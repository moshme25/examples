<?

namespace AZTT\helpers;


use AZTT\agents\ImportOffersAgent;
use AZTT\Entities\SKUImport;
use AZTT\Entities\ImportHistory;
use AZTT\Entities\Offer;
use AZTT\helpers\UtilityHelpers;
use AZTT\helpers\HighloadBlockHelpers;
use \Bitrix\Iblock\ElementTable;
use \Bitrix\Iblock\SectionTable;
use \Bitrix\Main\Loader;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Type\DateTime;

use \Bitrix\Main\Diag\Debug;


Loader::includeModule('iblock');
Loader::includeModule('catalog');

class ImportHelpers
{
    protected $CATALOG_IBLOCK_ID = [];
    protected $OFFERS_IBLOCK_ID = [];
    protected $arGroupsCache = [];
    protected $arOffersCache = [];
    protected $arProductsCache = [];

    public function __construct()
    {
        $this->CATALOG_IBLOCK_ID = IBLOCK_CATALOG_ID;
        $this->OFFERS_IBLOCK_ID = IBLOCK_OFFERS_ID;
    }

    private function getSectionByXML($xml_id, $iblock_id)
    {
        if (isset($this->arGroupsCache[$xml_id])) {
            return $this->arGroupsCache[$xml_id];
        }

        $params = [
            'select' => ['ID', 'ACTIVE'],
            'filter' => [
                '=IBLOCK_ID' => $iblock_id,
                '=XML_ID' => $xml_id,
            ],
        ];

        return $this->arGroupsCache[$xml_id] = SectionTable::getRow($params);
    }

    private function getOfferByXML($xml_id, $iblock_id, $provisionerID)
    {
        if (isset($this->arOffersCache[$xml_id."_".$provisionerID])) {
            return $this->arOffersCache[$xml_id."_".$provisionerID];
        }

        $rsElement = \CIBlockElement::GetList(
            array("SORT" => "ASC"),
            $arFilter = array(
                '=IBLOCK_ID' => $iblock_id,
                '=XML_ID' => $xml_id,
                '=PROPERTY_PROVISIONER' => $provisionerID,
            ),
            false,
            false,
            $arSelectFields = array('ID', 'ACTIVE', 'IBLOCK_ID', 'PROPERTY_PROVISIONER')
        );
        
        return $this->arOffersCache[$xml_id."_".$provisionerID] = $rsElement->fetch();
    }

    private function getProductByXML($xml_id, $iblock_id)
    {
        if (isset($this->arProductsCache[$xml_id])) {
            return $this->arProductsCache[$xml_id];
        }

        if (!$xml_id && !$iblock_id) {
            return [];
        }

        $params = [
            'select' => ['ID', 'ACTIVE', 'IBLOCK_ID', 'XML_ID', 'IBLOCK_SECTION_ID'],
            'filter' => [
                '=IBLOCK_ID' => $iblock_id,
                '=XML_ID' => $xml_id,
            ],
        ];

        return $this->arProductsCache[$xml_id] = ElementTable::getRow($params);
    }

