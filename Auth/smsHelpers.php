<?
namespace AZTT\helpers;

use \Bitrix\Main\Diag\Debug;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\UserPhoneAuthTable;

/**
 * Класс функций по работе с SMS
 */
class SmsHelpers
{
    const PHONE_CODE_RESEND_INTERVAL = 60; //минимальный интервал обновления кода верификации
    const PHONE_CODE_LIFE_TIME = 300; //время действия кода подтверждения

    /**
     * Проверяет код из SMS пользователя по телефону
     *
     * @param string $phoneNumber
     * @param string $code
     * @return array [STATUS, ERROR, USER_ID]
     */
    public static function verifyPhoneCode($phoneNumber = '', $code = '')
    {
        $result['STATUS'] = false;
        $result['ERROR'] = '';
        $result['USER_ID'] = '';

        if (!preg_match('/^[0-9]{5}$/', $code)) {
            $result['ERROR'] = Loc::getMessage('main_err_confirm_code_format');
            return $result;
        }

        $phoneNumber = UserPhoneAuthTable::normalizePhoneNumber($phoneNumber);
        $userPhone = UserPhoneAuthTable::getList(["filter" => ["=PHONE_NUMBER" => $phoneNumber]])->fetch();

        if (!$userPhone) {
            $result['ERROR'] = Loc::getMessage("main_register_no_user");
            return $result;
        }

        if ($userPhone["CONFIRMED"] != "Y") {
            if ($userPhone["OTP_SECRET"] == $code) {
                //код активен только 5 минут
                if ($userPhone['DATE_SENT']) {
                    $currentDateTime = new \Bitrix\Main\Type\DateTime();
                    if (($currentDateTime->getTimestamp() - $userPhone['DATE_SENT']->getTimestamp()) >= self::PHONE_CODE_LIFE_TIME) {
                        $result['ERROR'] = Loc::getMessage("main_err_confirm_code_unactive");
                        return $result;
                    }
                }
                $data["CONFIRMED"] = "Y";
                $data['DATE_SENT'] = new \Bitrix\Main\Type\DateTime();
                UserPhoneAuthTable::update($userPhone["USER_ID"], $data);
            } else {
                $data["ATTEMPTS"] = (int) $userPhone["ATTEMPTS"] + 1;
                UserPhoneAuthTable::update($userPhone["USER_ID"], $data);
                $result['ERROR'] = Loc::getMessage("main_err_confirm_code_match");
                return $result;
            }
        }

        $result['USER_ID'] = $userPhone["USER_ID"];
        $result['STATUS'] = true;
        return $result;
    }

    /**
     * Отправляет код верификации в SMS пользователю по телефону
     *
     * @param array $params
     * @return array [STATUS, ERROR]
     */
    public static function sendCodeAction($params)
    {
        $result['STATUS'] = false;
        $result['ERROR'] = '';
        
        if ($params["phoneNumber"] == '') {
            $result['ERROR'] = Loc::getMessage("main_register_incorrect_request");
            return $result;
        }
        if ($params["smsTemplate"] == '') {
            $params["smsTemplate"] = 'SMS_USER_CONFIRM_NUMBER';
        }
        
        $phoneNumber = UserPhoneAuthTable::normalizePhoneNumber($params["phoneNumber"]);
        $userPhone = UserPhoneAuthTable::getList(["filter" => ["=PHONE_NUMBER" => $phoneNumber]])->fetchObject();
        if (!$userPhone) {
            $result['ERROR'] = Loc::getMessage("main_register_no_user");
            return $result;
        }
        //alowed only once in a minute
        if ($userPhone['DATE_SENT']) {
            $currentDateTime = new \Bitrix\Main\Type\DateTime();
            if (($currentDateTime->getTimestamp() - $userPhone['DATE_SENT']->getTimestamp()) < self::PHONE_CODE_RESEND_INTERVAL) {
                $result['ERROR'] = Loc::getMessage("main_register_timeout");
                return $result;
            }
        }
        $generateResult = self::GeneratePhoneCode($userPhone->getUserId());
        
        $sms = new \Bitrix\Main\Sms\Event(
            $params["smsTemplate"],
            [
                "DEFAULT_SENDER" => "79259283615",
                "USER_PHONE" => $generateResult['PHONE_NUMBER'],
                "CODE" => $generateResult['CODE'],
            ]
        );
        
        $smsResult = $sms->send(true);
        if (!$smsResult->isSuccess()) {
            $smsLogData['date'] = new \Bitrix\Main\Type\DateTime();
            $smsLogData['error'] = $smsResult->getErrors()[0]->getMessage();
            Debug::writeToFile($smsLogData, $params["smsTemplate"], SMS_LOG_FILENAME);
            $result['ERROR'] = $smsResult->getErrors()[0]->getMessage();
            return $result;
        }

        $result['STATUS'] = true;
        return $result;
    }

    /**
     * Генерирует 5-ти значный код верификации для пользователя
     *
     * @param integer $userId
     * @return array [STATUS, ERROR]
     */
    public static function generatePhoneCode($userId)
    {
        $result = [];
        $row = UserPhoneAuthTable::getRowById($userId);
        if ($row) {
            $result['CODE'] = random_int(10000, 99999);
            $result['PHONE_NUMBER'] = $row["PHONE_NUMBER"];

            UserPhoneAuthTable::update($userId, array(
                "CONFIRMED" => 'N',
                "ATTEMPTS" => 0,
                "OTP_SECRET" => $result['CODE'],
                "DATE_SENT" => new \Bitrix\Main\Type\DateTime(),
            ));
        }
        return $result;
    }
}
