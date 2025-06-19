<?php

namespace DpoPay;

/**
 * Class DpoSettings
 *
 * This class defines the configuration settings for the DPO payment system.
 * It provides a centralised location for all payment-related parameters.
 */
class DpoSettings
{
    /**
     * Configuration settings for the DPO payment system.
     * Each entry consists of a default value and a data type indicator.
     */
    public const SETTINGS = [
        'dpopay_checkout_title'       => array('', 'char'),
        'dpopay_checkout_description' => array('', 'char'),
        'dpopay_company_token'        => array('', 'char'),
        'dpopay_default_service_type' => array('', 'int'),
        'dpopay_ptl_type'             => array('', 'char'),
        'dpopay_ptl_limit'            => array(90, 'int'),
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
    ];
}