    public function saveSections($arItems, $IBLOCK_ID = 0)
    {
        // TODO: доработать вывод ошибок не через Exception

        $result = [];

        if (!$IBLOCK_ID) {
            $IBLOCK_ID = $this->CATALOG_IBLOCK_ID;
        }

        $childs = [];
        foreach ($arItems as $arItem) {
            if (!strlen($arItem['XML_ID'])) {
                throw new \Exception('Empty section XML ID ' . $arItem['name']);
            }
            if (!strlen($arItem['CODE'])) {
                $arItem['CODE'] = \CUtil::translit($arItem['NAME'], 'ru', [
                    "replace_space" => '-',
                    "replace_other" => '-',
                ]);
            }
            $arFields = [
                'ACTIVE' => 'Y',
                'CODE' => $arItem['CODE'],
                'NAME' => $arItem['NAME'],
                'XML_ID' => $arItem['XML_ID'],
                'TIMESTAMP_X' => new DateTime,
            ];
            $arSection = $this->getSectionByXML($arItem['XML_ID'], $IBLOCK_ID);
            if ($arSection['ID'] > 0) {
                // если раздел существует, сравним названия и обновим если требуется
                if ($arSection['NAME'] != $arFields['NAME']) {
                    SectionTable::update($arSection['ID'], $arFields);
                }
            } else {
                if (strlen($arItem['PARENT_XML_ID']) > 0) {
                    $arParent = $this->getSectionByXML($arItem['PARENT_XML_ID'], $IBLOCK_ID);
                    if (!$arParent['ID']) {
                        // если дочерний раздел идет раньше родителя, то создать его нужно позже (рекурсия)
                        $childs[] = $arItem;
                        continue;
                    }
                    $arFields['IBLOCK_SECTION_ID'] = $arParent['ID'];
                }
                $arFields = array_merge($arFields, [
                    'IBLOCK_ID' => $IBLOCK_ID,
                ]);
                $arSection['ID'] = SectionTable::add($arFields)->getId();
            }

            if (!$arSection['ID']) {
                throw new \Exception('Cant create a section');
            }
            $result[$arItem['XML_ID']] = $arSection['ID'];
        }

        if (!empty($childs)) {
            $this->saveSections($childs, $IBLOCK_ID);
        }

        return $result;
    }

