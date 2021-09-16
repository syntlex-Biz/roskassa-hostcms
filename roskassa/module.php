<?php

$tmpDir = Market_Controller::instance()->tmpDir . DIRECTORY_SEPARATOR;

$aOptions = Market_Controller::instance()->options;

$aReplace = array();

$version = '1.0.0';
$sLng = 'ru';
$aPossibleLanguages = array('en', 'ru');
$sSitePostfix = '';

foreach ($aOptions as $optionName => $optionValue)
{
	$aReplace['%' . $optionName . '%'] = $optionValue;
}

$Install_Controller = Install_Controller::instance();
$Install_Controller->setTemplatePath($tmpDir);

$oShop = Core_Entity::factory('Shop', $aOptions['shop_id']);

$aPaymentsystemi18n = array(
	'ru' => array(
		9 => array('file' => 'roskassa.php', 'name' => 'roskassa', 'description' => 'РосКасса - поставщик платежных услуг в России. RosKassa гарантирует, что ваши клиенты могут просто, безопасно и быстро расплачиваться в вашем интернет-магазине. Используя такие способы оплаты, как Visa, MasterCard и Credit Card, мы поддерживаем интернет-магазины, учреждения и благотворительные организации. Помимо этих способов оплаты, Роскасса предлагает CMS-модули RosKassa. Полезные решения, такие как плагины, которые можно использовать прямо с вашей учетной записью RosKassa. Плагин, который легко установить, можно использовать для инициирования и совершения платежей. Когда потребитель хочет заплатить, он переходит на страницу оформления заказа. В этот момент появляется РосКасса. Потребитель может выбрать способ оплаты, которым он хочет заплатить. Торговец самостоятельно выбирает набор способов оплаты на вашем сайте. Платежи обрабатываются в их собственной безопасной платежной среде. После оплаты покупатель перенаправляется в интернет-магазин продавцов. Платежный модуль RosKassa органично интегрируется в вашу платежную структуру. С легкостью обрабатывайте платежи в своем магазине через Роскасса. Легко управляйте своими транзакциями из вашего магазина. Этот плагин предлагает варианты оплаты для следующих способов оплаты: Bank cards YuMoney QIWI WebMoney Perfect money Payeer Bitcoin Litecoin Ethereum Advcash PayPal Apple Pay'),
	)
);

// Платежные системы
foreach ($aPaymentsystemi18n[$sLng] as $iPaymentsystemId => $aPaymentsystem)
{
	$oShop_Payment_System = Core_Entity::factory('Shop_Payment_System');
	$oShop_Payment_System->name = $aPaymentsystem['name'];
	$oShop_Payment_System->description = $aPaymentsystem['description'];
	$oShop->add($oShop_Payment_System);

	$aReplace['Shop_Payment_System_Handler' . $iPaymentsystemId] = 'Shop_Payment_System_Handler' . $oShop_Payment_System->id;

	$sContent = $Install_Controller->loadFile($tmpDir . "tmp/" . $aPaymentsystem['file'], $aReplace);
	$oShop_Payment_System->savePaymentSystemFile($sContent);
}