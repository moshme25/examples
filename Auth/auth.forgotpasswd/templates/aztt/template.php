<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
?>

<div class="dialog-content dialog-restore" data-dialog-content="dialog-restore">
    <div class="dialog-content__title">
        Восстановить пароль
    </div>
    <div class="dialog-content__data">
        <form action="" name="forgotpasswdForm" data-js-validate="forgotpasswdForm">
            <input type="hidden" name="auth" value="Y">
            <input type="hidden" name="type" value="forgotpasswd">
            <div class="input-group phone-input">
                <label>
                    <span>
                        <span class="is-email">Электронная почта</span>
                        или
                        <span class="is-phone">мобильный телефон</span>
                    </span>
                </label>
                <input type="text" name="USER_PHONE_OR_EMAIL" required placeholder="Введите ваш e-mail или номер телефона" />
            </div>
            <p>
                Укажите адрес электронной почты или телефон,
                который вы использовали при регистрации
                своего профиля. Мы вышлем вам ссылку для
                восстановления пароля на почту или код
                восстановления в SMS
            </p>
            <button type="submit" class="btn btn-primary btn-block">
                Восстановить пароль
            </button>
            <div class="remember-link">
                <a href="" data-dialog-push="dialog-login">Вспомнили пароль?</a>
            </div>
        </form>
    </div>
</div>