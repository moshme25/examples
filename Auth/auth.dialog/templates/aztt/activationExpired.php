<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) {
	die();
}
?>

<div class="dialog-content dialog-informer" data-dialog-content="dialog-activation-expired">
    <div class="dialog-content__title">
        Активация просрочена
    </div>
    <div class="dialog-content__data">
        <p>
            Время активации истекло. Чтобы активировать
            профиль отправьте запрос еще раз и проверьте
            почту.
        </p>
        <button type="button" class="btn btn-primary btn-block" data-send-email="<?=$arResult['EMAIL']?>">
            Отправить ссылку еще раз
        </button>
    </div>
</div>

<?if($arResult['show']) {?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            authDialogWiz.push('dialog-activation-expired');
            authDialogWiz.show();
        });
    </script>
<?}?>