<?php

/**
 * dpopay.php
 *
 * Copyright (c) 2024 DPO Group
 *
 * @author      DPO Group
 * @link        https://dpogroup.com
 * @version     1.0.0
 */

use Dpo\Common\Dpo;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die('Restricted access');
if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

require_once 'vendor/autoload.php';

class plgVmPaymentDpopay extends vmPSPlugin
{
    // Instance of class
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable   = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey  = 'id';
        $this->_tableId    = 'id';
        $varsToPush        = array(
            'dpopay_checkout_title'       => array('', 'char'),
            'dpopay_checkout_description' => array('', 'char'),
            'dpopay_company_token'        => array('', 'char'),
            'dpopay_default_service_type' => array('', 'int'),
            'dpopay_ptl_type'             => ['', 'char'],
            'dpopay_ptl_limit'            => [90, 'int'],
            'payment_currency'            => array(0, 'int'),
            'debug'                       => array(0, 'int'),
            'dpopay_status_pending'       => array('', 'char'),
            'dpopay_status_success'       => array('', 'char'),
            'dpopay_status_canceled'      => array('', 'char'),
            'countries'                   => array(0, 'char'),
            'min_amount'                  => array(0, 'int'),
            'max_amount'                  => array(0, 'int'),
            'cost_per_transaction'        => array(0, 'int'),
            'cost_percent_total'          => array(0, 'int'),
            'tax_id'                      => array(0, 'int')
        );
        $this->addVarsToPushCore($varsToPush, 1);
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    function getTableSQLFields()
    {
        return array(
            'id'                           => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'          => ' int(11) UNSIGNED',
            'order_number'                 => ' char(32)',
            'virtuemart_paymentmethod_id'  => ' mediumint(1) UNSIGNED',
            'payment_name'                 => 'VARCHAR(75) NOT NULL DEFAULT "Dpopay"',
            'payment_order_total'          => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency'             => 'char(3) ',
            'cost_per_transaction'         => ' decimal(10,2)',
            'cost_percent_total'           => ' decimal(10,2)',
            'tax_id'                       => ' smallint(1)',
            'dpopay_response'              => ' varchar(255)  ',
            'dpopay_response_payment_date' => ' char(28)'
        );
    }

