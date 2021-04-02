<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) {
	die();
}
?>

<div class="dialog-content dialog-informer" data-dialog-content="dialog-email-sent">
    <div class="dialog-content__title">
        Ссылка отправлена
    </div>
    <div class="dialog-content__data">
        <p>
            Ссылка для восстановления пароля отправлена на
            ваш <b><?=$arResult['EMAIL']?></b>. Пожалуйста, проверьте почту.
        </p>
        <button type="button" class="btn btn-primary btn-block" data-dialog-close>Продолжить</button>
    </div>
</div>