    /**
     * Импорт ТП
     *
     * @param  array $arItems - array of Offer
     * @param  int $IBLOCK_ID
     *
     * @return array
     */
    public function saveOffers($arItems, $IBLOCK_ID = 0, $provisionerID = 0)
    {
        // TODO: доработать статистику успешного импорта, обновления цен, обновления количества

        $statistic = [];
        $productsIDs = []; // обновляемые товары
        $offersIDs = []; // обновляемые ТП
        $importHistory = new ImportHistory();

        $importHistoriesRes = HighloadBlockHelpers::getByFilter(["UF_PROVISIONER" => $provisionerID], HIGHLOADBLOCK_IMPORT_HISTORY_ID, false, ['ID' => 'DESC'], 1);
        if(isset($importHistoriesRes[0]['UF_ACTIVE'])) {
            if($importHistoriesRes[0]['UF_ACTIVE'] == false) {
                $importHistory->setHighloadData($importHistoriesRes[0]);
            }
        }

        if (!$IBLOCK_ID) {
            $IBLOCK_ID = $this->OFFERS_IBLOCK_ID;
        }

        if (empty($arItems)) {
            return $statistic;
        }

        // сохранение в историю импорта
        if(!$provisionerID) {
            $profile = UtilityHelpers::getUserExtendedInfo();
            $importHistory->provisioner = $profile->id;
            $provisionerID = $profile->id;
        } else {
            $importHistory->provisioner = $provisionerID;
        }

        // получаем список значений свойства "Наличие"
        $property_amount_values = [];
        $property_enums = \CIBlockPropertyEnum::GetList(["SORT" => "ASC"], ["IBLOCK_ID" => IBLOCK_OFFERS_ID, "CODE" => "AMOUNT"]);
        while ($enum_fields = $property_enums->GetNext()) {
            $property_amount_values[$enum_fields["VALUE"]] = $enum_fields;
        }

        // получаем список способов оплаты
        unset($getListArray);
        $getListArray['filter']['IBLOCK_ID'] = IBLOCK_PAYMENT_TYPE_ID;
        $getListArray['select'] = ['ID', 'NAME'];
        $getListArray['order'] = ['ID' => 'ASC'];
        $getListArray['nav'] = false;
        $payment_types = IBlockHelpers::getByFilter($getListArray, 'name');
        foreach ($payment_types as $key => $value) {
            $payment_types[mb_strtolower($value['NAME'])] = $value;
            unset($payment_types[$key]);
        }

        // получаем список способов доставки
        unset($getListArray);
        $getListArray['filter']['IBLOCK_ID'] = IBLOCK_DELIVERY_TERMS_ID;
        $getListArray['select'] = ['ID', 'NAME'];
        $getListArray['order'] = ['ID' => 'ASC'];
        $getListArray['nav'] = false;
        $delivery_types = IBlockHelpers::getByFilter($getListArray, 'name');
        foreach ($delivery_types as $key => $value) {
            $delivery_types[mb_strtolower($value['NAME'])] = $value;
            unset($delivery_types[$key]);
        }

        foreach ($arItems as $arItem) {
            $statistic[$arItem->xml_id]['status'] = 'error';
            $statistic[$arItem->xml_id]['id'] = 0;
            $statistic[$arItem->xml_id]['error'] = '';

            if (!($arItem instanceof Offer)) {
                $statistic[$arItem->xml_id]['error'] = Loc::getMessage('import_invalid_type');
                continue;
            }

            if (!$arItem->xml_id) {
                $statistic[$arItem->xml_id]['error'] = Loc::getMessage('import_empty_xml_id');
                continue;
            }

            if (!$arItem->product_xml_id) {
                $statistic[$arItem->xml_id]['error'] = Loc::getMessage('import_empty_product_xml_id');
                continue;
            }

            $product = $this->getProductByXML($arItem->product_xml_id, $this->CATALOG_IBLOCK_ID);
            $arItem->product_id = $product['ID'];
            if (!$arItem->product_id) {
                $statistic[$arItem->xml_id]['error'] = Loc::getMessage('import_product_not_found');
                continue;
            }
            $arItem->product_section_id = $product['IBLOCK_SECTION_ID'];

            // заполняем свойства типа "Привязка к элементу"
            $arItem->amount = $property_amount_values[$arItem->amount]['ID'];
            $arItem->payment_type = (int) $payment_types[mb_strtolower($arItem->payment_type)]['ID'];
            $arItem->delivery_type = (int) $delivery_types[mb_strtolower($arItem->delivery_type)]['ID'];
            
            $arElem = $this->getOfferByXML($arItem->xml_id, $IBLOCK_ID, $provisionerID);

            if ($arElem['ID']) {
                $updateData['FIELDS'] = $arItem->getIBlockFieldData();
                $updateData['PROPS'] = $arItem->getIBlockPropData();
                $updateResult = IBlockHelpers::addOrUpdateElement($arElem['ID'], $updateData, 'update');
                if ($updateResult['STATUS']) {
                    $statistic[$arItem->xml_id]['id'] = $updateResult['ID'];
                    $statistic[$arItem->xml_id]['status'] = 'updated';
                    $arItem->id = $updateResult['ID'];
                    $productsIDs[$arItem->product_id] = $arItem->product_id;
                    $offersIDs[$updateResult['ID']] = $updateResult['ID'];
                } else {
                    $statistic[$arItem->xml_id]['error'] = $updateResult['ERROR'];
                    continue;
                }
            } else {
                $addData['FIELDS'] = $arItem->getIBlockFieldData();
                $addData['FIELDS']['IBLOCK_ID'] = $IBLOCK_ID;
                $addData['PROPS'] = $arItem->getIBlockPropData();
                $addResult = IBlockHelpers::addOrUpdateElement(0, $addData, 'add');
                if ($addResult['STATUS']) {
                    $statistic[$arItem->xml_id]['id'] = $addResult['ID'];
                    $statistic[$arItem->xml_id]['status'] = 'added';
                    $arItem->id = $addResult['ID'];
                    $productsIDs[$arItem->product_id] = $arItem->product_id;
                    $offersIDs[$addResult['ID']] = $addResult['ID'];
                } else {
                    $statistic[$arItem->xml_id]['error'] = $addResult['ERROR'];
                    continue;
                }
            }

            \CCatalogProduct::Add($arItem->getProductData()); // добавление элемента в торговый каталог

            if (!empty($arItem->getBasePriceData())) {
                $priceUpdateResult = $this->addOrUpdatePrice($arItem->getBasePriceData()); // устанавливаем/обновляем базовую цену
                if ($priceUpdateResult !== true) {
                    $statistic[$arItem->xml_id]['error'] = $priceUpdateResult[0];
                    $statistic[$arItem->xml_id]['status'] = 'error';
                }
            }

            if (!empty($arItem->getOldPriceData())) {
                $priceUpdateResult = $this->addOrUpdatePrice($arItem->getOldPriceData()); // устанавливаем/обновляем старую цену
                if ($priceUpdateResult !== true) {
                    $statistic[$arItem->xml_id]['error'] .= $priceUpdateResult[0];
                    $statistic[$arItem->xml_id]['status'] = false;
                }
            }
        }

        Debug::writeToFile($offersIDs, "", '/logs/imports_' . date("d-m-Y") . '.txt');

        if(!$importHistory->id) {
            $importHistory->active = false;
            $importHistory->dateTime = new DateTime();
            $importHistory->products = array_keys($productsIDs);
            $importHistory->offers = array_keys($offersIDs);
            $addRes = HighloadBlockHelpers::add(HIGHLOADBLOCK_IMPORT_HISTORY_ID, $importHistory->getHighloadData());
        } else {
            $importHistory->products = array_unique(array_merge($importHistory->products, array_keys($productsIDs)));
            $importHistory->offers = array_unique(array_merge($importHistory->offers, array_keys($offersIDs)));
            $addRes = HighloadBlockHelpers::update($importHistory->id, HIGHLOADBLOCK_IMPORT_HISTORY_ID, $importHistory->getHighloadData());
        }

        $statistic['import_history']['status'] = $addRes['STATUS'];
        if (!$addRes['STATUS']) {
            $statistic['import_history']['error'] = $addRes['ERROR'];
        }

        return $statistic;
    }

