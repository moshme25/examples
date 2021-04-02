<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) {
	die();
}
use AZTT\helpers\UtilityHelpers;

$siteInfo = UtilityHelpers::getSiteInfo();

?>

<div class="dialog-content dialog-registration" data-dialog-content="dialog-register">
	<div class="dialog-content__title">Регистрация</div>
	<div class="dialog-content__link">
		<a href="" data-dialog-push="dialog-login">Войти</a>
	</div>
	<div class="dialog-content__data">
		<form action="" method="post" name="registerForm" data-js-validate="registerForm">
            <input type="hidden" name="auth" value="Y">
            <input type="hidden" name="type" value="register">
			<div class="check-group">
				<label class="check">
					<input type="radio" name="ENTITY" value="provisioner" />
					<span class="checkmark"></span>
					<span class="label">поставщик</span>
				</label>
				<label class="check">
					<input type="radio" name="ENTITY" value="customer" checked />
					<span class="checkmark"></span>
					<span class="label">покупатель</span>
				</label>
			</div>
			<div class="input-group phone-input">
				<label>
					<span>
						<span class="is-email">
							Электронная почта
						</span>
						или
						<span class="is-phone">
							мобильный телефон
						</span>
					</span>
				</label>
				<input type="text" name="LOGIN" required placeholder="Введите ваш e-mail или номер телефона" name="login" />
			</div>
			<div class="input-group">
				<label>Пароль</label>
				<input type="password" placeholder="Введите пароль" name="PASSWORD" />
				<span class="caption">
					Минимальная длина 6 символов, латинские буквы и цифры
				</span>
			</div>
			<button type="submit" class="btn btn-primary btn-block">Зарегистрироваться</button>
			<div class="policies">
                <label class="check">
                    <input type="checkbox" name="policy" value="1" checked />
                    <span class="checkmark"></span>
                    <span class="label">
                        Я соглашаюсь на отправку мне сервисных и информационных сообщений,
                        а также на обработку моих персональных данных в соответствии
                        с <a href="<?=$siteInfo['props']['TERMS_OF_SERVICE']['URL']?>" target="_blank">Пользовательским соглашением</a> и <a href="<?=$siteInfo['props']['PRIVACY_POLICY']['URL']?>" target="_blank">Политикой обработки персональных</a>
                        данных
                    </span>
                </label>
                <label class="check">
                    <input type="checkbox" name="adv-agreement" value="1" checked />
                    <span class="checkmark"></span>
                    <span class="label">
                        Я соглашаюсь на отправку мне рекламных сообщений
                    </span>
                </label>
            </div>
		</form>
	</div>
</div>
