<?php

	/**

	Initialize the form fields for the CapitalePay payment gateway.
	This function sets up the configuration options for the CapitalePay payment gateway, including
	enabling or disabling the gateway, setting the title and description, specifying the merchant
	account details, holder type, transaction industry type, account type, authorization mode, and
	enabling test mode.
	Compatible with PHP v8.
	*/
    function init_form_fields()
    {
        $form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'spyr-capitalepay'),
                'label' => __('Enable this payment gateway', 'spyr-capitalepay'),
                'type' => 'checkbox',
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'spyr-capitalepay'),
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'spyr-capitalepay'),
                'default' => __('Credit card', 'spyr-capitalepay')
            ),
            'description' => array(
                'title' => __('Description', 'spyr-capitalepay'),
                'type' => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'spyr-capitalepay'),
                'default' => __('Pay securely using your credit card.', 'spyr-capitalepay'),
                'css' => 'max-width:350px;'
            ),
            'merchantAccountCode' => array(
                'title' => __('Merchant Account ID', 'spyr-capitalepay'),
                'type' => 'text',
                'desc_tip' => __('This is the Merchant Code provided by CapitalePay when you signed up for an account.', 'spyr-capitalepay')
            ),
            'userName' => array(
                'title' => __('Username', 'spyr-capitalepay'),
                'type' => 'text',
                'desc_tip' => __('This is the Username provided by CapitalePay when you signed up for an account.', 'spyr-capitalepay')
            ),
            'password' => array(
                'title' => __('Password', 'spyr-capitalepay'),
                'type' => 'password',
                'desc_tip' => __('This is the Password provided by CapitalePay when you signed up for an account.', 'spyr-capitalepay')
            ),
            'holderType' => array(
                'title' => __('Holder Type', 'spyr-capitalepay'),
                'type' => 'select',
                'desc_tip' => __('Type of a payment card or bank account holder.', 'spyr-capitalepay'),
                'options' => array(
                    'P' => 'Personal',
                    'O' => 'Level II and Level III'
                )
            ),
            'TransactionIndustryType' => array(
                'title' => __('Transaction Industry Type', 'spyr-capitalepay'),
                'type' => 'select',
                'desc_tip' => __('Indicates the industry related to this merchant and specific transaction.', 'spyr-capitalepay'),
                'options' => array(
                    'DB' => 'Direct Marketing',
                    'EC' => 'Ecommerce',
                    'RE' => 'Retail',
                    'RS' => 'Restaurant',
                    'LD' => 'Lodging',
                    'PT' => 'Petroleum',
                    'CCD' => 'Corporate Credit or Debit is used when charging a corporate bank account.',
                    'C21' => 'Check 21',
                    'PPD' => 'Prearranged Payment and Deposit is used when charging an individual consumer bank account.',
                    'POP' => 'Point of Purchase entry.',
                    'TEL' => 'Telephone initatied transactions, used when the ACH transaction is initiated over the phone with the account holder.',
                    'WEB' => 'Internet initiated entry.'
                )
            ),
            'AccountType' => array(
                'title' => __('Account Type', 'spyr-capitalepay'),
                'type' => 'select',
                'desc_tip' => __('Specifies the payment method to be used with this transaction.', 'spyr-capitalepay'),
                'options' => array(
                    'R' => 'Branded credit card',
                    'E' => 'Branded debit checking card',
                    'V' => 'Branded debit savings card',
                    'S' => 'Bank savings account (ACH)',
                    'C' => 'Bank checking account (ACH)'
                )
            ),
            'authorizeTransaction' => array(
                'title' => __('Authorize a Transaction', 'cepay_cheque'),
                'type' => 'select',
                'desc_tip' => __('Instead of a complete capture, this option places an Authorize/Hold on the funds. Once the product is shipped, you can manually process the transaction.', 'cepay_cheque'),
                'options' => array(
                    'yes' => 'yes',
                    'no' => 'no'
                )
            ),
            'environment' => array(
                'title' => __('Test Mode', 'spyr-capitalepay'),
                'label' => __('Enable Test Mode', 'spyr-capitalepay'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in test mode.', 'spyr-capitalepay'),
                'default' => 'no'
            )
        );

		return $form_fields;
		
		
    }
	
?>