<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
?>

<div class="dialog-content dialog-login" data-dialog-content="dialog-login">
	<div class="dialog-content__title">Войти</div>
	<div class="dialog-content__link">
		<a href="" data-dialog-push="dialog-register">
			Зарегистрироваться
		</a>
	</div>
	<div class="dialog-content__data">
		<form action="" name="authorizeForm" data-js-validate="authorizeForm">
			<input type="hidden" name="auth" value="Y">
			<input type="hidden" name="type" value="authorize">
			<div class="input-group phone-group">
				<label>
					Электронная почта или мобильный телефон
				</label>
				<input type="text" name="USER_LOGIN" required mask-emailphone placeholder="Введите ваш e-mail или номер телефона" />
				<div class="signup-arrow">
					<svg width="92" height="92" viewBox="0 0 92 92" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="92" height="92" fill="transparent" />
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M49.1714 8.48378C49.916 8.42284 50.5844 7.737 50.628 7.00184C50.6645 6.37524 50.2335 5.95242 49.601 6.0043L33.5656 7.31458C33.5656 7.31458 33.5656 7.31458 33.4065 7.34698C33.2413 7.36877 33.1006 7.40713 32.9752 7.46462C32.9494 7.47667 32.9256 7.48658 32.9045 7.49471C32.8959 7.49805 32.8876 7.50112 32.8797 7.50393C32.7814 7.55601 32.6885 7.61738 32.6031 7.68772C32.5845 7.71006 32.5579 7.74048 32.5244 7.77397L32.5154 7.78303L32.5146 7.78378L32.5093 7.7901C32.5035 7.79708 32.4985 7.8033 32.4903 7.81334C32.4872 7.81717 32.4837 7.82155 32.4795 7.82674C32.4664 7.84283 32.4479 7.86553 32.4269 7.89006L32.4202 7.898L32.4132 7.90579C32.3262 8.00354 32.255 8.10654 32.1963 8.21651L32.19 8.22819C32.1708 8.26356 32.1597 8.28416 32.1497 8.30401C32.1403 8.3224 32.1359 8.33265 32.1333 8.33904L32.1287 8.34989C32.0495 8.53799 32.0096 8.69638 32.0019 8.80969C31.9953 8.93457 32.0059 9.05041 32.0315 9.15678L32.0325 9.16064L35.9762 24.6339L35.9768 24.636C36.1065 25.1285 36.6371 25.4809 37.3621 25.2026L37.3634 25.2021C38.1547 24.8996 38.5528 24.0639 38.4015 23.4656L35.2121 10.9517L37.4554 12.5858C43.8825 17.2677 48.5368 24.32 48.8692 24.8344C62.3532 44.3047 59.1634 70.9724 41.5848 84.3305C40.8658 84.8786 40.8027 85.7066 41.0846 86.1223C41.1171 86.1672 41.15 86.2055 41.184 86.2395C41.475 86.5305 42.1208 86.6514 42.7549 86.1697C61.4934 71.9299 65.286 43.6486 51.0595 23.1236L51.0482 23.1072L51.0375 23.0903C50.8436 22.7852 46.4101 15.9406 39.9428 10.9866L37.8819 9.40786L49.1714 8.48378Z"
                            fill="#0045FF"
                        />
                    </svg>
				</div>
			</div>
			<div class="input-group">
				<label>Пароль</label>
				<input type="password" name="USER_PASSWORD" required placeholder="Введите пароль" />
			</div>
			<button type="submit" class="btn btn-primary btn-block">
				Войти
			</button>
			<div class="remember-link">
				<a href="" data-dialog-push="dialog-restore">Забыли пароль?</a>
			</div>
		</form>
	</div>
</div>

<?if($arResult['show']) {?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            authDialogWiz.push('dialog-login');
            authDialogWiz.show();
        });
    </script>
<?}?>