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

        try {

          $quoteMD5 = md5(json_encode($payload));

          if (isset($_SESSION[$quoteMD5])) {
            $simulation = $_SESSION[$quoteMD5];
            $bestQuote  = $this->bestPrice($simulation->quotes);
          }
          else {
            $simulation = $this->module->getQuoteSimulation($payload);
            $bestQuote  = $this->bestPrice($simulation->quotes);

            $_SESSION['lastQuote'] = $quoteMD5;
            $_SESSION[$quoteMD5]   = $simulation;

            $deadLine = $this->getDeadLineFromQuote($bestQuote);
            $deadLine = (string) $deadLine;

            foreach (Language::getLanguages(true) as $lang) {
              $carrier->delay[$lang['id_lang']] = $this->module->l("Entrega em até {$deadLine} dias úteis");
            }

            $carrier->save();
          }

          return $this->module->isFreeShipping($this->getTotal($cart)) ? 0 : $bestQuote->total;

        } catch (\Throwable $th) {
            return false;
        }
    }

    public function getDeadLineFromQuote($quote)
    {
        $deadline = Configuration::get('FC_SHIPPING_DEADLINE');
        $deadline = !is_numeric($deadline) ? 0 : ((int) $deadline);

        $retrieve = (int) $quote->retrieveDeadline;

        $delivery = (int) $quote->deliveryDeadline;

        return $retrieve + $delivery + $deadline;
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
