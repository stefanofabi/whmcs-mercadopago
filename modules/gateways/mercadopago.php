<?php
/**
 * WHMCS MercadoPago Payment Gateway Module
 *
 * Payment Gateway modules allow you to integrate payment solutions with the
 * WHMCS platform.
 *
 * This sample file demonstrates how a payment gateway module for WHMCS should
 * be structured and all supported functionality it can contain.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "gatewaymodule" and therefore all functions
 * begin "gatewaymodule_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _config
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function mercadopago_MetaData()
{
    return array(
        'DisplayName' => 'MercadoPago',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function mercadopago_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'MercadoPago',
        ),
        // a text field type allows for single line text input
        'AccessToken' => array(
            'FriendlyName' => 'Access Token',
            'Type' => 'text',
            'Size' => '150',
            'Default' => '',
            'Description' => '<br />Obtener Access Token: <br /> <a href="https://www.mercadopago.com/mla/account/credentials" target="_blank" class="btn btn-info btn-xs"> MercadoPago Argentina </a>',
        ),
        'Prefix' => array(
            'FriendlyName' => 'Prefijo para referenciar pagos',
            'Type' => 'text',
            'Size' => '150',
            'Default' => 'WHMCS-',
            'Description' => '<br /> Prefijo para la referencia externa y diferenciar pagos',
        ),
        'PaymentText' => array(
            'FriendlyName' => 'Texto de pago',
            'Type' => 'text',
            'Size' => '150',
            'Default' => 'Pagá de forma segura con MercadoPago',
            'Description' => '<br /> Mensaje que aparece arriba del botón de pago',
        ),
        'BackURL_Success' => array(
            'FriendlyName' => 'URL de retorno ante pago aprobado',
            'Type' => 'text',
            'Size' => '255',
        ),
        'BackURL_Pending' => array(
            'FriendlyName' => 'URL de retorno ante pago pendiente o en proceso',
            'Type' => 'text',
            'Size' => '255',
        ),
        'BackURL_Failure' => array(
            'FriendlyName' => 'URL de retorno ante pago cancelado',
            'Type' => 'text',
            'Size' => '255',
        ),
    );
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function mercadopago_link($params)
{
    if (empty($params['AccessToken'])) 
        return "Access Token no cargado";

    $accesstoken = $params['AccessToken'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    //$description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];

    // System Parameters
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    
    $url = "https://api.mercadopago.com/checkout/preferences/";
    $ch = curl_init($url);
    
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_POST, true);
    
    $postfields = [
        "items" => [
            [
                "id" => "MP001",
                "title" => "Pago Factura Nro. #$invoiceId",
                "quantity" => 1,
                "currency_id" => $currencyCode,
                "unit_price" => intval($amount)
            ]
        ],
        "payer" => [
            "name" => $firstname,
            "surname" => $lastname,
            "email" => $email,
        ],
        "back_urls" => [
            "success" => empty($params['BackURL_Success']) ? $returnUrl : $params['BackURL_Success'],
            "pending" => empty($params['BackURL_Pending']) ? $returnUrl : $params['BackURL_Pending'],
            "failure" => empty($params['BackURL_Failure']) ? $returnUrl : $params['BackURL_Failure'],
        ],
        "external_reference" => $params['Prefix']."".$invoiceId,
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Authorization: Bearer ".$accesstoken,
        "Content-Length: ". strlen(json_encode($postfields)),
    ]);
   
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
    
    $response = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($response);
    
    $htmlOutput = '<img src="'.$systemUrl.'mercadopago-logo.png"> <br />';
    $htmlOutput .= empty($params['PaymentText']) ? "" : $params['PaymentText']." <br /> <br />";
    $htmlOutput .= '<a class="btn btn-success" href="' . $response->init_point . '">';
    $htmlOutput .= $langPayNow;
    $htmlOutput .= '</a>';
    
    return $htmlOutput;
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 * @return array Transaction response status
 */
function mercadopago_refund($params)
{
    
}

/**
 * Cancel subscription.
 *
 * If the payment gateway creates subscriptions and stores the subscription
 * ID in tblhosting.subscriptionid, this function is called upon cancellation
 * or request by an admin user.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/subscription-management/
 *
 * @return array Transaction response status
 */
function mercadopago_cancelSubscription($params)
{

}
