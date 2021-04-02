<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Bitrix vars
 * @global CMain $APPLICATION
 * @global CUser $USER
 * @var array $arParams
 * @var array $arResult
 */

use AZTT\helpers\SmsHelpers;
use \Bitrix\Main\Localization\Loc;

$arResult["~CONFIRM_CODE"] = trim($_REQUEST['CONFIRM_CODE']);
$arResult["CONFIRM_CODE"] = htmlspecialcharsbx($arResult["~CONFIRM_CODE"]);
$arResult["~LOGIN"] = trim($_REQUEST['LOGIN']);
$arResult["LOGIN"] = htmlspecialcharsbx($arResult["~LOGIN"]);

$rsUser = CUser::GetByLogin($arResult["~LOGIN"]);

$ajaxResult['status'] = 'N';
$ajaxResult['message'] = '';

if ($arResult['USER'] = $rsUser->GetNext()) {
    $verifyResult = SmsHelpers::verifyPhoneCode($arResult['USER']['LOGIN'], $arResult["CONFIRM_CODE"]);
    if ($verifyResult['STATUS']) {
        $ajaxResult['status'] = 'Y';
    } else {
        $ajaxResult['status'] = 'N';
        $ajaxResult['message'] = $verifyResult['ERROR'];
    }
    if($ajaxResult['status'] == 'Y') {
        if ($arResult['USER']['ACTIVE'] === 'N') { // верификация после регистрации
            $ajaxResult['status'] = 'registration';
            $obUser = new CUser;
            $updateUser = $obUser->Update($arResult['USER']['ID'], ['ACTIVE' => 'Y']);
            if (!$updateUser) {
                $ajaxResult['status'] = 'N';
                $ajaxResult['message'] = $updateUser->LAST_ERROR;
            } else {
                $USER->Authorize($arResult['USER']['ID']); 
            }
        } else { // верификация для смены пароля
            $ajaxResult['status'] = 'changepass';
        }
    }
} else {
    $ajaxResult['message'] = Loc::getMessage('main_register_no_user');
}

if ($_REQUEST['auth'] == 'Y' && $_REQUEST['type']) {
    $APPLICATION->RestartBuffer();
    echo json_encode($ajaxResult);
    die();
} else {
    $arResult["USER_PHONE_NUMBER"] = $_COOKIE["USER_PHONE_NUMBER"];
}

$this->IncludeComponentTemplate();
