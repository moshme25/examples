<?

namespace AZTT\helpers;

use AZTT\helpers\IBlockHelpers;
use AZTT\helpers\SmsHelpers;
use AZTT\helpers\UtilityHelpers;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Mail\Event;

/**
 * Класс функций по работе с авторизацией
 */
class AuthHelpers
{
    const EMAIL_CODE_LIFE_TIME = 60 * 60 * 48; //время действия кода подтверждения регистрации

    /**
     * Предварительные проверки перед выполнением основных функций
     *
     * @return array [STATUS, ERROR]
     */
    private static function prepareAction()
    {
        $result['status'] = true;
        $result['error'] = '';

        global $USER;
        if (!$USER->IsAuthorized()) {
            $result['status'] = false;
            $result['error'] = Loc::getMessage("system_unauthorized");
        }

        return $result;
    }

    public static function changePassword($LOGIN, $CHECKWORD, $PASSWORD, $CONFIRM_PASSWORD, $SITE_ID = false, $captcha_word = "", $captcha_sid = 0, $authActions = true, $phoneNumber = "")
    {
        /** @global CMain $APPLICATION */
        global $DB, $APPLICATION;

        $arParams = array(
            "LOGIN" => &$LOGIN,
            "CHECKWORD" => &$CHECKWORD,
            "PASSWORD" => &$PASSWORD,
            "CONFIRM_PASSWORD" => &$CONFIRM_PASSWORD,
            "SITE_ID" => &$SITE_ID,
            "PHONE_NUMBER" => &$phoneNumber,
        );

        $APPLICATION->ResetException();
        foreach (GetModuleEvents("main", "OnBeforeUserChangePassword", true) as $arEvent) {
            if (ExecuteModuleEventEx($arEvent, array(&$arParams)) === false) {
                if ($err = $APPLICATION->GetException()) {
                    return array("MESSAGE" => $err->GetString() . "", "TYPE" => "ERROR");
                }
                return array("MESSAGE" => GetMessage("main_change_pass_error") . "", "TYPE" => "ERROR");
            }
        }

        if (\Bitrix\Main\Config\Option::get("main", "captcha_restoring_password", "N") == "Y") {
            if (!($APPLICATION->CaptchaCheckCode($captcha_word, $captcha_sid))) {
                return array("MESSAGE" => GetMessage("main_user_captcha_error") . "", "TYPE" => "ERROR");
            }
        }

        $phoneAuth = ($arParams["PHONE_NUMBER"] != '' && \Bitrix\Main\Config\Option::get("main", "new_user_phone_auth", "N") == "Y");

        $strAuthError = "";
        if (strlen($arParams["LOGIN"]) < 3 && !$phoneAuth) {
            $strAuthError .= GetMessage('MIN_LOGIN') . "";
        }
        if ($arParams["PASSWORD"] != $arParams["CONFIRM_PASSWORD"]) {
            $strAuthError .= GetMessage('WRONG_CONFIRMATION') . "";
        }
        if ($strAuthError != '') {
            return array("MESSAGE" => $strAuthError, "TYPE" => "ERROR");
        }

        $updateFields = array(
            "PASSWORD" => $arParams["PASSWORD"],
        );

        $res = [];
        if ($phoneAuth) {
            $verifyStatus = SmsHelpers::verifyPhoneCode($arParams["PHONE_NUMBER"], $arParams['CHECKWORD']);
            if ($verifyStatus['STATUS']) {
                $userId = $verifyStatus['USER_ID'];
            }

            if (!$userId) {
                return array("MESSAGE" => GetMessage("main_change_pass_code_error"), "TYPE" => "ERROR");
            }

            //activate user after phone number confirmation
            $updateFields["ACTIVE"] = "Y";
        } else {
            \CTimeZone::Disable();
            $db_check = $DB->Query(
                "SELECT ID, LID, CHECKWORD, " . $DB->DateToCharFunction("CHECKWORD_TIME", "FULL") . " as CHECKWORD_TIME " .
                "FROM b_user " .
                "WHERE LOGIN='" . $DB->ForSql($arParams["LOGIN"], 0) . "'" . (
                    // $arParams["EXTERNAL_AUTH_ID"] can be changed in the OnBeforeUserChangePassword event
                    $arParams["EXTERNAL_AUTH_ID"] != '' ?
                    "    AND EXTERNAL_AUTH_ID='" . $DB->ForSQL($arParams["EXTERNAL_AUTH_ID"]) . "' " : "    AND (EXTERNAL_AUTH_ID IS NULL OR EXTERNAL_AUTH_ID='') ")
            );
            \CTimeZone::Enable();
            if (!($res = $db_check->Fetch())) {
                return array("MESSAGE" => preg_replace("/#LOGIN#/i", htmlspecialcharsbx($arParams["LOGIN"]), GetMessage('LOGIN_NOT_FOUND')), "TYPE" => "ERROR", "FIELD" => "LOGIN");
            }
            $userId = $res["ID"];
        }

        $arPolicy = \CUser::GetGroupPolicy($userId);

        $passwordErrors = \CUser::CheckPasswordAgainstPolicy($arParams["PASSWORD"], $arPolicy);
        if (!empty($passwordErrors)) {
            return array(
                "MESSAGE" => implode("", $passwordErrors) . "",
                "TYPE" => "ERROR",
            );
        }

        if (!$phoneAuth) {
            if ($res["CHECKWORD"] == '' || $res["CHECKWORD"] != $arParams["CHECKWORD"]) {
                return array("MESSAGE" => preg_replace("/#LOGIN#/i", htmlspecialcharsbx($arParams["LOGIN"]), GetMessage("CHECKWORD_INCORRECT")) . "", "TYPE" => "ERROR", "FIELD" => "CHECKWORD");
            }

            // $site_format = CSite::GetDateFormat();
            // if (time() - $arPolicy["CHECKWORD_TIMEOUT"] * 60 > MakeTimeStamp($res["CHECKWORD_TIME"], $site_format)) {
            //     return array("MESSAGE" => preg_replace("/#LOGIN#/i", htmlspecialcharsbx($arParams["LOGIN"]), GetMessage("CHECKWORD_EXPIRE")) . "", "TYPE" => "ERROR", "FIELD" => "CHECKWORD_EXPIRE");
            // }

            if ($arParams["SITE_ID"] === false) {
                if (defined("ADMIN_SECTION") && ADMIN_SECTION === true) {
                    $arParams["SITE_ID"] = CSite::GetDefSite($res["LID"]);
                } else {
                    $arParams["SITE_ID"] = SITE_ID;
                }
            }
        }

        // change the password
        $obUser = new \CUser;
        $res = $obUser->Update($userId, $updateFields, $authActions);
        if (!$res && $obUser->LAST_ERROR != '') {
            return array("MESSAGE" => $obUser->LAST_ERROR . "", "TYPE" => "ERROR");
        }

        if ($phoneAuth) {
            return array("MESSAGE" => GetMessage("main_change_pass_changed") . "", "TYPE" => "OK");
        } else {
            \CUser::SendUserInfo($userId, $arParams["SITE_ID"], GetMessage('CHANGE_PASS_SUCC'), true, 'USER_PASS_CHANGED');
            return array("MESSAGE" => GetMessage('PASSWORD_CHANGE_OK') . "", "TYPE" => "OK");
        }
    }

