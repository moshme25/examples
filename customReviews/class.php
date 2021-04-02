<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
use \Bitrix\Main\Engine\Contract\Controllerable;
use HB\config\Config;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity;
use HB\handler\ReviewHandler;

class ReviewsComponent extends CBitrixComponent implements Controllerable {

    function onPrepareComponentParams($arParams) {
        return $arParams;
    }

    public function configureActions()
    {
        return [
            'sendReviewForm' => [
                'prefilters' => [],
            ],
            'addLike' => [
                'prefilters' => [],
            ],
            'showMore' => [
                'prefilters' => [],
            ],
        ];
    }

    /**
     * Метод отправляет отзыв (по умолчанию не активный, одобряется в админке)
     *
     * @param array $data
     * @return array
     */
    public function sendReviewFormAction($data)
    {
        \CModule::IncludeModule('iblock');

        $name = $data["name"] ?? '';
        $stars = (int) $data["stars"] ?? 0;
        $text = $data["review"] ?? '';
        $id = $data["id"] ?? '';

        $result = [
            "STATUS" => false,
        ];
        
        $date = new DateTime();
        $date = $date->format("d.m.Y");
       
        if (!empty($name) && !empty($text) && !empty($id)) {
            $el = new CIBlockElement;
            $PROP = [
                24 => $name,
                25 => $id,
                26 => $text,
                27 => $stars,
                141 => CUser::GetID(),
            ];
            
            $addData = [
                "IBLOCK_ID" => Config::getInstance()->getIblock('reviews'),
                "PROPERTY_VALUES" => $PROP,
                'ACTIVE' => "N",
                'DATE_ACTIVE_FROM' => $date,
                "NAME" => "Отзыв",
            ];
           
            $status = $el->Add($addData);
            
            $result = [
                "STATUS" => $status !== false,
                "HTML" => "",
            ];    
        }

        return $result;
    }

    /**
     * Метод проставления лайка/дизлайка коментарию
     *
     * @param array $data
     * @return array
     */
    public function addLikeAction($data)
    {
        global $USER;
        \CModule::IncludeModule("highloadblock");
        
        $result = [
            "STATUS" => false,
        ];

        $like = $data["like"];
        $reviewId = $data["review"];
       
        if ($USER->IsAuthorized() && !empty($like) && !empty($reviewId)) {
            $hlblock = HL\HighloadBlockTable::getById(Config::getInstance()->getHighloadblock('likes'))->fetch();

            $entity = HL\HighloadBlockTable::compileEntity($hlblock);
            $entity_data_class = $entity->getDataClass();
            $rsData = $entity_data_class::getList(array(
            "select" => array("*"),
            "order" => array("ID" => "ASC"),
            "filter" => array('UF_USER' => $USER::GetID(), 'UF_REVIEW_ID' => $reviewId)
            ));
            
            if(empty($rsData->fetch())) {
                
                $entity_data_class::add([
                    "UF_LIKE" => $like,
                    "UF_USER" => $USER::GetID(),
                    "UF_REVIEW_ID" => $reviewId,
                ]);

                $result = [
                    "STATUS" => true,
                ];
            }
            
        }
        
        return $result;
    }

    /**
     * Возвращает все отзывы (кнопка - Показать еще)
     *
     * @param array $data
     * @return array
     */
    public function showMoreAction($data)
    {
        $firstColumn = '';
        $secondColumn = '';

        $result = [
            "STATUS" => false,
        ];

        if (!empty($data["id"])) {

            $db = CIBlockElement::GetList(array("SORT" => "asc"), array("IBLOCK_ID" => Config::getInstance()->getIblock('reviews'), "PROPERTY_PRODUCT_ID" => $data["id"], "ACTIVE" => "Y"), false, false, array("ID","PROPERTY_USER_NAME", "PROPERTY_PRODUCT_ID", "PROPERTY_TEXT", "PROPERTY_RATING", "PROPERTY_LIKE", "PROPERTY_DISLIKE", "DATE_ACTIVE_FROM"));
            
            while($res = $db->GetNext()){
                $arResult["ITEMS"][] = $res;
                $reviewIds[] = $res["ID"]; 
            }
    
            $arResult["LIKES"] = self::getLikes($reviewIds);
            
            foreach ($arResult["ITEMS"] as $key => $item) {
                if ($key == 0 || $key % 2 == 0) {
                    $firstColumn .= '<div class="productpage-reviews__item">
                                        <div class="d-flex align-center space-between">
                                            <h2 class="productpage-reviews__name">'. $item["PROPERTY_USER_NAME_VALUE"] . '</h2>
                                            <h2 class="productpage-reviews__date">'. $item["DATE_ACTIVE_FROM"] . '</h2>
                                        </div>
                                        <div class="item-rate">
                                            <div class="item-rate-'. $item["PROPERTY_RATING_VALUE"] . '"></div>
                                        </div>
                                        <p class="productpage-reviews__desc">'. $item["PROPERTY_TEXT_VALUE"] . '</p>
                                        <div class="productpage-reviews__item-votes">
                                            <button class="productpage-reviews__item-votes-add js-like" data-like="4" data-review="'. $item["ID"] . '"></button>
                                                <span class="productpage-good-votes">'. $arResult["LIKES"][$item["ID"]]["like"] . '</span>
                                            <button class="productpage-reviews__item-votes-remove js-like" data-like="5" data-review="'. $item["ID"] . '"></button>
                                                <span class="productpage-bad-votes">'. $arResult["LIKES"][$item["ID"]]["dislike"] . '</span>
                                        </div>
                                    </div>';
                } else {
                    $secondColumn .= '<div class="productpage-reviews__item">
                                        <div class="d-flex align-center space-between">
                                            <h2 class="productpage-reviews__name">'. $item["PROPERTY_USER_NAME_VALUE"] . '</h2>
                                            <h2 class="productpage-reviews__date">'. $item["DATE_ACTIVE_FROM"] . '</h2>
                                        </div>
                                        <div class="item-rate">
                                            <div class="item-rate-'. $item["PROPERTY_RATING_VALUE"] . '"></div>
                                        </div>
                                        <p class="productpage-reviews__desc">'. $item["PROPERTY_TEXT_VALUE"] . '</p>
                                        <div class="productpage-reviews__item-votes">
                                            <button class="productpage-reviews__item-votes-add js-like" data-like="4" data-review="'. $item["ID"] . '"></button>
                                                <span class="productpage-good-votes">'. $arResult["LIKES"][$item["ID"]]["like"] . '</span>
                                            <button class="productpage-reviews__item-votes-remove js-like" data-like="5" data-review="'. $item["ID"] . '"></button>
                                                <span class="productpage-bad-votes">'. $arResult["LIKES"][$item["ID"]]["dislike"] . '</span>
                                        </div>
                                    </div>';
                }
                
            }
            
            $result = [
                "STATUS" => true,
                "HTML_FIRST" => $firstColumn,
                "HTML_SECOND" => $secondColumn,
                "COUNT" => count($arResult["ITEMS"]),
            ];
        }
        
        return $result;
    }

