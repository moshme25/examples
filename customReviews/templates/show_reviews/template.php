<? if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<script data-skip-moving="true" src="<?= $this->GetFolder() . '/script.js'?>"></script>

<? $count = count($arResult['ITEMS']); ?>
<div class="productpage-reviews">
    <h2 class="slider-title"><?= $arParams["allCount"] ?> отзывов</h2>
    <div class="productpage-reviews__container">
        <? if ($count) : ?>
        <div class="productpage-reviews__col js-add-to-container" id="first-column">
            <? foreach ($arResult['ITEMS'] as $key => $item) { ?>
                <? if ($key == 0 || $key % 2 == 0) : ?>
                    <div class="productpage-reviews__item">
                        <div class="d-flex align-center space-between">
                            <h2 class="productpage-reviews__name"><?= $item["PROPERTY_USER_NAME_VALUE"] ?></h2>
                            <h2 class="productpage-reviews__date"><?= $item["DATE_ACTIVE_FROM"] ?></h2>
                        </div>
                        <div class="item-rate">
                            <div class="item-rate-<?= $item["PROPERTY_RATING_VALUE"] ?>"></div>
                        </div>
                        <p class="productpage-reviews__desc"><?= $item["PROPERTY_TEXT_VALUE"] ?></p>
                        <div class="productpage-reviews__item-votes">
                            <button class="productpage-reviews__item-votes-add js-like" data-like="4" data-review="<?= $item["ID"] ?>"></button>
                                <span class="productpage-good-votes"><?= $arResult["LIKES"][$item["ID"]]["like"] ?></span>
                            <button class="productpage-reviews__item-votes-remove js-like" data-like="5" data-review="<?= $item["ID"] ?>"></button>
                                <span class="productpage-bad-votes"><?= $arResult["LIKES"][$item["ID"]]["dislike"] ?></span>
                        </div>
                    </div>
                <? unset($arResult['ITEMS'][$key]) ?>
                <? endif; ?>
            <? } ?>
        </div>
        <div class="productpage-reviews__col js-add-to-container" id="second-column">
            <? foreach ($arResult['ITEMS'] as $key => $item) { ?>
                <div class="productpage-reviews__item">
                    <div class="d-flex align-center space-between">
                        <h2 class="productpage-reviews__name"><?= $item["PROPERTY_USER_NAME_VALUE"] ?></h2>
                        <h2 class="productpage-reviews__date"><?= $item["DATE_ACTIVE_FROM"] ?></h2>
                    </div>
                    <div class="item-rate">
                        <div class="item-rate-<?= $item["PROPERTY_RATING_VALUE"] ?>"></div>
                    </div>
                    <p class="productpage-reviews__desc"><?= $item["PROPERTY_TEXT_VALUE"] ?></p>
                    <div class="productpage-reviews__item-votes">
                        <button class="productpage-reviews__item-votes-add js-like" data-like="4" data-review="<?= $item["ID"] ?>"></button>
                            <span class="productpage-good-votes"><?= $arResult["LIKES"][$item["ID"]]["like"] ?></span>
                        <button class="productpage-reviews__item-votes-remove js-like" data-like="5" data-review="<?= $item["ID"] ?>"></button>
                            <span class="productpage-bad-votes"><?= $arResult["LIKES"][$item["ID"]]["dislike"] ?></span>
                    </div>
                </div>
            <? } ?>
        </div>
        <? endif; ?>
        <div class="productpage-reviews__col last-col" <?= ($count>0) ? 'itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating"' : ''?>>
            <meta <?= ($count>0) ? 'itemprop="reviewCount"' : '' ?> content="<?= $count ?>">
            <h2 class="productpage-reviews-assignment-title">Средняя оценка товара</h2>
            <div class="d-flex productpage-reviews-assignment">
                <div class="item-rate">
                    <div class="item-rate-<?= floor($arResult["overall_rating"]) ?>"></div>
                </div><span class="productpage-reviews-assignment-value" <?= ($count>0) ? 'itemprop="ratingValue"':'' ?> content="<?= $arResult["overall_rating"] ?>"><?= $arResult["overall_rating"] ?></span><span
                    class="productpage-reviews-assignment-desc">из 5</span>
            </div>
            <? if ($USER->IsAuthorized()) : ?><button class="productpage-reviews-add-review add-review-button">Добавить
                свой отзыв</button>
            <? endif; ?>
        </div>
        <? if ($USER->IsAuthorized()) : ?><button
            class="productpage-reviews-add-review add-review-button for-mobile">Добавить свой отзыв</button>
        <? endif; ?>
    </div>
</div>
<? if ($count == 10) :?>
    <div class="show-more-button__container">
        <button class="productpage-reviews-add-review show-more-button js-show-more-button" data-show='<?= json_encode($arParams["ID"]) ?>'>Показать еще</button>
    </div>
<? endif; ?>
<script>var CLikeArea = new CLikeArea();</script>