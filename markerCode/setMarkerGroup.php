<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
$APPLICATION->SetTitle('Обновление свойства "Группа товаров"');

use Bitrix\Main\Loader; 
use Bitrix\Main\Application;

Loader::IncludeModule("main");
Loader::IncludeModule("iblock");
Loader::IncludeModule("catalog");
Loader::IncludeModule("sale");

$request = Application::getInstance()->getContext()->getRequest();

if ($request["update_marking_group"] == "Y") {
    
    global $USER_FIELD_MANAGER;
    $arSelect = ["ID", "NAME", "PROPERTY_GRUPPA_TOVAROV_MARKIROVKA"];
    $arFilter = ["IBLOCK_ID"=> 20, "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y"];
    $res = CIBlockElement::GetList([], $arFilter, false, [], $arSelect);

    $index = 0;
    while($ob = $res->Fetch())
    {
        $productGroup_old = $USER_FIELD_MANAGER->GetUserFieldValue('PRODUCT', 'UF_PRODUCT_GROUP', $ob["ID"]);
        if ($ob["PROPERTY_GRUPPA_TOVAROV_MARKIROVKA_VALUE"] != $productGroup_old) {
            ++$index;
            $USER_FIELD_MANAGER->Update("PRODUCT", $ob["ID"], ["UF_PRODUCT_GROUP" => $ob["PROPERTY_GRUPPA_TOVAROV_MARKIROVKA_VALUE"]]);
        }
    }
    ?>
    <p>Свойство "Группа товаров" успешно обновлены у <?= $index ?> элементов.</p>
    <a href="/bitrix/admin/setMarkerGroup.php">Вернуться</a>
<? } else { ?>
    <form method="post" action="" enctype="multipart/form-data">
        <h3>Обновление свойства "Группа товаров"</h3>
        <input type="submit" name="submit" value="Обновить">
        <input type="hidden" name="update_marking_group" value="Y">
    </form> 
<? }
require($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");?>