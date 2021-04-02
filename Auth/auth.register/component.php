<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Bitrix vars
 * @global CMain $APPLICATION
 * @global CUser $USER
 * @global CDatabase $DB
 * @global CUserTypeManager $USER_FIELD_MANAGER
 * @param array $arParams
 * @param array $arResult
 * @param CBitrixComponent $this
 */

foreach ($arResult as $key => $value) {
    if (!is_array($value)) {
        $arResult[$key] = htmlspecialcharsbx($value);
    }
}

use AZTT\helpers\SmsHelpers;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\Localization\Loc;

$arRequestParams = array(
    "ENTITY",
    "PASSWORD",
    "LOGIN",
);

foreach ($arRequestParams as $param) {
    $arResult[$param] = ($_REQUEST[$param] != '' ? $_REQUEST[$param] : "");
    $arResult[$param] = htmlspecialcharsbx($arResult[$param]);
}

$arResult["VALUES"] = [];
$arResult["ERROR"] = '';
$ajaxResult['status'] = 'N';
$ajaxResult['message'] = '';
$ajaxResult['field'] = 'LOGIN';

if (!strpos($arResult['LOGIN'], '@')) {
    $arResult['VALUES']['PHONE_NUMBER'] = $arResult['LOGIN'];
    $arResult['VALUES']['PERSONAL_PHONE'] = $arResult['LOGIN'];
    $arResult['PHONE_REGISTRATION'] = true;
} else {
    $arResult['VALUES']['EMAIL'] = $arResult['LOGIN'];
}

if ($arResult['LOGIN'] && $arResult['PASSWORD'] && !$USER->IsAuthorized()) {
    $arResult['VALUES']["LOGIN"] = $arResult["LOGIN"];
    $arResult['VALUES']["PASSWORD"] = $arResult["PASSWORD"];
    $arResult['VALUES']["CHECKWORD"] = md5(CMain::GetServerUniqID() . uniqid());
    $arResult['VALUES']["~CHECKWORD_TIME"] = $DB->CurrentTimeFunction();
    $arResult['VALUES']["ACTIVE"] = 'N';
    $arResult['VALUES']["CONFIRM_CODE"] = randString(8);
    $arResult['VALUES']["LID"] = SITE_ID;
    $arResult['VALUES']["LANGUAGE_ID"] = LANGUAGE_ID;
    $arResult['VALUES']["UF_TYPE"] = $arResult['ENTITY'] == 'provisioner' ? USER_PROP_TYPE_PROVISIONER : USER_PROP_TYPE_CUSTOMER;

    $arResult['VALUES']["USER_IP"] = $_SERVER["REMOTE_ADDR"];
    $arResult['VALUES']["USER_HOST"] = @gethostbyaddr($_SERVER["REMOTE_ADDR"]);
    $arResult["VALUES"]["AUTO_TIME_ZONE"] = "";

    $rsUser = CUser::GetByLogin($arResult["LOGIN"]);
    $arUser = $rsUser->Fetch();
    if ($arUser) { // если пользователь с таким логином уже существует
        $arResult['ERROR'] = Loc::getMessage('main_register_user_exist').'<div class="remember-link">
                                                                             <a href="" data-dialog-push="dialog-restore">Забыли пароль?</a>
                                                                          </div>';
    } else { // создание нового пользователя
        $bOk = true;
        $events = GetModuleEvents("main", "OnBeforeUserRegister", true);
        foreach ($events as $arEvent) {
            if (ExecuteModuleEventEx($arEvent, array(&$arResult['VALUES'])) === false) {
                if ($err = $APPLICATION->GetException()) {
                    $arResult['ERROR'] .= ' ' . $err->GetString();
                }
                $bOk = false;
                break;
            }
        }

        $ID = 0;
        $user = new CUser();
        if ($bOk) {
            $ID = $user->Add($arResult["VALUES"]);
        }
        if (intval($ID) > 0) {
            if ($arResult['PHONE_REGISTRATION'] && $arResult['VALUES']['PHONE_NUMBER']) { // регистрация по номеру телефона
                $smsParams['phoneNumber'] = $arResult['VALUES']['PHONE_NUMBER'];
                
                $sendCode = SmsHelpers::sendCodeAction($smsParams);
                
                if ($sendCode['STATUS']) {
                    $ajaxResult['status'] = 'phone';
                } else {
                    $arResult["ERROR"] = $sendCode['ERROR'];
                }
            } else { // регистрация по почте
                $arResult['VALUES']["USER_ID"] = $ID;
                $arEventFields = $arResult['VALUES'];
                unset($arEventFields["PASSWORD"]);
                unset($arEventFields["CONFIRM_PASSWORD"]);

                Event::send(array(
                    "EVENT_NAME" => "NEW_USER_CONFIRM",
                    "LID" => SITE_ID,
                    "C_FIELDS" => $arEventFields,
                ));

                Event::send(array(
                    "EVENT_NAME" => "NEW_USER",
                    "LID" => SITE_ID,
                    "C_FIELDS" => $arEventFields,
                ));

                $ajaxResult['status'] = 'email';
            }
        } else {
            $arResult["ERROR"] .= ' '.$user->LAST_ERROR;
        }
    }
    $events = GetModuleEvents('main', 'OnAfterUserRegister', true);
    foreach ($events as $arEvent) {
        ExecuteModuleEventEx($arEvent, array(&$arResult['VALUES']));
    }
} else {
    $arResult['ERROR'] = 'Вы уже авторизованы';
}

$ajaxResult['message'] = $arResult['ERROR'];

if ($_REQUEST['auth'] == 'Y' && $_REQUEST['type']) {
    $APPLICATION->RestartBuffer();
    echo json_encode($ajaxResult);
    die();
}

$this->IncludeComponentTemplate();
