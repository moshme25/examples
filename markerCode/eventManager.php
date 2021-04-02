<? 
Main\EventManager::getInstance()->addEventHandler(
    'iblock',
    'OnAfterIBlockElementUpdate', 
    'markinGroupUpdate'
);

// Автоматическое обновление поля группа товаров у торгового предложения по значению свойства GRUPPA_TOVAROV_MARKIROVKA
function markinGroupUpdate(&$arFields)
{
	if ($arFields['IBLOCK_ID'] == 20 && $arFields['RESULT']) {
		if (Cmodule::IncludeModule('catalog')) {
			global $USER_FIELD_MANAGER;
			$db_props = CIBlockElement::GetProperty($arFields['IBLOCK_ID'], $arFields["ID"], [], ["CODE"=>"GRUPPA_TOVAROV_MARKIROVKA"]);
			if ($ar_props = $db_props->Fetch()) {
				$productGroup = $ar_props["VALUE"];
			}
			$productGroup_old = $USER_FIELD_MANAGER->GetUserFieldValue('PRODUCT', 'UF_PRODUCT_GROUP', $arFields["ID"]);
			if ($productGroup != $productGroup_old) {
				$USER_FIELD_MANAGER->Update("PRODUCT", $arFields["ID"], ["UF_PRODUCT_GROUP" => $productGroup]);
			}
		}
	}
}

?>