<?php
class Shop_Payment_System_HandlerXX extends Shop_Payment_System_Handler
{
	/**
	 * url для оплаты в системе RosKassa
	 */
	protected $m_url = 'https://pay.roskassa.net/form/';

	/**
	 * Идентификатор магазина, зарегистрированного в системе "RosKassa"
	 */
	protected $m_shop = '';

	/**
	 * Первый секретный ключ
	 */
	protected $secret_key1 = '';

	/**
	 * email для отправки сообщений об ошибках оплаты
	 */
	protected $emailerror = '';

	/**
	 * Тестовый режим
	 */
	protected $test ='0';

    public function checkPaymentBeforeContent() {
        $roskassa_m_operation_id = Core_Array::getRequest('intid');
        $roskassa_m_order = Core_Array::getRequest('order_id');

        if (!empty($roskassa_m_operation_id) && !empty($roskassa_m_order))
        {
            $order_id = intval(Core_Array::getRequest('order_id'));

            $oShop_Order = Core_Entity::factory('Shop_Order')->find($order_id);

            if (!is_null($oShop_Order->id) && !$oShop_Order->paid)
            {
                /**
                 * Вызов обработчика платежной системы
                 */
                $roskassa_status = Shop_Payment_System_Handler::factory($oShop_Order->Shop_Payment_System)
                    ->shopOrder($oShop_Order)
                    ->paymentProcessing();

                exit($roskassa_status);
            }
        }
    }

    public function checkPaymentAfterContent()
    {
        if (isset($_REQUEST['order_id'])) {
            // Получаем ID заказа
            $order_id = intval(Core_Array::getRequest('order_id'));

            $oShop_Order = Core_Entity::factory('Shop_Order')->find($order_id);

            if (!is_null($oShop_Order->id)) {
                // Вызов обработчика платежной системы
                Shop_Payment_System_Handler::factory($oShop_Order->Shop_Payment_System)
                    ->shopOrder($oShop_Order)
                    ->paymentProcessing();
            }
        }
    }

    public function execute()
    {
        parent::execute();
        $this->printNotification();
        return $this;
    }

    protected function _processOrder()
    {
        parent::_processOrder();
        $this->setXSLs();
        $this->send();
        return $this;
    }

    /**
     * Идентификатор валюты, в которой передается сумма в платежную систему
     */
    protected $roskassa_currency = 1;

    public function getSumWithCoeff()
    {
        return Shop_Controller::instance()->round(($this->roskassa_currency > 0 && $this->_shopOrder->shop_currency_id > 0
                ?
                Shop_Controller::instance()->getCurrencyCoefficientInShopCurrency(
                    $this->_shopOrder->Shop_Currency,
                    Core_Entity::factory('Shop_Currency', $this->roskassa_currency)
                ) : 0) * $this->_shopOrder->getAmount()
        );
    }

    public function getInvoice()
    {
        return $this->getNotification();
    }

    public function getNotification()
    {
        $m_url = $this->m_url;
        $m_shop = $this->m_shop;
        $m_key = $this->secret_key1;
        $m_orderid = $this->_shopOrder->id;
        $m_amount = number_format($this->getSumWithCoeff(), 2, '.', '');
        $oShop_Currency = Core_Entity::factory('Shop_Currency', $this->roskassa_currency);
        $currency_code = $oShop_Currency->code;
        $currency_name = $oShop_Currency->name;
        $m_curr = ($currency_code == 'RUR') ? 'RUB' : $currency_code;

        $data = array(
            'shop_id'=> $m_shop,
            'amount'=>$m_amount,
            'currency'=>$m_curr,
            'order_id'=>$m_orderid,
        );

        if ($this->test)
        {
            $data['test'] = $this->test;
        }

        ksort($data);

        $str = http_build_query($data);
        $sign = md5($str . $m_key);
        ob_start();
        ?>
        <h1>Оплата через систему RosKassa</h1>
        <p>Сумма к оплате составляет <strong><?php echo $m_amount?> <?php echo $currency_name?></strong></p>
        <form action="<?php echo $m_url?>" name="pay" method="POST">
            <input type="hidden" name="shop_id" value="<?php echo $m_shop?>">
            <input type="hidden" name="amount" value="<?php echo $m_amount?>">
            <input type="hidden" name="currency" value="<?php echo $m_curr?>">
            <input type="hidden" name="order_id" value="<?php echo $m_orderid?>">
            <input type="hidden" name="sign" value="<?php echo $sign?>">
            <?php if ($this->test){ ?>
                <input type='hidden' name='test' value=<?php echo $this->test?>>
            <?php } ?>
            <p><img src="https://roskassa.net/wp-content/uploads/2020/12/logo-text-blue.svg " style="width: 400px"
                    alt="Система электронных платежей RosKassa"
                    title="Система электронных платежей RosKassa"
                    style="float:left; margin-right:0.5em; margin-bottom:0.5em; padding-top:0.25em;">
            </p>
            <p><input type="submit" name="submit" value="Оплатить"></p>
        </form>
        <?php
        return ob_get_clean();
    }

