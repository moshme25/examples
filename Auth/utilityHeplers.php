<?

namespace AZTT\helpers;

use AZTT\Entities\Profile;
use \Bitrix\Iblock\ElementTable;
use \Bitrix\Main\Loader;

Loader::includeModule('iblock');

/**
 * Класс общих сервисных функций
 */
class UtilityHelpers
{

    /**
     * Получение настроек сайта
     *
     * @return array
     */
    public static function getSiteInfo()
    {
        $result = [];

        $cache_id = IBLOCK_SETTINGS_ID . '_' . ELEMENT_SETTINGS_ID;
        $cache_dir = "/tagged";
        $obCache = new \CPHPCache;

        if ($obCache->InitCache(604800, $cache_id, $cache_dir)) {
            $result = $obCache->GetVars();
        } elseif ($obCache->StartDataCache()) {
            global $CACHE_MANAGER;
            $CACHE_MANAGER->StartTagCache($cache_dir);

            $getListArray['order'] = ['ID' => 'ASC'];
            $getListArray['filter'] = ['IBLOCK_ID' => IBLOCK_SETTINGS_ID, '=ID' => ELEMENT_SETTINGS_ID, 'ACTIVE' => 'Y'];
            $getListArray['select'] = ['ID', 'NAME', 'IBLOCK_ID'];

            $rs = ElementTable::getList($getListArray);
            if ($arItem = $rs->fetch()) {
                $result['fields'] = $arItem;
                $dbProperty = \CIBlockElement::getProperty($arItem['IBLOCK_ID'], $arItem['ID'], [], []);
                while ($arProperty = $dbProperty->fetch()) {
                    if ($arProperty['PROPERTY_TYPE'] == 'F') {
                        $file = \CFile::GetByID($arProperty['VALUE'])->fetch();
                        $fileType = explode('/', $file['CONTENT_TYPE']);
                        $result['props'][$arProperty['CODE']]['TYPE'] = (count($fileType) == 2) ? $fileType[1] : $file['CONTENT_TYPE'];
                        $result['props'][$arProperty['CODE']]['URL'] = \CFile::GetPath($arProperty['VALUE']);
                    } else {
                        $result['props'][$arProperty['CODE']] = $arProperty['VALUE'];
                    }
                }
                $CACHE_MANAGER->RegisterTag('iblock_' . IBLOCK_SETTINGS_ID . '_element_' . $arItem['ID']);
                $CACHE_MANAGER->EndTagCache();
            }

            $obCache->EndDataCache($result);
        }

        return $result;
    }

    /**
     * Получение данных покупателя/поставщика
     *
     * @return Profile
     */
    public static function getUserExtendedInfo($id = 0)
    {
        $profile = new Profile();
        $class_vars = get_class_vars(get_class($profile));

        global $USER, $_SESSION;

        if (!$id) {
            //TODO: временно отключено т.к. сбоит. как вариант можно переделать получение анкет через свойство привязки к пользователю
            // foreach ($class_vars as $name => $value) {
            //     if ($_SESSION['profile_' . $name]) {
            //         $profile->{$name} = $_SESSION['profile_' . $name];
            //     }
            // }
            $userID = $USER->getId();
        } else {
            $userID = $id;
        }

        
        if (!$userID) {
            return $profile;
        }

        if ($profile->userID != $userID) {
            $profile->userID = $userID;
            $profile->email = $USER->GetEmail();
            $profile->phone = $USER->GetParam("PERSONAL_PHONE");
            $profile->name = $USER->GetLogin();
            $profile->userLogin = $profile->name;
            $rsUser = \CUser::GetByID($userID);
            if ($user = $rsUser->fetch()) {

                if ($user['UF_TYPE'] == USER_PROP_TYPE_CUSTOMER) {
                    $profile->type = 'customer';
                    $iblockID = IBLOCK_CUSTOMER_ID;
                } elseif ($user['UF_TYPE'] == USER_PROP_TYPE_PROVISIONER) {
                    $profile->type = 'provisioner';
                    $iblockID = IBLOCK_PROVISIONER_ID;
                }
            }

            if ($iblockID) {
                $getListArray['order'] = ['ID' => 'ASC'];
                $getListArray['filter'] = ['IBLOCK_ID' => $iblockID, '=PROPERTY_USER' => $userID, 'ACTIVE' => 'Y'];
                $getListArray['select'] = ['ID', 'NAME', 'IBLOCK_ID', 'PREVIEW_PICTURE', 'PROPERTY_USER', 'PROPERTY_LOGO'];

                $rs = \CIBlockElement::GetList($getListArray['order'], $getListArray['filter'], false, false, $getListArray['select']);
                if ($arItem = $rs->fetch()) {
                    $profile->id = $arItem['ID'];
                    $profile->name = $arItem['NAME'];
                    $profile->logo = \CFile::GetPath($arItem['PROPERTY_LOGO_VALUE']);
                }

                foreach ($class_vars as $name => $value) {
                    $_SESSION['profile_' . $name] = $profile->{$name};
                }
            }
        }

        return $profile;
    }

    /**
     * Функция возврата окончания слова с числом
     *
     * @param  mixed $number
     * @param  mixed $after
     *
     * @return string
     */
    public static function getRightWordEnding($number, $after)
    {
        $cases = [2, 0, 1, 1, 1, 2];
        return $number . ' ' . $after[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
    }
    
    /**
     * Функция возврата окончания слова без числа
     *
     * @param  mixed $number
     * @param  mixed $after
     *
     * @return string
     */
    public static function getRightWordEndingWord($number, $after)
    {
        $cases = [2, 0, 1, 1, 1, 2];
        return $after[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
    }

    /**
     * Функция перевода первой буквы слова в верхний регистр
     *
     * @param  string $str
     * @param  string $encoding
     * @return string
     */
    public static function uppercaseFirstLetter($str = '', $encoding = 'UTF-8')
    {
        if (!$str) {
            return '';
        }

        $str = mb_ereg_replace('^[\ ]+', '', $str);
        $str = mb_strtoupper(mb_substr($str, 0, 1, $encoding), $encoding) . mb_substr($str, 1, mb_strlen($str), $encoding);
        
        return $str;
    }
}
