<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

foreach ($arResult as $key => $value) {
    if (!is_array($value)) {
        $arResult[$key] = htmlspecialcharsbx($value);
    }
}

use AZTT\helpers\AuthHelpers;
use AZTT\helpers\UtilityHelpers;

$arRequestParams = array(
    'EMAIL',
    'user_id',
    'user_login',
    'confirm_code',
    'confirm_registration',
    'confirm_by_phone',
    'dialog',
    'show'
);

foreach ($arRequestParams as $param) {
    $arResult[$param] = ($_REQUEST[$param] != '' ? $_REQUEST[$param] : "");
    $arResult[$param] = htmlspecialcharsbx($arResult[$param]);
}

$arResult['EMAIL'] = $_REQUEST['email'];

if ($arResult['confirm_registration'] == 'y' && $arResult['user_id']) { // подтверждение регистрации по email
    $confirmResult = AuthHelpers::confirmFromEmail($arResult['user_id'], $arResult['confirm_code']);
    switch ($confirmResult['status']) {
        case 'Y':
            CUser::SetUserGroup($arResult['user_id'], [2, USER_GROUP_INITIAL_REGISTRATION_ID]); // пользователь получает статус "Прошел первичную регистрацию"
            $USER->Authorize($arResult['user_id']); 
            $profile = UtilityHelpers::getUserExtendedInfo();
            $arResult['profile_type'] = $profile->type ? $profile->type : 'customer';
            $arResult['show'] = true;
            $arResult['dialog'] = 'registrationComplete';
            break;
        case 'expired':
            $arResult['show'] = true;
            $arResult['EMAIL'] = $confirmResult['data']['email'];
            $arResult['dialog'] = 'activationExpired';
            break;
        case 'error':
            $error = $confirmResult['error'];
            echo "Ошибка: ".$error;
            // диалог ошибки
            break;
    }
} elseif($arResult['confirm_by_phone'] == 'y' && $arResult['user_login']) { // подтверждение регистрации по коду из СМС
    $rsUser = CUser::GetByLogin($arResult['user_login']);
    $arUser = $rsUser->Fetch();
    if ($arUser) {
        CUser::SetUserGroup($arUser['ID'], [2, USER_GROUP_INITIAL_REGISTRATION_ID]); // пользователь получает статус "Прошел первичную регистрацию"
        $USER->Authorize($arUser['ID']); 
        $profile = UtilityHelpers::getUserExtendedInfo();
        $arResult['profile_type'] = $profile->type ? $profile->type : 'customer';
        $arResult['show'] = true;
        $arResult['dialog'] = 'registrationComplete';
    }
}

if($arResult['dialog']) {
    if ($this->InitComponentTemplate($arResult['dialog'])) {
        $this->ShowComponentTemplate();
    } 
}