    public function paymentProcessing()
    {
        if (isset($_REQUEST['order_id']) && isset($_REQUEST['payment']))
        {
            $this->showResultMessage();
            return TRUE;
        }

        if (isset($_REQUEST['order_id']))
        {
            $this->ProcessResult();
            return TRUE;
        }
    }

    public function showResultMessage()
    {
        $iShop_Order_Id = Core_Array::getRequest('order_id', 0);
        $oShop_Order = Core_Entity::factory('Shop_Order')->find($iShop_Order_Id);

        if (is_null($oShop_Order->id))
        {
            // Заказ не найден
            return FALSE;
        }


        // Сравниваем хэши
        if ($_GET['payment'] ='success')
        {
            $oShop_Order->paid=1;
            $sStatus = $oShop_Order->paid == 1 ? "оплачен" : "не оплачен";

            ?><h1>Заказ <?php echo $sStatus?></h1>
            <p>Заказ <strong>№ <?php echo htmlspecialchars($oShop_Order->invoice)?></strong> <?php echo $sStatus?>.</p>
            <?php
        }
        else
        {
            ?><p>Хэш не совпал!</p><?php
        }
    }

    public function ProcessResult($request)
    {
        $iShop_Order_Id = Core_Array::getRequest('order_id', 0);
        $oShop_Order = Core_Entity::factory('Shop_Order')->find($iShop_Order_Id);

        if (is_null($oShop_Order->id) || $oShop_Order->paid)
        {
            // Заказ не найден
            return FALSE;
        }

        $sHash = Core_Array::getRequest('sign', '');
        $sSum = Core_Array::getRequest('amount', '');

        // В данном примере сумма обязательно должна быть с двумя десятичными нулями
        $sHostcmsSum = sprintf("%.2f", $this->getSumWithCoeff());

        if ($sSum == $sHostcmsSum)
        {
            $data = array(
                'shop_id'=> $this->m_shop,
                'amount'=>number_format($this->getSumWithCoeff(), 2, '.', ''),
                'currency'=>Core_Entity::factory('Shop_Currency', $this->roskassa_currency)->code,
                'order_id'=>$this->_shopOrder->id,
            );
            if ($this->test)
            {
                $data['test'] = $this->test;
            }
            ksort($data);
            $str = http_build_query($data);
            $sign = md5($str . $this->secret_key1);
            $sHostcmsHash = $sign;
            // Сравниваем хэши
            if (mb_strtoupper($sHostcmsHash) == mb_strtoupper($sHash))
            {
                $this->shopOrder($oShop_Order)->shopOrderBeforeAction(clone $oShop_Order);

                $oShop_Order->system_information = "Товар оплачен через платежную систему RosKassa.\n";
                $oShop_Order->paid();
                $this->setXSLs();
                $this->send();
                // Ответ платежной системе на уведомление об оплате, см. API платежной системы
                echo "OK{$oShop_Order->id}\n";
                ob_start();
                $this->changedOrder('changeStatusPaid');
                ob_get_clean();
            }
            else
            {
                $oShop_Order->system_information = 'Roskassa хэш не совпал!';
                $oShop_Order->save();

                // Ответ платежной системе на уведомление об оплате, см. API платежной системы
                echo "bad sign\n";
            }
        }

        if ( isset($request['order_id']))
        {
            $err = false;
            $message = '';
            if (!$err)
            {
                $order_amount = number_format($this->_shopOrder->getAmount(), 2, '.', '');

                // проверка суммы

                if ($request['amount'] != $order_amount)
                {
                    $message .= " - неправильная сумма\n";
                    $err = true;
                }
            }

            if ($err)
            {
                $to = $this->emailerror;

                if (!empty($to))
                {
                    $message = "Не удалось провести платёж через систему RosKassa по следующим причинам:\n\n" . $message . "\n" ;
                    $oShop = $this->_shopOrder->Shop;
                    $from = $oShop->getFirstEmail();
                    Core_Mail::instance()
                        ->to($to)
                        ->from($from)
                        ->subject("Ошибка оплаты")
                        ->message($message)
                        ->contentType('text/plain')
                        ->header('X-HostCMS-Reason', 'Alert')
                        ->header('Precedence', 'bulk')
                        ->send();

                }
                return $request['order_id'] . ' |error| ' . $message;
            }
        }
        else
        {
            return false;
        }
    }
}