<?php

use Arrilot\BitrixModels\Models\UserModel;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectNotFoundException;
use Bitrix\Main\Web\Json;
use Bitrix\Sale\Fuser;
use Bitrix\Sale\PropertyValue;
use Smit\Bitrix\Config\Option;
use Smit\Models\Setting;
use Smit\Sale\Basket;
use Smit\Sale\BasketItem;
use Smit\Sale\Order;

/**
 * Class SmitSaleOrderAjaxComponent
 */
class SmitSaleOrderAjaxComponent extends \CBitrixComponent
{
    /**
     * @var null|Order $order Объект заказа.
     */
    public $order = null;
    /**
     * @var array Массив с контентной информацией страницы.
     */
    public $content = [];
    /**
     * @var array $errors Массив с ошибками.
     */
    protected $errors = [];
    /**
     * @var array Массив с ответом для AJAX.
     */
    protected $response = [
        'errors' => [],
        'html' => '',
    ];

    public function onPrepareComponentParams($params): array
    {
        Loader::includeModule('sale');
        Loader::includeModule('catalog');

        // Тип плательщика пользователя.
        if ($params['PERSON_TYPE_ID'] && intval($params['PERSON_TYPE_ID'])) {
            $params['PERSON_TYPE_ID'] = intval($params['PERSON_TYPE_ID']);
        } elseif (intval($this->request['payer']['person_type_id'])) {
            $params['PERSON_TYPE_ID'] = intval($this->request['payer']['person_type_id']);
        } elseif (is_null($this->order)) {
            $params['PERSON_TYPE_ID'] = 1;
        }

        // Проверка на ajax-запрос.
        if (isset($params['AJAX'])) {
            $params['AJAX'] = $params['AJAX'] === 'Y';
        } elseif (isset($this->request['ajax'])) {
            $params['AJAX'] = $this->request['ajax'] === 'Y';
        } else {
            $params['AJAX'] = false;
        }

        // Проверка на выполняемое действие.
        if (isset($params['ACTION']) && strlen($params['ACTION']) > 0) {
            $params['ACTION'] = strval($params['ACTION']);
        } elseif (isset($this->request['action']) && strlen($this->request['action']) > 0) {
            $params['ACTION'] = strval($this->request['action']);
        } else {
            $params['ACTION'] = '';
        }

        return parent::onPrepareComponentParams($params);
    }

    /**
     * Метод регистрирует кастомные классы, наследуемые от битриксовых, расширяющие функционал.
     *
     * @return void
     */
    protected function registerEntities(): void
    {
        $registry = Bitrix\Sale\Registry::getInstance(Bitrix\Sale\Registry::REGISTRY_TYPE_ORDER);
        $registry->set(Bitrix\Sale\Registry::ENTITY_BASKET, '\Smit\Sale\Basket');
        $registry->set(Bitrix\Sale\Registry::ENTITY_BASKET_ITEM, '\Smit\Sale\BasketItem');
        $registry->set(Bitrix\Sale\Registry::ENTITY_ORDER, '\Smit\Sale\Order');
        $registry->set(Bitrix\Sale\Registry::ENTITY_PROPERTY_VALUE, '\Smit\Sale\PropertyValue');
    }

