<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use AZTT\helpers\SmsHelpers;
use AZTT\helpers\AuthHelpers;
use \Bitrix\Main\Localization\Loc;

$arResult["PHONE_REGISTRATION"] = (COption::GetOptionString("main", "new_user_phone_auth", "N") == "Y");

$user_field = $_REQUEST['USER_PHONE_OR_EMAIL'];
if ($user_field) {
    $arResult['USER_LOGIN'] = $user_field;
    if (strpos($user_field, '@')) {
        $arResult['USER_EMAIL'] = $user_field;
    } else {
        $arResult['USER_PHONE_NUMBER'] = $user_field;
    }
}

if ($arResult["PHONE_REGISTRATION"] && $arResult['USER_PHONE_NUMBER']) {
    $_COOKIE["USER_PHONE_NUMBER"] = $arResult['USER_PHONE_NUMBER'];
    $_SESSION["USER_PHONE_NUMBER"] = $arResult['USER_PHONE_NUMBER'];
}

foreach ($arResult as $key => $value) {
    if (!is_array($value)) {
        $arResult[$key] = htmlspecialcharsbx($value);
    }
}

$arResult["USER_PHONE_OR_EMAIL"] = htmlspecialcharsbx($_COOKIE[COption::GetOptionString("main", "cookie_name", "BITRIX_SM") . "_LOGIN"]);

$ajaxResult['status'] = 'N';
$ajaxResult['message'] = '';
$ajaxResult['field'] = 'USER_PHONE_OR_EMAIL';

// if ($_REQUEST['auth'] == 'Y' && $_REQUEST['type']) {
//     global $USER;
//     if (!$USER->IsAuthorized()) {
//         $ajaxResult['message'] = Loc::getMessage('system_unauthorized');
//         $APPLICATION->RestartBuffer();
//         echo json_encode($ajaxResult);
//         die();
//     }
// }

$rsUser = CUser::GetByLogin($arResult["USER_LOGIN"]);
$arUser = $rsUser->Fetch();
//if ($arUser && $arUser["ACTIVE"] == "Y") {
if ($arUser) {
    if ($arResult['USER_PHONE_NUMBER']) {
        $sendCodeParams["phoneNumber"] = $arResult['USER_PHONE_NUMBER'];
        $sendCode = SmsHelpers::sendCodeAction($sendCodeParams);
        if ($sendCode['STATUS']) {
            $ajaxResult['status'] = 'phone';
        } else {
            $ajaxResult['message'] = $sendCode['ERROR'];
        }
    } else {
        $sendEmailRequest = [];
        $sendEmailRequest['LOGIN'] = $arUser['LOGIN'];
        $sendEmailRequest['EMAIL'] = $arUser['EMAIL'] ? $arUser['EMAIL'] : $arUser['LOGIN'];
        $sendEmailRequest['CHECKWORD'] = $arUser['CHECKWORD'];
        $sendEmailRequest['USER_ID'] = $arUser['ID'];
        $sendEmailRequest['STATUS'] = $arUser['STATUS'];
        $sendEmailRequest['NAME'] = $arUser['NAME'];
        $sendEmailRequest['LAST_NAME'] = $arUser['LAST_NAME'];
        $sendEmail = AuthHelpers::sendPasswordConfirm($sendEmailRequest); // отправка письма для восстановления пароля
        if ($sendEmail['status'] == 'Y') {
            $ajaxResult['status'] = 'email';
        } else {
            $ajaxResult['message'] = $sendEmail['error'];
        }
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
