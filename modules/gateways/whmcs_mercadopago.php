<?php
/**
 * WHMCS MercadoPago Payment Gateway Module
 *
 * Payment Gateway modules allow you to integrate payment solutions with the
 * WHMCS platform.
 *
 * For more information, please refer to the online documentation.
 *
 * @copyright Stefano Fabi - https://github.com/stefanofabi
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * @return array
 */
function whmcs_mercadopago_MetaData()
{
    return array(
        'DisplayName' => 'WHMCS MercadoPago',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * @return array
 */
function  whmcs_mercadopago_config()
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
        'SandboxMode' => array(
            'FriendlyName' => 'Modo de prueba',
            'Type' => 'yesno',
            'Description' => 'Tilda para activar el modo sandbox',
        ),
        'Bitacora' => array(
            'FriendlyName' => 'Activar bitacora',
            'Type' => 'yesno',
            'Description' => 'Tilda para guardar un registro del webhook',
        ),
    );
}

/**
 * Payment link.
 *
 * @return string
 */
function whmcs_mercadopago_link($params)
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
    $moduleName = $params['paymentmethod'];

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
                "unit_price" => floatval($amount)
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
        "notification_url" => $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php?source_news=webhooks',
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

    if ($params['SandboxMode']) {
        $checkoutUrl = $response->sandbox_init_point;
    } else {
        $checkoutUrl = $response->init_point;
    }
    
    $htmlOutput = '<img src="'.$systemUrl.'mercadopago-logo.png"> <br />';
    $htmlOutput .= empty($params['PaymentText']) ? "" : $params['PaymentText']." <br /> <br />";
    $htmlOutput .= '<a class="btn btn-success" href="' . $checkoutUrl . '">';
    $htmlOutput .= $langPayNow;
    $htmlOutput .= '</a>';
    
    return $htmlOutput;
}

/**
 * Refund transaction.
 *
 * @return array Transaction response status
 */
function whmcs_mercadopago_refund($params)
{
    
}

/**
 * Cancel subscription.
 *
 * @return array Transaction response status
 */
function whmcs_mercadopago_cancelSubscription($params)
{

}