    /**
     * Добавление/обновление цены
     *
     * @param  array $data
     *
     * @return boolean|array
     */
    public function addOrUpdatePrice($data)
    {
        if (empty($data)) {
            return [Loc::getMessage('iblock_incorrect_params')];
        }

        $rsP = \Bitrix\Catalog\PriceTable::getList([
            'filter' => ['CATALOG_GROUP_ID' => $data['CATALOG_GROUP_ID'], 'PRODUCT_ID' => $data['PRODUCT_ID']],
        ]);

        if ($arP = $rsP->fetch()) {
            unset($data['CATALOG_GROUP_ID']);
            unset($data['PRODUCT_ID']);
            $result = \Bitrix\Catalog\PriceTable::update($arP['ID'], $data);
        } else {
            $result = \Bitrix\Catalog\Model\Price::add($data);
        }

        if ($result->isSuccess()) {
            return true;
        } else {
            return $result->getErrorMessages();
        }
    }

    /**
     * Добавление выгрузки в HL-блок
     *
     * @param  ImportHistory $importHistory
     *
     * @return array
     */
    public function saveFileToHL($provisionerID = 0, $file = false)
    {
        $result['message'] = '';
        $result['status'] = 'Y';
        $result['id'] = 0;

        if (!$provisionerID || !$file) {
            $result['message'] = Loc::getMessage('iblock_incorrect_params');
            return $result;
        }

        $skuImportNew = new SKUImport();
        $skuImportNew->provisioner = $provisionerID;
        $skuImportNew->file = $file;
        $skuImportNew->start = 0;
        $skuImportNew->dateTime = new DateTime();
        
        $skuImportRes = HighloadBlockHelpers::add(HIGHLOADBLOCK_SKUIMPORT_ID, $skuImportNew->getHighloadData());
        if ($skuImportRes['ID']) {
            $result['id'] = $skuImportRes['ID'];
        }

        return $result;
    }
}