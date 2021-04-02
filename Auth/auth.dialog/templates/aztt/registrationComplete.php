<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) {
	die();
}
?>

<div class="dialog-content dialog-informer" data-dialog-content="dialog-registration-complete">
    <div class="dialog-content__title">
        Спасибо за регистрацию!
    </div>
    <div class="dialog-content__data">
        <p>
            Теперь вы можете пользоваться личным кабинетом <br/> и заполнить анкету расширенной регистрации.
        </p>
        <a href="/registration/<?=$arResult['profile_type']?>/primary/" class="btn btn-primary btn-block">Продолжить регистрацию</a>
    </div>
</div>

<?if($arResult['show']) {?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            authDialogWiz.push('dialog-registration-complete');
            authDialogWiz.show();
        });
    </script>
<?}?>