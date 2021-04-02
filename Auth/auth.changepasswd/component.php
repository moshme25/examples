<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Bitrix vars
 *
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponent $this
 * @global CMain $APPLICATION
 * @global CUser $USER
 */

use AZTT\helpers\AuthHelpers;
use AZTT\helpers\SmsHelpers;
use \Bitrix\Main\Localization\Loc;

foreach ($arResult as $key => $value) {
    if (!is_array($value)) {
        $arResult[$key] = htmlspecialcharsbx($value);
    }
}

$arRequestParams = array(
    'USER_PASSWORD',
    'USER_CHECKWORD',
    'change_password',
    'USER_PHONE_NUMBER',
);

foreach ($arRequestParams as $param) {
    $arResult[$param] = ($_REQUEST[$param] != '' ? $_REQUEST[$param] : "");
    $arResult[$param] = htmlspecialcharsbx($arResult[$param]);
}

$arResult['USER_CONFIRM_PASSWORD'] = $arResult['USER_PASSWORD'];

if (!CMain::IsHTTPS() && COption::GetOptionString('main', 'use_encrypted_auth', 'N') == 'Y') {
    $sec = new CRsaSecurity();
    if (($arKeys = $sec->LoadKeys())) {
        $sec->SetKeys($arKeys);
        $sec->AddToForm('newpasswordForm', array('USER_PASSWORD'));
    }
}

//stored in the auth.forgotpasswd/component.php
$arResult["PHONE_REGISTRATION"] = (COption::GetOptionString("main", "new_user_phone_auth", "N") == "Y" && $arResult["USER_PHONE_NUMBER"] != '');
$arResult["USER_PHONE_NUMBER"] = $arResult["USER_PHONE_NUMBER"] ? $arResult["USER_PHONE_NUMBER"] : $_SESSION["USER_PHONE_NUMBER"];

if ($arResult["PHONE_REGISTRATION"]) {
    $arResult["PHONE_CODE_RESEND_INTERVAL"] = CUser::PHONE_CODE_RESEND_INTERVAL;
}

if (isset($_GET["USER_LOGIN"])) {
    $arResult["~LAST_LOGIN"] = CUtil::ConvertToLangCharset($_GET["USER_LOGIN"]);
} elseif (isset($_POST["USER_LOGIN"])) {
    $arResult["~LAST_LOGIN"] = $_POST["USER_LOGIN"];
} else {
    $arResult["~LAST_LOGIN"] = $_COOKIE[COption::GetOptionString("main", "cookie_name", "BITRIX_SM") . "_LOGIN"];
}

$arResult["LAST_LOGIN"] = htmlspecialcharsbx($arResult["~LAST_LOGIN"]);

$ajaxResult['status'] = 'N';
$ajaxResult['message'] = '';
$ajaxResult['field'] = 'USER_PASSWORD';

if ($arResult['change_password'] == 'y') { // смена пароля по коду из СМС
    if ($USER->IsAuthorized()) {
        return false;
    }else{
        $arResult['show'] = true;
    }
} elseif ($arResult['USER_PHONE_NUMBER']) {
    $verifyResult = SmsHelpers::verifyPhoneCode($arResult["PHONE_REGISTRATION"], '55555'); // проверка что пользователь верифицирован
    if ($verifyResult['STATUS']) {
        $user = $USER->getByLogin($arResult["LAST_LOGIN"])->fetch();
        if ($user) {
            $arResult['USER_CHECKWORD'] = $arUser['CHECKWORD'];
        }
    } else {
        $ajaxResult['status'] = 'N';
        $ajaxResult['message'] = $verifyResult['ERROR'];
    }
}

if ($_REQUEST['auth'] == 'Y' && $arResult["LAST_LOGIN"]) {
    if ($arResult['USER_CHECKWORD'] && $arResult['USER_PASSWORD'] && $arResult['USER_CONFIRM_PASSWORD']) {
        $changePassResult = AuthHelpers::changePassword(
            $arResult["LAST_LOGIN"],
            $arResult['USER_CHECKWORD'],
            $arResult['USER_PASSWORD'],
            $arResult['USER_CONFIRM_PASSWORD'],
            false, "", 0, true,
            $arResult["USER_PHONE_NUMBER"]
        );
        if ($changePassResult["TYPE"] == "OK") {
            $ajaxResult['status'] = 'Y';
            $ajaxResult['message'] = "Пароль успешно сменен.";
            //Авторизуем пользователя
            $arAuthResult = $USER->Login($arResult["LAST_LOGIN"], $arResult["USER_PASSWORD"]);
            $APPLICATION->arAuthResult = $arAuthResult;
        } else {
            $ajaxResult['message'] = $changePassResult['MESSAGE'];
        }
    } else {
        $ajaxResult['message'] = Loc::getMessage('user_error_incorrect_params');
    }
} else {
    $ajaxResult['message'] = Loc::getMessage('main_register_no_user');
}

if ($_REQUEST['auth'] == 'Y' && $_REQUEST['type']) {
    $APPLICATION->RestartBuffer();
    echo json_encode($ajaxResult);
    die();
}

$this->IncludeComponentTemplate();