    /**
     * Производит активацию пользователя по ссылке из email
     *
     * @param string $userId
     * @param string $code
     *
     * @return array [STATUS, ERROR, EMAIL]
     */
    public static function confirmFromEmail($userId = 0, $code = '')
    {
        global $USER;
        $result['data'] = [];
        $result['error'] = '';
        $result['status'] = '';

        $userRes = \Bitrix\Main\UserTable::getList(["filter" => ['=ID' => $userId]]);
        if ($user = $userRes->fetch()) {
            if ($user['ACTIVE'] == 'Y') {
                $result['status'] = 'Y';
                return $result;
            }

            if ($user['CONFIRM_CODE'] == $code) {
                $currentDateTime = new \Bitrix\Main\Type\DateTime();
                if (($currentDateTime->getTimestamp() - $user['DATE_REGISTER']->getTimestamp()) >= self::EMAIL_CODE_LIFE_TIME) { // истек срок действия кода
                    $result['status'] = 'expired';
                    $result['data']['email'] = $user['EMAIL'];
                    $result['error'] = Loc::getMessage("main_err_confirm_code_unactive");
                } else {
                    $obUser = new \CUser;
                    $updateUser = $obUser->Update($userId, array('ACTIVE' => 'Y'));
                    if (!$updateUser) {
                        $result['status'] = 'error';
                        $result['error'] = $updateUser->LAST_ERROR;
                    } else {
                        $result['status'] = 'Y';
                        $USER->Authorize($userId);
                    }
                }
            } else {
                $result['status'] = 'error';
                $result['error'] = Loc::getMessage('main_err_confirm_code_match');
            }
        } else {
            $result['status'] = 'error';
            $result['error'] = Loc::getMessage('main_register_no_user');
        }

        return $result;
    }

    /**
     * Вызывает нужную сервисную функцию
     *
     * @param string $service
     * @param array $params
     *
     * @return string [json]
     */
    public static function service($service = '', $params = [])
    {
        $result['data'] = '';
        $result['error'] = '';
        $result['status'] = '';

        if (method_exists(__CLASS__, $service)) {
            $result = self::{$service}($params);
        } else {
            $result['status'] = 'error';
            $result['error'] = 'Invalid Method';
        }

        return json_encode($result);
    }