    /**
     * This is where the checkout confirm order comes to
     *
     * @param $cart
     * @param $order
     *
     * @return false|void|null
     * @throws Exception
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        $retVar = null;

        $method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);
        if (!$method) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            $retVar = false;
        } else {
            $session        = JFactory::getSession();
            $return_context = $session->getId();
            $this->_debug   = $method->debug;
            $this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');
            if (!class_exists('VirtueMartModelOrders')) {
                require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . PF_ORDER);
            }
            if (!class_exists('VirtueMartModelCurrency')) {
                require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
            }

            $dbValues['payment_name'] = $this->renderPluginName($method);
            if (!empty($method->payment_info)) {
                $dbValues['payment_name'] .= '<br />' . $method->payment_info;
            }

            $vendorModel = new VirtueMartModelVendor();
            $vendorModel->setId(1);
            $this->getPaymentCurrency($method);
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $q  = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="'
                  . $db->quote($method->payment_currency) . '" ';
            $db->setQuery($q);
            $paymentCurrency        = CurrencyDisplay::getInstance($method->payment_currency);
            $totalInPaymentCurrency = round(
                $paymentCurrency->convertCurrencyTo(
                    $method->payment_currency,
                    $order['details']['BT']->order_total,
                    false
                ),
                2
            );

            $dpopayDetails = $this->_getDpopayDetails($method);

            if (empty($dpopayDetails['companyToken'])) {
                vmInfo(JText::_('VMPAYMENT_DPOPAY_COMPANY_TOKEN_NOT_SET'));
                $retVar = false;
            } else {
                $payCurrency = $paymentCurrency->_vendorCurrency_code_3;
                $countryId   = $order['details']['BT']->virtuemart_country_id;
                $country     = VirtueMartModelCountry::getCountry($countryId);
                $data        = [
                    'serviceType'       => $method->dpopay_default_service_type,
                    'companyToken'      => $method->dpopay_company_token,
                    'paymentAmount'     => $totalInPaymentCurrency,
                    'paymentCurrency'   => $payCurrency,
                    'companyRef'        => $order['details']['BT']->order_number,
                    'redirectURL'       => JROUTE::_(
                        JURI::root()
                        . 'index.php?option=com_virtuemart&view=vmplg&task=pluginresponsereceived&pm='
                        . $order['details']['BT']->virtuemart_paymentmethod_id
                        . "&o_id={$order['details']['BT']->order_number}"
                    ),
                    'backURL'           => JROUTE::_(
                        JURI::root()
                        . 'index.php?option=com_virtuemart&view=vmplg&task=pluginUserPaymentCancel&on='
                        . $order['details']['BT']->order_number . '&pm='
                        . $order['details']['BT']->virtuemart_paymentmethod_id
                    ),
                    'customerFirstName' => $order['details']['BT']->first_name,
                    'customerLastName'  => $order['details']['BT']->last_name,
                    'customerEmail'     => $order['details']['BT']->email,
                    'customerZip'       => $order['details']['BT']->zip,
                    'customerAddress'   => $order['details']['BT']->address_1 . ' ' . $order['details']['BT']->city,
                    'customerCountry'   => $country->country_2_code,
                    'customerCity'      => $order['details']['BT']->city,
                    'customerPhone'     => $order['details']['BT']->phone_1 ?? '',
                ];
                $dpo         = new Dpo(false);
                $token       = $dpo->createToken($data);

                if ($token['success'] && $token['result'] === '000') {
                    // Store order data in database
                    $values['order_number']                = $order['details']['BT']->order_number;
                    $values['payment_name']                = $method->payment_name;
                    $values['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
                    $values['cost_percent_total']          = $method->cost_percent_total;
                    $values['payment_currency']            = $payCurrency;
                    $values['payment_order_total']         = $totalInPaymentCurrency;

                    $verify = $dpo->verifyToken(
                        [
                            'companyToken' => $method->dpopay_company_token,
                            'transToken'   => $token['transToken'],
                        ]
                    );
                    if (!empty($verify) && str_starts_with($verify, '<?xml')) {
                        $verify = new SimpleXMLElement($verify);
                    }
                    $orderAmount                    = $totalInPaymentCurrency;
                    $verifyAmount                   = (float)$verify?->TransactionAmount->__toString();
                    $costPerTransaction             = $verifyAmount - $orderAmount;
                    $values['cost_per_transaction'] = number_format($costPerTransaction, 2, '.');
                    $this->storePSPluginInternalData($values);

                    $url = $dpo->getPayUrl() . "?ID=" . $token['transToken'];
                    header("Location: $url");
                    die();
                }
            }
        }

        return $retVar;
    }

    function _getDpopayDetails($method): array
    {
        return [
            'companyToken' => $method->dpopay_company_token,
            'serviceType'  => $method->dpopay_default_service_type,
        ];
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $this->getPaymentCurrency($method);
        $paymentCurrencyId = $method->payment_currency;
    }

    /**
     * @param $html
     *
     * @return bool|null
     * @throws Exception
     */
    public function plgVmOnPaymentResponseReceived(&$html): ?bool
    {
        $paymentData               = JFactory::getApplication()->input->getArray();
        $virtuemartPaymentmethodId = $paymentData['pm'];
        if (!($method = $this->getVmPluginMethod($virtuemartPaymentmethodId))) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        vmdebug('plgVmOnPaymentResponseReceived', $paymentData);
        $orderNumber = $paymentData['o_id'];
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . PF_ORDER);
        }

        // Verify the integrity of the returned data
        $dpo    = new Dpo(false);
        $verify = $dpo->verifyToken(
            [
                'companyToken' => $method->dpopay_company_token,
                'transToken'   => $paymentData['TransactionToken'],
            ]
        );

        $this->processVerify($verify, $orderNumber);

        return true;
    }

    /**
     * @param string $verify
     * @param $orderNumber
     *
     * @return void
     * @throws Exception
     */
    public function processVerify(string $verify, $orderNumber): void
    {
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . PF_ORDER);
        }

        $virtuemartOrderId = VirtueMartModelOrders::getOrderIdByOrderNumber($orderNumber);
        $payment           = $this->getDataByOrderId($virtuemartOrderId);
        $paymentFields     = json_decode(json_encode($payment), true);

        $method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);

        $orderModel                   = new VirtueMartModelOrders();
        $order['virtuemart_order_id'] = $virtuemartOrderId;

        if (!empty($verify) && str_starts_with($verify, '<?xml')) {
            $verify = new SimpleXMLElement($verify);
        }
        $result            = $verify?->Result->__toString();
        $resultExplanation = $verify?->ResultExplanation->__toString();
        $transactionAmount = $verify?->TransactionAmount->__toString();
        $transactionRef    = $verify?->TransactionRef->__toString();
        $companyRef        = $verify?->CompanyRef->__toString();
        $status            = $method->dpopay_status_canceled;
        if ($result === '000') {
            // Payment was successful
            if (
                $payment->order_number === $companyRef &&
                (
                    (int)(100 * (float)$transactionAmount) ===
                    (
                        (int)(100 * (float)($payment->cost_per_transaction)) +
                        (int)(100 * (float)($payment->payment_order_total))
                    )
                )
            ) {
                // Payment is successful and details match
                $status                                        = $method->dpopay_status_success;
                $paymentFields['dpopay_response']              = $transactionRef;
                $paymentFields['dpopay_response_payment_date'] = date('Y-m-d H:i:s');
                $order['customer_notified']                    = 1;
                $order['comments']                             = "DPO Payment Confirmed: $transactionRef";
                $order['paid']                                 = $payment->payment_order_total;
            } else {
                $paymentFields['dpopay_response']
                                   = "Amount and/or reference did not match: $transactionAmount $companyRef";
                $order['comments'] = "Amount and/or reference did not match: $transactionAmount $companyRef";
            }
        } else {
            $paymentFields['dpopay_response'] = "$result: $resultExplanation";
            $order['comments']                = "Failed: $result: $resultExplanation";
        }
        $order['order_status'] = $status;
        $orderModel->updateStatusForOneOrder($virtuemartOrderId, $order);
        $this->storePSPluginInternalData($paymentFields);

        $this->emptyCart();
    }

    /**
     * Returns here if the 'Cancel' button is clicked on the DPO Pay portal
     *
     * @return bool|null
     * @throws Exception
     */
    public function plgVmOnUserPaymentCancel(): ?bool
    {
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . PF_ORDER);
        }

        $paymentData               = JFactory::getApplication()->input->getArray();
        $virtuemartPaymentmethodId = $paymentData['pm'];
        $orderNumber               = $paymentData['on'];
        if (!($method = $this->getVmPluginMethod($virtuemartPaymentmethodId))) {
            return null;
        }

        $virtuemartOrderId                             = VirtueMartModelOrders::getOrderIdByOrderNumber($orderNumber);
        $payment                                       = $this->getDataByOrderId($virtuemartOrderId);
        $paymentFields                                 = json_decode(json_encode($payment), true);
        $paymentFields['dpopay_response']              = 'User cancelled';
        $paymentFields['dpopay_response_payment_date'] = date('Y-m-d H:i:s');

        $this->storePSPluginInternalData($paymentFields);
        $orderModel                   = new VirtueMartModelOrders();
        $order['virtuemart_order_id'] = $virtuemartOrderId;
        $order['order_status']        = $method->dpopay_status_canceled;
        $order['comments']            = 'The transaction was cancelled by the user';
        $orderModel->updateStatusForOneOrder($virtuemartOrderId, $order);

        return true;
    }

    /**
     * DPO Pay pushPayments posts to here
     *
     * @return bool|null
     * @throws Exception
     */
    public function plgVmOnPaymentNotification(): ?bool
    {
        $paymentData      = JFactory::getApplication()->input->getArray();
        $transactionToken = $paymentData['TransactionToken'] ?? null;
        if (!$transactionToken) {
            // This is not a DPO Pay response
            return null;
        }
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->select($db->quoteName(['virtuemart_paymentmethod_id', 'payment_element', 'payment_params']));
        $query->from($db->quoteName('#__virtuemart_paymentmethods'));
        $query->where($db->quoteName('payment_element') . ' = ' . $db->quote('dpopay'));
        $db->setQuery($query);
        $result                    = $db->loadObject();
        $virtuemartPaymentMethodId = $result->virtuemart_paymentmethod_id;
        $method                    = $this->getVmPluginMethod($virtuemartPaymentMethodId);
        $dpo                       = new Dpo(false);
        $verify                    = $dpo->verifyToken(
            [
                'companyToken' => $method->dpopay_company_token,
                'transToken'   => $paymentData['TransactionToken'],
            ]
        );

        $orderNumber = $paymentData['CompanyRef'];
        $this->processVerify($verify, $orderNumber);

        return true;
    }

    /**
     * Display stored payment data for an order
     * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id)
    {
        if (!$this->selectedThisByMethodId($payment_method_id)) {
            return null;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $q  = 'SELECT * FROM `' . $this->_tablename . '` '
              . 'WHERE `virtuemart_order_id` = ' . $db->quote($virtuemart_order_id);
        $db->setQuery($q);
        if (!($paymentTable = $db->loadObject())) {
            return '';
        }

        $this->getPaymentCurrency($paymentTable);
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="'
             . $db->quote($paymentTable->payment_currency) . '" ';
        $db->setQuery($q);
        $html = '<table class="adminlist">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
        $code = "dpopay_response_";
        foreach ($paymentTable as $key => $value) {
            if (substr($key, 0, strlen($code)) == $code) {
                $html .= $this->getHtmlRowBE($key, $value);
            }
        }

        return $html .= '</table>' . "\n";
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param VirtueMartCart $cart Cart object
     * @param integer $selected ID of the method selected
     * @param $htmlIn
     *
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, int $selected, &$htmlIn): bool
    {
        if (isset($_POST['virtuemart_paymentmethod_id'])) {
            $selected = (int)htmlspecialchars($_POST['virtuemart_paymentmethod_id']);
        }
        if (isset($this->methods) && (int)$this->methods[0]->virtuemart_paymentmethod_id === $selected) {
            $cart->cartData['paymentName'] = "DPO Pay";
        }

        return $this->displayListFE($cart, $selected, $htmlIn);
    }


    public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     *
     * @param VirtueMartCart $cart cart: the cart object
     *
     * @return null if no plugin was found, 0 if more than one plugin was found,
     *  virtuemart_xxx_id if only one plugin is found
     *
     */
    public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array())
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /*
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented,
     * then the default values from this function are taken.
     * @cart: VirtueMartCart the current cart
     * @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     *
     */

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     *
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     *
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the payment method-specific data.
     *
     * @param $order_number
     * @param integer $method_id method used for this order
     *
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     *
     */
    public function plgVmOnShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param $name
     * @param $id
     * @param $data
     *
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     *
     */
    public function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    public function plgVmGetTablePluginParams($psType, $name, $id, &$xParams, &$varsToPush)
    {
        return $this->getTablePluginParams($psType, $name, $id, $xParams, $varsToPush);
    }

    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     *
     * @param $cart
     * @param $method
     * @param $cart_prices : cart prices
     *
     * @return bool: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        $address     = (($cart->ST == 0) ? $cart->BT : $cart->ST);
        $amount      = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount && $amount <= $method->max_amount
                        ||
                        ($method->min_amount <= $amount && ($method->max_amount == 0)));
        $countries   = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        if (!is_array($address)) {
            $address                          = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (in_array($address['virtuemart_country_id'], $countries) || empty($countries) || $amount_cond) {
            return true;
        }

        return false;
    }

    protected function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment DPO Pay Table');
    }
}