    /**
     * Метод создает виртуальный заказ.
     *
     * @return void
     */
    protected function createVirtualOrder(): void
    {
        try {
            // Получение идентификатора сайта.
            $siteId = Context::getCurrent()->getSite();
            // Получение товаров корзины.
            $basketItems = Basket::loadItemsForFUser(Fuser::getId(), $siteId)->getOrderableItems();

            // Если корзина пустая, выполнить редирект на страницу каталог.
            if (!$basketItems->count() && !$this->arParams['AJAX']) {
                LocalRedirect('/catalog/');
            }

            $otherUser = null;

            // Доступные символы для логина и пароля.
            $chars = [
                'abcdefghijklnmopqrstuvwxyz',
                'ABCDEFGHIJKLNMOPQRSTUVWXYZ',
                '0123456789',
            ];
            // Генерация случайного пароля.
            $password = randString(25, $chars);

            //получение доступного идентификатора пользователя
            $existingUserId = Smit\Utils::getExistingUserId();

            if ($this->request['save'] === 'Y') {
                global $USER;
                // Регистрация нового пользователя, если покупатель не авторизован.
                if (!$USER->IsAuthorized()) {
                    // Костыль для отключения требования каптчи для регистрации.
                    Option::changeOptions('main', 'captcha_registration', 'N');
                    if (!UserModel::getByEmail($this->request['EMAIL'])->id) {
                        if ($this->arParams['PERSON_TYPE_ID'] == 1) {
                            $USER->Register(
                                'user' . $existingUserId,
                                '',
                                '',
                                $password,
                                $password,
                                $this->request['EMAIL']
                            );
                            //$USER->SimpleRegister($this->request['EMAIL']);
                        } elseif ($this->arParams['PERSON_TYPE_ID'] == 2) {
                            $USER->Register(
                                'user' . $existingUserId,
                                '',
                                '',
                                $password,
                                $password,
                                $this->request['F_EMAIL']
                            );
                            //$USER->SimpleRegister($this->request['F_EMAIL']);
                        }
                    } else {
                        

                        // Регистрация нового пользователя с логином 'user{последний идентификатор + 1}.
                        $USER->Register(
                            'user' . $existingUserId,
                            '',
                            '',
                            $password,
                            $password,
                            ''
                        );
                    }
                    // Костыль для отключения требования каптчи для регистрации.
                    Option::changeOptions('main', 'captcha_registration', 'Y');
                }
            }

            $userId = UserModel::current()->id;

            // Создание объекта заказа.
            $this->order = Order::create($siteId, $userId);
            $this->order->setPersonTypeId($this->arParams['PERSON_TYPE_ID']);
            $this->order->setBasket($basketItems);

            // Получение коллекции отгрузок.
            $shipmentCollection = $this->order->getShipmentCollection();

            // Добавление отгрузки.
            if (intval($this->request['delivery_id'])) {
                $shipment = $shipmentCollection->createItem(
                    Bitrix\Sale\Delivery\Services\Manager::getObjectById(
                        intval($this->request['delivery_id'])
                    )
                );
            } else {
                $shipment = $shipmentCollection->createItem();
            }

            // Установка валюты отгрузки.
            $shipment->setField('CURRENCY', $this->order->getCurrency());

            // Получение коллекции товаров отгрузки.
            $shipmentItemCollection = $shipment->getShipmentItemCollection();

            // Добавление товаров в отгрузку.
            /** @var BasketItem $item */
            foreach ($this->order->getBasket()->getOrderableItems() as $basketItem) {
                $shipmentItem = $shipmentItemCollection->createItem($basketItem);
                $shipmentItem->setQuantity($basketItem->getQuantity());
            }

            if (intval($this->request['payment_id'])) {
                // Получение коллекции платежей.
                $paymentCollection = $this->order->getPaymentCollection();

                // Добавление платежа.
                $payment = $paymentCollection->createItem(
                    Bitrix\Sale\PaySystem\Manager::getObjectById(
                        intval($this->request['payment_id'])
                    )
                );

                // Установка суммы и валюты платежа.
                $payment->setField('SUM', $this->order->getPrice());
                $payment->setField('CURRENCY', $this->order->getCurrency());
            }

            $this->setOrderProperties();
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }
    }

