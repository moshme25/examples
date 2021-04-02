<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) {
	die();
}
?>

<div class="dialog-content dialog-informer" data-dialog-content="dialog-welcome">
    <div class="dialog-content__title">
        Добро пожаловать в маркетплейс!
    </div>
    <div class="dialog-content__data">
        <p>
            Теперь вы можете видеть акции, получать
            специальные предложения, сравнивать поставщиков
            и их цены.
        </p>
        <a href="/catalog/" class="btn btn-primary btn-block">Перейти в каталог</a>
    </div>
</div>

<?if($arResult['show']) {?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            authDialogWiz.push('dialog-welcome');
            authDialogWiz.show();
        });
    </script>
<?}?>