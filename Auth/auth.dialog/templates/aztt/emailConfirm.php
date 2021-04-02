<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) {
	die();
}
?>

<div class="dialog-content dialog-informer" data-dialog-content="dialog-email-confirm">
    <div class="dialog-content__title">
        Подтвердите адрес
    </div>
    <div class="dialog-content__data">
        <p>
            Письмо с подтверждением было выслано </br>
            на <b><?=$arResult['EMAIL']?></b>, нажмите на ссылку в
            письме, чтобы подтвердить свою электронную почту.
        </p>
        <a href="/" class="btn btn-primary btn-block">Вернуться на главную</a>
    </div>
</div>