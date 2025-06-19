<?php

namespace DpoPay;

class DpoDbHelper
{
    public function getTableSQLFields(): array
    {
        return [
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
            'dpopay_response_payment_date' => ' char(28)',
        ];
    }
}
