<?php

namespace Lucena\Cielo\Payment;

use Illuminate\Support\Facades\Config;
use Webkul\Payment\Payment\Payment;
use DB;
use Illuminate\Support\Facades\Log;
use Webkul\Checkout\Models\CartAddress;
/**
 * Paypal class
 *
 * @author    Jitendra Singh <jitendra@webkul.com>
 * @copyright 2018 Webkul Software Pvt Ltd (http://www.webkul.com)
 */
abstract class Cielo extends Payment
{
    /**
     * PayPal web URL generic getter
     *
     * @param array $params
     * @return string
     */
    public function getCieloUrl($params = [])
    {

        $cart = $this->getCart();
        
        // ENVIA DADOS PRO CHECKOUT DA CIELO PRA CRIAR O CHECKOUT
//        file_put_contents('filename.txt', print_r($array, true));
        
        $items_data = array();
        foreach($cart->items as $item){
            $data_item = array(
                'Name' => $item->name,
                'UnitPrice' => $item->price * 100,
                'Quantity' => $item->quantity,
                'Type' => 'Asset',
                'Sku' => $item->sku
            );
            array_push($items_data, $data_item);
        }

        $cart_data = array(
            'Discount' => array(
                'Type' => "Amount",
                'Value' => $cart->discount_amount * 100
            ),
            'Items' => $items_data
        );

        // para descobrir endereço e o preço da entrega, precisa fazer uma query, buscando o endereço do carrinho com o address_type = shipping
        // depois buscar este id na tabela 	cart_shipping_rates

        $cart_shipping_rates = DB::table('cart_shipping_rates')
            ->join('cart','cart_shipping_rates.method','cart.shipping_method')
            ->join('cart_address','cart_address.cart_id', '=', 'cart.id')
            ->select('cart_shipping_rates.*','cart_shipping_rates.method_title','cart_address.postcode')
            ->where('cart.id',$cart->id)
            ->where('cart_address.address_type','shipping')
            ->orderBy('cart_shipping_rates.id','desc')
            ->first();

            $cart_address = CartAddress::where('cart_id',$cart->id)
            ->where('address_type','shipping')->first();

            $shipping = array(
                "TargetZipCode"=> $cart_shipping_rates->postcode,

                "Type" => "FixedAmount",
                'Services' => array(
                    array(
                        'Name' => $cart_shipping_rates->method_title,
                        'Price' => $cart_shipping_rates->price*100,
                        'Deadline' => 2,
                        "Carrier" => null
                    )
                    ),
                    // alterar o sistema para receber os dados de entrega. . 
                'Address' => array(
                    "Street" =>  $cart->billing_address->address1,
                    "Number" => $cart_address->number,
                    "Complement" => $cart_address->complement,
                    "District" => $cart_address->district,
                    "City" => $cart_address->city,
                    "State" => $cart_address->state
                )
        );
      
        $payment = array(
            'BoletoDiscount' => 0,
            'DebitDiscount' => 0,
            'FirstInstallmentDiscount' => 0
        );

        $customer = array(
            'Identity' => $cart->customer_identy,
            'FullName' => $cart->customer_first_name . " " . $cart->customer_last_name,
            'Email' => $cart->customer_email,
            'Phone' => $cart->customer_phone
        );

        $options = array(
            'AntifraudEnabled' => true,
            'ReturnUrl' => route('cielo.standard.success')
        );
         
        $data = array(
            'OrderNumber' => $cart->id,
            'Cart' => $cart_data,
            'Shipping' => $shipping,
            'Payment' => $payment,
            'Customer' => $customer,
            'Options' => $options,
            'Settings' => null
        );

        
        $url_cielo = $this->Request($data,$this->getConfigData('merchant_key'))['settings']['checkoutUrl'];

        //https://cieloecommerce.cielo.com.br/api/public/v1/orders
        return $url_cielo;
    }

    function Request($data,$merchant_id){
        $cabeçalhos = [
            "Content-Type: application/json",
            'MerchantId:'.$merchant_id,
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [

            // Define o método POST:
            CURLOPT_CUSTOMREQUEST => 'POST',

            /* Uma outra opção é utilizar:
            CURLOPT_POST => true,
            */
            // Define o URL:
            CURLOPT_URL => 'https://cieloecommerce.cielo.com.br/api/public/v1/orders',

            // Define os cabeçalhos:    
            CURLOPT_HTTPHEADER => $cabeçalhos,

            // Define corpo, em JSON:
            CURLOPT_POSTFIELDS => json_encode($data),

            // Habilita o retorno
            CURLOPT_RETURNTRANSFER => true

        ]);

        // Executa:
        $resposta = curl_exec($ch);

        $json = json_decode($resposta, true);

            // Encerra CURL:
        curl_close($ch);
        return $json;
    }
    function gravar($texto){
        //Variável arquivo armazena o nome e extensão do arquivo.
        $arquivo = "meu_arquivo.txt";
        //Variável $fp armazena a conexão com o arquivo e o tipo de ação.
        $fp = fopen($arquivo, "a+");
        //Escreve no arquivo aberto.
        fwrite($fp, $texto . PHP_EOL);
        //Fecha o arquivo.
        fclose($fp);
    }

    /**
     * Add order item fields
     *
     * @param array $fields
     * @param int $i
     * @return void
     */
    protected function addLineItemsFields(&$fields, $i = 1)
    {
        $cartItems = $this->getCartItems();

        foreach ($cartItems as $item) {

            foreach ($this->itemFieldsFormat as $modelField => $paypalField) {
                $fields[sprintf($paypalField, $i)] = $item->{$modelField};
            }

            $i++;
        }
    }

    /**
     * Add billing address fields
     *
     * @param array $fields
     * @return void
     */
    protected function addAddressFields(&$fields)
    {
        $cart = $this->getCart();

        $billingAddress = $cart->billing_address;

        $fields = array_merge($fields, [
            'city'             => $billingAddress->city,
            'country'          => $billingAddress->country,
            'email'            => $billingAddress->email,
            'first_name'       => $billingAddress->first_name,
            'last_name'        => $billingAddress->last_name,
            'zip'              => $billingAddress->postcode,
            'state'            => $billingAddress->state,
            'address1'         => $billingAddress->address1,
            'address_override' => 1
        ]);
    }

    /**
     * Checks if line items enabled or not
     *
     * @param array $fields
     * @return void
     */
    public function getIsLineItemsEnabled()
    {
        return true;
    }
}