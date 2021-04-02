<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

if ($arResult["PHONE_REGISTRATION"]) {
    CJSCore::Init('phone_auth');
}
?>

<div class="dialog-content dialog-newpassword" data-dialog-content="dialog-newpassword">
    <div class="dialog-content__title">Новый пароль</div>
    <div class="dialog-content__data">
        <form action="" method="post" name="newpasswordForm" data-js-validate="newpasswordForm">
            <input type="hidden" name="auth" value="Y">
            <input type="hidden" name="type" value="changepasswd">
            <?if ($arResult["PHONE_REGISTRATION"]) {?>
                <input type="hidden" name="USER_PHONE_NUMBER" value="<?=htmlspecialcharsbx($arResult['USER_PHONE_NUMBER'])?>" />
            <?} else {?>
                <input type="hidden" name="USER_LOGIN" value="<?=$arResult['LAST_LOGIN']?>" />
                <input type="hidden" name="USER_CHECKWORD" value="<?=$arResult['USER_CHECKWORD']?>" />
                <input type="hidden" name="change_password" value="y" />
            <?}?>
            <div class="input-group">
                <label>Придумайте новый пароль</label>
                <input type="password" placeholder="" name="USER_PASSWORD"/>
                <span class="caption">Минимальная длина 6 символов, латинские буквы и цифры</span>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Сохранить пароль</button>
        </form>
    </div>
</div>

<?if($arResult['show']) {?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            authDialogWiz.push('dialog-newpassword');
            authDialogWiz.show();
        });
    </script>
<?}?>