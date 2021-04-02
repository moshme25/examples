<?php
global $APPLICATION;
use Bitrix\Main;
use \Bitrix\Main\Loader; 
use Bitrix\Main\UserGroupTable;
use Bitrix\Sale;
Loader::IncludeModule('sale');
Loader::IncludeModule('main');
/**
 *  Класс для работы с доставкой
 */
class Delivery{
    /**
     * Получение точек самовызова и курьеров для города
     * 
     * @param mixed $arData - массив с данными
     * 
     * @return array
     */
    public static function getItemsDelivery($city)
    {
        $arData = self::getWeightandDimensions();

        $parcel_size = '[' . $arData["DIMENSIONS"][0]  . ',' . $arData["DIMENSIONS"][1] . ',' . $arData["DIMENSIONS"][2] .']';
        $url.= "https://api3.marschroute.ru/8a1623d5079174d8edd1f928de58993e/delivery_city?name=" . $city . "&weight=" . $arData["WEIGHT"] . "&parcel_size=" . $parcel_size;

        //генерация url
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        if (is_array($result["data"]))
            return $result["data"][0];
        return false;
    }
    /**
     * Получение массива с весом корзины и размером
     * 
     * @return array
     */
    public static function getWeightandDimensions()
    {
        $basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), "s1");
        $basket->refresh();//Обновление данных корзины
        $weight = $basket->getWeight();
        $arVolume = [];
        foreach ($basket as $basketItem) {
            $dimensions = unserialize($basketItem->getField("DIMENSIONS"));
            $arVolume[] = array_product($dimensions);
        }
        $totalVolume = array_sum($arVolume)
        $side = round(pow($totalVolume, (1/3)))*10;
        $arDimen = [$side, $side , $side];
        return ["WEIGHT" => $weight, "DIMENSIONS" => $arDimen];
    }
    /**
     * Проверяет доступна ли доставка в данный регион
     * 
     * @param mixed $city
     * @param mixed $total_price
     * 
     * @return bool
     */
    public static function AllowDelivery($city, $total_price)
    {
        $setting = Utils::GetMainSettings();
        $settingPrice = $setting["NOT_DELIVERY_PRICE"]["VALUE"];
        $settingCity = $setting["DELIVERY_CITY"]["VALUE"];
        $status = array_search($city, $settingCity); //false  - в регионы
        if (!is_bool($status)) {
            return true;
        }
        return ($total_price >= $settingPrice && is_bool($status)) ? true : false;
    }
}
?>