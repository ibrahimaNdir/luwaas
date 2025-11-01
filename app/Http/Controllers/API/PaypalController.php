<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

use App\Http\Controllers\Controller;


class PaypalController extends Controller
{
    public function create(Request $request)
    {
        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();
        $response = $provider->createOrder([
            "intent" => "CAPTURE",
            "purchase_units" => [
                [
                    "amount" => [
                        "currency_code" => "USD", // ou "XOF"
                        "value" => "100.00"
                    ]
                ]
            ],
            "application_context" => [
                "return_url" => url('api/paypal/success'),
                "cancel_url" => url('api/paypal/cancel')
            ]
        ]);
        // Retourne le lien pour que l'utilisateur finalise le paiement
    }

    public function success(Request $request)
    {
        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();
        $response = $provider->capturePaymentOrder($request->token); // Le token vient du retour PayPal
        // Marque la transaction comme réussie ou non dans ta base
    }
    //
}