    /**
     * Отправляет письмо с ссылкой подтверждения регистрации
     *
     * @param array $params
     *
     * @return array
     */
    public static function sendRegisterConfirm($params = [])
    {
        $result['data'] = '';
        $result['error'] = '';
        $result['status'] = '';
        $arEventFields = [];

        if (!$params['email']) {
            $result['status'] = 'error';
            $result['error'] = Loc::getMessage('main_register_no_user');
            return $result;
        }

        $userRes = \Bitrix\Main\UserTable::getList(["filter" => ['=EMAIL' => $params['email']]]);
        if ($user = $userRes->fetch()) {
            $arEventFields['USER_ID'] = $user['ID'];
            $arEventFields['LOGIN'] = $user["LOGIN"];
            $arEventFields['EMAIL'] = $user["LOGIN"];
            $arEventFields['CONFIRM_CODE'] = randString(8);
            $arEventFields['LID'] = SITE_ID;
            $arEventFields['LANGUAGE_ID'] = LANGUAGE_ID;
            $obUser = new \CUser;
            $updateUser = $obUser->Update($user['ID'], ['ACTIVE' => 'N', 'CONFIRM_CODE' => $arEventFields['CONFIRM_CODE']]);
            if (!$updateUser) {
                $result['status'] = 'error';
                $result['error'] = $updateUser->LAST_ERROR;
            } else {
                Event::send(array(
                    "EVENT_NAME" => "NEW_USER_CONFIRM",
                    "LID" => SITE_ID,
                    "C_FIELDS" => $arEventFields,
                ));
                $result['status'] = 'Y';
            }
        } else {
            $result['status'] = 'error';
            $result['error'] = Loc::getMessage('main_register_no_user');
        }

        return $result;
    }

    /**
     * Отправляет письмо с ссылкой на смену пароля
     *
     * @param array $params
     *
     * @return array
     */
    public static function sendPasswordConfirm($params = [])
    {
        $result['data'] = '';
        $result['error'] = '';
        $result['status'] = 'Y';
        $arEventFields = [];

        if (!($params['LOGIN'] && $params['CHECKWORD'])) {
            $result['status'] = 'error';
            $result['error'] = Loc::getMessage('main_register_no_user');
            return $result;
        }

        $arEventFields['USER_ID'] = $params['USER_ID'];
        $arEventFields['LOGIN'] = $params['LOGIN'];
        $arEventFields['CHECKWORD'] = $params["CHECKWORD"];
        $arEventFields['EMAIL'] = $params["EMAIL"] ? $params["EMAIL"] : $params["LOGIN"];
        $arEventFields['STATUS'] = $params["STATUS"];
        $arEventFields['URL_LOGIN'] = urlencode($params["LOGIN"]);
        $arEventFields['NAME'] = $params['NAME'];
        $arEventFields['LAST_NAME'] = $params['LAST_NAME'];
        Event::send(array(
            "EVENT_NAME" => "USER_PASS_REQUEST",
            "LID" => SITE_ID,
            "C_FIELDS" => $arEventFields,
        ));

        return $result;
    }

    /**
     * Деактивирует пользователя и его профиль, оповещает об этом администратора
     *
     * @param array $params
     *
     * @return array
     */
    public static function deleteProfile()
    {
        $result['data'] = '';
        $result['error'] = '';
        $result['status'] = '';
        $arEventFields = [];

        $prepareAction = self::prepareAction();
        if (!$prepareAction['status']) {
            $result['error'] = $prepareAction['error'];
            return $result;
        }

        $profile = UtilityHelpers::getUserExtendedInfo();

        //деактивация профиля
        if ($profile->type) {
            $deactivateProfileResult = IBlockHelpers::update($profile->id, ['FIELDS' => ['ACTIVE' => 'N']]);
            if (!$deactivateProfileResult['STATUS']) {
                $result['error'] = $deactivateProfileResult['ERROR'];
                return $result;
            }
        }

        if (!$profile->userID) {
            global $USER;
            $profile->userID = $USER->GetID();
        }

        //деактивация пользователя
        $obUser = new \CUser;

        $updateUser = $obUser->Update($profile->userID, ['ACTIVE' => 'N']);
        if (!$updateUser) {
            $result['status'] = 'error';
            $result['error'] = $updateUser->LAST_ERROR;
        } else {
            $obUser->Logout();

            $arEventFieldsDeleteUser = [];
            $arEventFieldsDeleteUser['EMAIL'] = $profile->email;

            $resultUserMessage = Event::send([
                "EVENT_NAME" => "USER_PROFILE_DEACTIVATE_FOR_USER",
                "LID" => SITE_ID,
                "C_FIELDS" => $arEventFieldsDeleteUser,
            ]);

            if (!$resultUserMessage) {
                $result['error'] = 'Сообщение пользователю не отправлено';
            } else {
                $arEventFields['ID'] = $profile->userID;
                $arEventFields['NAME'] = $profile->userLogin;
                $arEventFields['PROFILE_ID'] = $profile->id;
    
                $resultAdminMessage = Event::send([
                    "EVENT_NAME" => "USER_PROFILE_DEACTIVATE",
                    "LID" => SITE_ID,
                    "C_FIELDS" => $arEventFields,
                ]); 

                if (!$resultAdminMessage) {
                    $result['error'] = 'Сообщение администратору не отправлено';
                } else {
                    $result['status'] = 'Y';
                }
            }
        }

        return $result;
    }
}