    /**
     * Метод устанавливает свойства заказа.
     */
    protected function setOrderProperties()
    {
        /** @var PropertyValue $property Свойство заказа. */
        foreach ($this->order->getPropertyCollection() as $property) {
            // Заполнение карты свойств заказа.
            $this->order->propertyMap[$property->getField('CODE')] = $property->getPropertyId();
            // Значение свойства.
            $value = '';

            // Значение свойства из запроса.
            foreach ($this->request as $key => $item) {
                if (strtolower($key) === strtolower($property->getField('CODE'))) {
                    $value = htmlspecialchars($item);
                }
            }

            // Значение свойства по умолчанию.
            if (!$value) {
                $value = $property->getProperty()['DEFAULT_VALUE'];
            }

            // Установка значения свойства.
            if ($value) {
                $property->setValue($value);
            }
        }

        try {
            // Установка комментария покупателя.
            $this->order->setField('USER_DESCRIPTION', $this->request['COMMENT']);
        } catch (ArgumentException $e) {
            return;
        }
    }

    /**
     * Метод вызывается ajax-запросом при переключении типа плательщика.
     *
     * @return void
     */
    protected function changePersonTypeAction(): void
    {
        $this->setTemplateName('form');
    }

    /**
     * Метод вызывается ajax-запросом при изменении способа доставки.
     *
     * @return void
     */
    protected function changeDeliveryAction(): void
    {
        $this->setTemplateName('paysystems');
        $this->response['json'] = json_encode(['period' => $this->order->getDeliveryPeriod()]);
    }

    /**
     * Метод вызывается ajax-запросом при нажатии на кнопку оформления заказа.
     *
     * @return void
     */
    protected function saveOrderAction(): void
    {
        if ($this->request['save'] && $this->request['save'] === 'Y' && !$this->errors) {
            try {
                // Сохранение заказа.
                $this->order->save();
                $this->order->doFinalAction();

                $this->response['json'] = [
                    'success' => 'Y',
                    'redirect' => '/personal/cart/order/?ORDER_ID=' . $this->order->getId(),
                ];
            } catch (ArgumentOutOfRangeException | ArgumentNullException | ObjectNotFoundException $e) {
                $this->response['json'] = ['success' => 'N'];
            }
        } else {
            $this->response['json'] = ['success' => 'N'];
        }
    }

    /**
     * Метод получает информацию на страницу из инфоблока с настройками сайта.
     */
    protected function getContent(): void
    {
        $content = Setting::query()
            ->active()
            ->select([
                'PROPERTY_ORDER_DELIVERY_TEXT',
                'PROPERTY_ORDER_FOOTER_PHONE',
                'PROPERTY_ORDER_FOOTER_LINKS',
                'PROPERTY_LINK_SHIPPING_RATES',
                'PROPERTY_LINK_TERMS_OF_USE',
            ])
            ->first()
            ->toArray();

        $this->content = $content;
    }

    /**
     * @return array|null
     * @throws ArgumentException
     */
    public function executeComponent(): ?array
    {
        global $APPLICATION;

        if ($this->arParams['AJAX']) {
            $APPLICATION->RestartBuffer();
        }

        // Регистрация классов, наследуемых от Битриксовых.
        $this->registerEntities();

        // Создание виртуального заказа.
        $this->createVirtualOrder();

        // Создание объекта отгрузки заказа.
        $this->order->setShipment();

        // Получение информации на страницу.
        $this->getContent();

        // Названия действий, получаемые посредством со клиентской стороны через ajax.
        if (isset($this->arParams['ACTION'])) {
            if (is_callable([$this, $this->arParams['ACTION'] . 'Action'])) {
                try {
                    call_user_func([$this, $this->arParams['ACTION'] . 'Action']);
                } catch (Exception $e) {
                    $this->errors[] = $e->getMessage();
                }
            }
        }

        // Возврат результата выполнения компонента для ajax.
        if ($this->arParams['AJAX']) {
            // Сохранение html шаблона.
            if ($this->getTemplateName()) {
                ob_start();
                $this->includeComponentTemplate();
                $this->response['html'] = ob_get_contents();
                ob_end_clean();
            }

            $this->response['errors'] = $this->errors;
            header('Content-Type: application/json');
            echo Json::encode($this->response);
            die;
        }

        $this->includeComponentTemplate();

        return $this->arResult;
    }
}
