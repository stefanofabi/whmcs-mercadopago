<?php
/**
 * WHMCS MercadoPago Payment Callback File
 *
 * This sample file demonstrates how a payment gateway callback should be
 * handled within WHMCS.
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging and Adding Payment to an Invoice.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

function writeLog($string) {
    $file = fopen("whmcs_mercadopago.txt", "a");
    fwrite($file, $string."" . PHP_EOL);
    fclose($file);
}

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

echo $gatewayModuleName;

$type = $_POST["type"];

if ($type != "payment") {
    if ($gatewayParams['Bitacora'])
        writeLog("ERROR: La notificacion no se refiere a un pago ($type)");

    exit();
}

$paymentId = $_POST["data"]["id"];

if ($gatewayParams['Bitacora'])
    writeLog("Llego notificacion para el pago #$paymentId");

if (empty($paymentId)) {
    if ($gatewayParams['Bitacora'])
        writeLog("ERROR: ID de pago vacio");

    exit();
}

$url = "https://api.mercadopago.com/checkout/preferences/";
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLINFO_HEADER_OUT, true);
curl_setopt($ch, CURLOPT_POST, true);

$postfields = [
    "id" => $paymentId,
];

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "Authorization: Bearer ".$accesstoken,
    "Content-Length: ". strlen(json_encode($postfields)),
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));

$response = curl_exec($ch);
curl_close($ch);
$payment = json_decode($response);


// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$success = ($payment->status == "approved") ? true : false;

//$invoiceId = str_replace($gatewayParams['Prefix'], "", $payment->external_reference);
$prefix = substr($payment->external_reference, 0, strlen($gatewayParams['Prefix']));

if ($prefix != $gatewayParams['Prefix']) {
    if ($gatewayParams['Bitacora'])
        writeLog("ERROR: Prefijo no coincide");

    exit();
}

$invoiceId = substr($payment->external_reference, strlen($gatewayParams['Prefix']));
$transactionId = $payment->id;
$paymentAmount = $payment->transaction_amount;
$paymentFee = $paymentAmount - $payment->transaction_details->net_received_amount;

$transactionStatus = $success ? 'Success' : 'Failure';

/**
 * Validate callback authenticity.
 *
 * Most payment gateways provide a method of verifying that a callback
 * originated from them. In the case of our example here, this is achieved by
 * way of a shared secret which is used to build and compare a hash.
 */
/*
$secretKey = $gatewayParams['secretKey'];
if ($hash != md5($invoiceId . $transactionId . $paymentAmount . $secretKey)) {
    $transactionStatus = 'Hash Verification Failure';
    $success = false;
}
*/

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 *
 * @param int $invoiceId Invoice ID
 * @param string $gatewayName Gateway Name
 */
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 *
 * @param string $transactionId Unique Transaction ID
 */
if ($gatewayParams['Bitacora'])
    writeLog("Chequear transaccion #$transactionId");

checkCbTransID($transactionId);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */

if ($gatewayParams['Bitacora'])
    writeLog("logTransaction $transactionId");
    
logTransaction($gatewayParams['name'], $_POST, $transactionStatus);

if ($success) {

    /**
     * Add Invoice Payment.
     *
     * Applies a payment transaction entry to the given invoice ID.
     *
     * @param int $invoiceId         Invoice ID
     * @param string $transactionId  Transaction ID
     * @param float $paymentAmount   Amount paid (defaults to full balance)
     * @param float $paymentFee      Payment fee (optional)
     * @param string $gatewayModule  Gateway module name
     */
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $paymentFee,
        $gatewayModuleName
    );
    
    if ($gatewayParams['Bitacora'])
        writeLog("Agregar pago a la factura #$invoiceId");

    header("HTTP/1.1 200");
}
