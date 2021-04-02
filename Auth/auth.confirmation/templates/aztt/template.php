<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
?>

<div class="dialog-content dialog-confirmation" data-dialog-content="dialog-confirmation">
    <div class="dialog-content__title">Введите код</div>
    <div class="dialog-content__data">
        <form action="" name="confirmationForm" class="conf-container" data-js-validate="confirmationForm">
            <input type="hidden" name="auth" value="Y">
            <input type="hidden" name="type" value="confirmation">
            <input type="hidden" name="LOGIN" value="<?=$arResult['LOGIN']?>" />
            <input type="hidden" name="CONFIRM_CODE" value="<?=$arResult['CONFIRM_CODE']?>" />
            <div class="conf-container__phone">
                <span>
                    Мы отправили код на
                    <b id="conf-phone-value"><?=$arResult["USER_PHONE_NUMBER"]?></b>
                </span>
                <a href="" data-dialog-push="dialog-register">Изменить номер</a>
            </div>
            <div class="conf-container__code">
                <div class="code-input" data-code>
                    <input type="text"/>
                    <input type="text"/>
                    <input type="text"/>
                    <input type="text"/>
                    <input type="text"/>
                </div>
            </div>
            <div data-js-timer="sms">
                <span class="conf-container__timer" data-js-timer-pending>
                    Получить новый код можно через
                    <span data-js-timer-sec></span> сек
                </span>
                <span class="conf-container__send" data-js-timer-ended>
                    <button class="btn btn-outline btn-outline-secondary btn-block" type="button">
                        Отправить новый код
                    </button>
                </span>
            </div>
            <a href="" data-dialog-push="dialog-register">Зарегистрироваться по почте</a>
        </form>
    </div>
</div>