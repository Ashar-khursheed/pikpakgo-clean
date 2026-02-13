<?php

namespace App\Services;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
use Illuminate\Support\Facades\Log;

class AuthorizeNetService
{
    private $apiLoginId;
    private $transactionKey;
    private $sandbox;

    public function __construct()
    {
        $this->apiLoginId = config('services.authorize_net.api_login_id');
        $this->transactionKey = config('services.authorize_net.transaction_key');
        $this->sandbox = config('services.authorize_net.environment') === 'sandbox';
    }

    /**
     * Charge a credit card
     *
     * @param array $cardDetails
     * @param float $amount
     * @param string $refId
     * @return array
     */
    public function chargeCreditCard(array $cardDetails, float $amount, string $refId)
    {
        /* Create a merchantAuthenticationType object with authentication details
           retrieved from the constants file */
        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($this->apiLoginId);
        $merchantAuthentication->setTransactionKey($this->transactionKey);

        // Set the transaction's refId
        $refId = 'ref' . time() . $refId;

        // Create the payment data for a credit card
        $creditCard = new AnetAPI\CreditCardType();
        $creditCard->setCardNumber($cardDetails['cardNumber']);
        $creditCard->setExpirationDate($cardDetails['expirationDate']);
        $creditCard->setCardCode($cardDetails['cvv']);

        // Add the payment data to a paymentType object
        $paymentOne = new AnetAPI\PaymentType();
        $paymentOne->setCreditCard($creditCard);

        // Create order information
        $order = new AnetAPI\OrderType();
        $order->setInvoiceNumber($refId);
        $order->setDescription("Booking Payment");

        // Create a TransactionRequestType object and add the previous objects to it
        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType("authCaptureTransaction");
        $transactionRequestType->setAmount($amount);
        $transactionRequestType->setOrder($order);
        $transactionRequestType->setPayment($paymentOne);
        
        // Assemble the complete transaction request
        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequestType);

        // Create the controller and get the response
        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse(
            $this->sandbox ? \net\authorize\api\constants\ANetEnvironment::SANDBOX : \net\authorize\api\constants\ANetEnvironment::PRODUCTION
        );

        if ($response != null) {
            // Check to see if the API request was successfully received and acted upon
            if ($response->getMessages()->getResultCode() == "Ok") {
                // Since the API request was successful, look for a transaction response
                // and parse it to display the results of authorizing the card
                $tresponse = $response->getTransactionResponse();
            
                if ($tresponse != null && $tresponse->getMessages() != null) {
                   return [
                       'success' => true,
                       'transaction_id' => $tresponse->getTransId(),
                       'response_code' => $tresponse->getResponseCode(),
                       'auth_code' => $tresponse->getAuthCode(),
                       'message' => 'Successfully created transaction with Transaction ID: ' . $tresponse->getTransId()
                   ];
                } else {
                    $error = 'Transaction Failed';
                    if ($tresponse->getErrors() != null) {
                        $error .= ': ' . $tresponse->getErrors()[0]->getErrorText();
                    }
                    return [
                        'success' => false,
                        'message' => $error,
                        'error_code' => $tresponse->getErrors()[0]->getErrorCode() ?? 'UNKNOWN'
                    ];
                }
                // Or, print errors if the API request wasn't successful
            } else {
                $tresponse = $response->getTransactionResponse();
                
                $error = 'Transaction Failed'; 
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    $error .= ': ' . $tresponse->getErrors()[0]->getErrorText();
                     $errorCode = $tresponse->getErrors()[0]->getErrorCode();
                } else {
                    $error .= ': ' . $response->getMessages()->getMessage()[0]->getText();
                    $errorCode = $response->getMessages()->getMessage()[0]->getCode();
                }
                
                 return [
                    'success' => false,
                    'message' => $error,
                    'error_code' => $errorCode
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'No response returned',
                'error_code' => 'NO_RESPONSE'
            ];
        }
    }
}
