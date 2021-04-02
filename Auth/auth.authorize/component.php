<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Bitrix vars
 * @global CUser $USER
 * @global CMain $APPLICATION
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponent $this
 */

use \Bitrix\Main\Localization\Loc;

$arResult["STORE_PASSWORD"] = COption::GetOptionString("main", "store_password", "Y") == "Y" ? "Y" : "N";

$arRes = array();
foreach ($arResult as $key => $value) {
    $arRes[$key] = htmlspecialcharsbx($value);
    $arRes['~' . $key] = $value;
}
$arResult = $arRes;

$arRequestParams = array(
    "USER_LOGIN",
    "USER_PASSWORD",
    'dialog'
);

foreach ($arRequestParams as $param) {
    $arResult[$param] = ($_REQUEST[$param] != '' ? $_REQUEST[$param] : "");
    $arResult[$param] = htmlspecialcharsbx($arResult[$param]);
}

if($arResult['dialog'] == 'auth') {
    $arResult['show'] = true;
}

$ajaxResult['status'] = 'N';
$ajaxResult['message'] = '';
$ajaxResult['field'] = '';

if ($arResult["USER_LOGIN"] && $arResult["USER_PASSWORD"]) {
    $rsUser = CUser::GetByLogin($arResult["USER_LOGIN"]);
    $arUser = $rsUser->Fetch();
    if ($arUser) {
        if (!is_object($USER)) {
            $USER = new CUser;
        }
        if($arUser['ACTIVE'] == 'Y') {
            $arAuthResult = $USER->Login($arResult["USER_LOGIN"], $arResult["USER_PASSWORD"], $arResult["STORE_PASSWORD"]);
            if (!$arAuthResult['MESSAGE']) {
                $ajaxResult['status'] = 'Y';
            } else {
                $ajaxResult['field'] = 'USER_PASSWORD';
                $ajaxResult['message'] = Loc::getMessage('main_register_incorrect_passwd');
            }
        } else {
            $ajaxResult['field'] = 'USER_LOGIN';
            $ajaxResult['message'] = Loc::getMessage('main_register_user_inactive');
        }
    } else {
        $ajaxResult['field'] = 'USER_LOGIN';
        $ajaxResult['message'] = Loc::getMessage('main_register_no_user');
    }
} else {
    $ajaxResult['field'] = 'USER_LOGIN';
    $ajaxResult['message'] = Loc::getMessage('user_error_incorrect_params');
}

if ($_REQUEST['auth'] == 'Y' && $_REQUEST['type']) {
    $APPLICATION->RestartBuffer();
    echo json_encode($ajaxResult);
    die();
} else {
    $loginCookieName = COption::GetOptionString("main", "cookie_name", "BITRIX_SM") . "_LOGIN";
    $arResult["~LOGIN_COOKIE_NAME"] = $loginCookieName;
    $arResult["~LAST_LOGIN"] = $_COOKIE[$loginCookieName];
    $arResult['USER_LOGIN'] = $arResult['~LAST_LOGIN'];
}

$this->IncludeComponentTemplate();
