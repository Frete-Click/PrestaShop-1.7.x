<?php


class FreteClickGetOrderShippingCostController
{
    public function __construct($module, $file, $path)
    {
        $this->file = $file;
        $this->module = $module;
        $this->context = Context::getContext();
        $this->_path = $path;
    }

    public function run($cart, $shipping_fees)
    {
        $carrier = new Carrier($this->module->id_carrier);

        if ($carrier->name !== 'Frete Click') {
            return false;
        }

        $productTotalPrice = $this->getTotal($cart);
        if ($productTotalPrice >= 300) {
            return 0;
        }

        if (!isset($_SESSION)) {
            session_start();
        }

        $address = new Address($cart->id_address_delivery);
        $cep = $address->postcode;

        if ($cep === '') {
            $cep = isset($_SESSION['cep']) ? $_SESSION['cep'] : '';
        }

        if (strlen($cep) < 8) {
            return 0;
        }

        $destination = self::getAddressByCep($cep);
        $deliveryAddress = [
            'country' => $destination->response->data[0]->country,
            'state' => $destination->response->data[0]->state,
            'city' => $destination->response->data[0]->city,
        ];

        $data = [
            'origin' => $this->loadRetrieveAddress(),
            'destination' => $deliveryAddress,
            'productTotalPrice' => $this->getTotal($cart),
            'packages' => $this->loadProducts($cart),
            "productType" => 'Material de Escritório',
            "contact" => null
        ];

        if (isset($_SESSION[md5(json_encode($data))])) {
            $result = $_SESSION[md5(json_encode($data))];
            $bestQuote = $this->bestPrice($result->quotes);

            foreach (Language::getLanguages(true) as $lang) {
                $carrier->delay[$lang['id_lang']] = $this->module->l("Entrega em até {$bestQuote->deliveryDeadline} dias");
            }
            $carrier->save();
            return $bestQuote->total;
        }
        $result = $this->getDeliveryService($data);

        $bestQuote = $this->bestPrice($result->quotes);

        foreach (Language::getLanguages(true) as $lang) {
            $carrier->delay[$lang['id_lang']] = $this->module->l("Entrega em até {$bestQuote->deliveryDeadline} dias");
        }
        $carrier->save();

        return $bestQuote->total;
    }

    public function bestPrice($quotes)
    {
        $value = null;
        foreach ($quotes as $quote) {
            if (!isset($value) || $quote->total < $value->total) {
                $value = $quote;
            }
        }

        return $value;
    }

    public function matchCarrier($id, $quotes)
    {
        $value = 0;

        $carrier = new Carrier($id);

        foreach ($quotes as $quote) {
            if ($carrier->name === $quote->carrier->name) {
                $this->module->cookie->fc_quote_id = $quote->id;
                return $quote->total;
            }
        }

        return $value;
    }

    public function getShippingCost($id_carrier, $delivery_service)
    {
        $shipping_cost = false;
        if (isset($delivery_service['ClassicDelivery']) && $id_carrier === Configuration::get('TEST')) {
            $shipping_cost = (int)$delivery_service['ClassicDelivery'];
        }
        if (isset($delivery_service['RelayPoint']) && $id_carrier === Configuration::get('TEST2')) {
            $shipping_cost = (int)$delivery_service['RelayPoint'];
        }
        return $shipping_cost;
    }

    public static function getAddressByCep($cep)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.freteclick.com.br/geo_places?input=' . $cep);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept' => 'application/json',
            'content-type' => 'application/ld+json',
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response, false);

//        if (!isset($response->response->data[0])) {
//            throw new Exception('Cep não encontrado!');
//        }

        return $response;
    }

    public function loadRetrieveAddress()
    {
        return [
            'country' => Configuration::get('FC_COUNTRY_ORIGIN'),
            'state' => Configuration::get('FC_STATE_ORIGIN'),
            'city' => Configuration::get('FC_CITY_ORIGIN')
        ];
    }

    public function loadProducts($cart)
    {
        $packages = [];
        foreach ($cart->getProducts() as $product) {
            $packages[] = [
                "qtd" => (float)$product['quantity'],
                "weight" => (float)$product['weight'],
                "height" => ((float)$product['height']) / 100,
                "width" => ((float)$product['width']) / 100,
                "depth" => ((float)$product['depth']) / 100
            ];
        }
        return $packages;
    }

    public function getTotal($cart)
    {
        $total = 0;
        foreach ($cart->getProducts() as $product) {
            $total += $product['total_wt'];
        }
        return $total;
    }

    public function getDeliveryService($postFields)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.freteclick.com.br/quotes');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept:application/json',
                'Content-Type:application/json',
                'api-token:' . Configuration::get('FC_API_KEY'),
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $response = json_decode($response, false);

            if ($response && $response->response && $response->response->data && $response->response->data->order) {
                $_SESSION['lastQuote'] = md5(json_encode($postFields));
                $_SESSION[md5(json_encode($postFields))] = $response->response->data->order;
                return $response->response->data->order;
            }
        } catch (\Exception $e) {
            die($e->getMessage());
        }

        return null;
    }
}