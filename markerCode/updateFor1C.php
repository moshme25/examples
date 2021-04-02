<?php
/**
 * Аутентификация  
 * 
 * @param mixed $user - логин
 * @param mixed $pass - пароль
 * 
 * @return bool
 */
function pc_validate($user,$pass) {
    $users = array('updateOrder' => '********************', 'bro' => '*****************'); 
    if (isset($users[$user]) && ($users[$user] == $pass)) {
        return true;
    } else { 
        return false;
    }
}

if (! pc_validate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) { 
    header('WWW-Authenticate: Basic realm="My Website"'); 
    header('HTTP/1.0 401 Unauthorized'); echo "You need to enter a valid username and password.";
    exit;
} 
require_once $_SERVER['DOCUMENT_ROOT']."/bitrix/modules/main/include/prolog_before.php";

$json = file_get_contents('php://input');
if ($json) {
    $arData = json_decode($json, true);
    if ($arData > 0) {
        if (\Bitrix\Main\Loader::includeModule('sale')) {
            foreach ($arData as $arOrder) {
                $order = \Bitrix\Sale\Order::load($arOrder['orderId']);
                $ShipmentCollection = $order->getShipmentCollection();
                foreach ($ShipmentCollection as $shipment) {
                    if ($shipment->isSystem())
                         continue;
                    $ShipmentItemCollection = $shipment->getShipmentItemCollection();
                    foreach ($ShipmentItemCollection as $shipmentItem) {
                        $update = false;
                        $collection  = $shipmentItem->getShipmentItemStoreCollection();
                        $product_xml_id = $shipmentItem->getBasketItem()->getField('PRODUCT_XML_ID');
                        $index = array_search($product_xml_id, array_column($arOrder['items'], 'productId'));

                        //Обновление Маркировочного кода
                        foreach ($collection as $ShipmentItemStoreitem) { 
                            if ($ShipmentItemStoreitem) {
                                $ShipmentItemStoreitem->setField('MARKING_CODE', $arOrder['items'][$index]['markCode']);
                                $update = true;
                            }
                            continue;
                        }
                        //Добавляение Маркировочного кода
                        if (!$update) {
                            $itemStore  = $collection->createItem($shipmentItem->getBasketItem());
                            $itemStore->setFields([
                                'QUANTITY' => $arOrder['items'][$index]['quantity'],
                                'MARKING_CODE'=> $arOrder['items'][$index]['markCode']
                            ]);
                        }
                        $r = $order->save();
                        if (!$r->isSuccess())
                        {
                            echo "<pre>"; print_r($r->getErrorMessages()); echo "</pre>";
                        }
                    }
                }
            }
        }
    } else {
        echo "Ошибка обработчки JSON";
        return false;
    }
} else {
    echo "Ошибка получения JSON";
    return false;
}
?>

