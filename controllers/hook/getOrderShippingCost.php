<?php



class FreteClickGetOrderShippingCostController
{
    public function __construct($module, $file, $path)
    {
        $this->file    = $file;
        $this->module  = $module;
        $this->context = Context::getContext();
        $this->_path   = $path;
    }

    public function run($cart, $shipping_fees)
    {
        $carrier = new Carrier($this->module->id_carrier);

        if ($carrier->name !== 'Frete Click') {
            return false;
        }

        if ($this->module->isFreeShipping($this->getTotal($cart))) {
            return 0;
        }

        if (!isset($_SESSION)) {
            session_start();
        }

        $address = new Address($cart->id_address_delivery);
        $cep     = $address->postcode;

        if ($cep === '') {
            $cep = isset($_SESSION['cep']) ? $_SESSION['cep'] : '';
        }

        if (strlen($cep) < 8) {
            return false;
        }

        $destination = $this->module->getAddressByPostalcode($cep);
        $payload     = [
            'origin'            => [
                'country' => Configuration::get('FC_COUNTRY_ORIGIN'),
                'state'   => Configuration::get('FC_STATE_ORIGIN'  ),
                'city'    => Configuration::get('FC_CITY_ORIGIN'   )
            ],
            'destination'       => [
                'country' => $destination->country,
                'state'   => $destination->state,
                'city'    => $destination->city,
            ],
            'productTotalPrice' => $this->getTotal($cart),
            'packages'          => $this->loadProducts($cart),
            "productType"       => $this->getProductsCategories($cart),
            "contact"           => null
        ];

        $quoteMD5 = md5(json_encode($payload));

        if (isset($_SESSION[$quoteMD5])) {
            $result    = $_SESSION[$quoteMD5];
            $bestQuote = $this->bestPrice($result->quotes);

            foreach (Language::getLanguages(true) as $lang) {
                $carrier->delay[$lang['id_lang']] = $this->module->l("Entrega em até {$bestQuote->deliveryDeadline} dias");
            }

            $carrier->save();

            return $bestQuote->total;
        }

        try {

            $result    = $this->module->getQuoteSimulation($payload);
            $bestQuote = $this->bestPrice($result->quotes);

            $_SESSION['lastQuote'] = $quoteMD5;
            $_SESSION[$quoteMD5]   = $result;

            foreach (Language::getLanguages(true) as $lang) {
                $carrier->delay[$lang['id_lang']] = $this->module->l("Entrega em até {$bestQuote->deliveryDeadline} dias");
            }

            $carrier->save();

            return $bestQuote->total;

        } catch (\Throwable $th) {
            return false;
        }
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

    public function loadProducts($cart)
    {
        $packages = [];

        foreach ($cart->getProducts() as $product) {
            $packages[] = [
                "qtd"    => ((float) $product['quantity']),
                "weight" => ((float) $product['weight'  ]),
                "height" => ((float) $product['height'  ]) / 100,
                "width"  => ((float) $product['width'   ]) / 100,
                "depth"  => ((float) $product['depth'   ]) / 100
            ];
        }

        return $packages;
    }

    private function getProductsCategories($cart): string
    {
        $categories = [];
        foreach ($cart->getProducts() as $product) {
            $categories[] = ucfirst(strtolower($product['category']));
        }
        return implode(',', array_unique($categories));
    }

    public function getTotal($cart)
    {
        $total = 0;
        foreach ($cart->getProducts() as $product) {
            $total += $product['total_wt'];
        }
        return $total;
    }
}