    /**
     * Возвращает лайки для комментариев
     *
     * @param array $reviewIds
     * @return array
     */
    private static function getLikes($reviewIds)
    {
        $result = [];
        if(!empty($reviewIds)) {
            \CModule::IncludeModule("highloadblock");

            $hlblock = HL\HighloadBlockTable::getById(Config::getInstance()->getHighloadblock('likes'))->fetch();
    
            $entity = HL\HighloadBlockTable::compileEntity($hlblock);
            $entity_data_class = $entity->getDataClass();
            $rsData = $entity_data_class::getList(array(
                "select" => array("UF_REVIEW_ID", "UF_LIKE"),
                "order" => array("ID" => "ASC"),
                "filter" => array('UF_REVIEW_ID' => $reviewIds)
            ));
    
            while($like = $rsData->fetch()) {
                
                if ($like["UF_LIKE"] == 4) {
                    $result[$like["UF_REVIEW_ID"]]["like"] += 1;
                } else {
                    $result[$like["UF_REVIEW_ID"]]["dislike"] += 1;
                }
            
            }
        }
        
        return $result;
    }

    /**
     * Возвращает рейтинг товара
     *
     * @return float|int
     */
    private function getOverallRating()
    {
        $db = CIBlockElement::GetList(array("SORT" => "asc"), array("IBLOCK_ID" => Config::getInstance()->getIblock('reviews'), "PROPERTY_PRODUCT_ID" => $this->arParams["ID"], "ACTIVE" => "Y"), false, false, array("ID", "PROPERTY_RATING"));

        $allRate = 0;
        $vote_count = 0;

        while($res = $db->GetNext()) {

            if(!empty($res["PROPERTY_RATING_VALUE"])) {

                $allRate += $res["PROPERTY_RATING_VALUE"];
                ++$vote_count;
            }
        }

        return ReviewHandler::calculateRating($allRate, $vote_count);
    }

    /**
     * Собирает данные для отображения отзывов
     *
     * @return void
     */
    public function index()
    {
        $db = CIBlockElement::GetList(array("SORT" => "asc"), array("IBLOCK_ID" => Config::getInstance()->getIblock('reviews'), "PROPERTY_PRODUCT_ID" => $this->arParams["ID"], "ACTIVE" => "Y"), false, array("nPageSize"=>10), array("ID","PROPERTY_USER_NAME", "PROPERTY_PRODUCT_ID", "PROPERTY_TEXT", "PROPERTY_RATING", "PROPERTY_LIKE", "PROPERTY_DISLIKE", "DATE_ACTIVE_FROM"));

        while($res = $db->GetNext()) {
            $arr = ParseDateTime($res["DATE_ACTIVE_FROM"], FORMAT_DATETIME);
            $res["DATE_ACTIVE_FROM"] = $arr["DD"] . ' ' . GetMessage("MONTH_".intval($arr["MM"])) . ' ' . $arr["YYYY"];
            
            $arResult['ITEMS'][] = $res;
                       
            $reviewIds[] = $res["ID"];
        }

        $arResult["overall_rating"] = $this->getOverallRating();

       
        $arResult["LIKES"] = self::getLikes($reviewIds);
       

        return $arResult;
    }
  
    function executeComponent(){
        //$this->request
        $this->arResult = $this->index();
        //$this->arParams
        
        $this->includeComponentTemplate();   
    }
}