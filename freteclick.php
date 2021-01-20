<?php
require_once(dirname(__FILE__).'/vendor/autoload.php');

use PrestaShop\PrestaShop\Core\Addon\Module\ModuleManagerBuilder;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Freteclick extends CarrierModule
{
    public $id_carrier;
    private $_html = '';
    private $_postErrors = array();
    public $url_shipping_quote;
    public $url_city_origin;
    public $url_city_destination;
    public $url_search_city_from_cep;
    public $url_choose_quote;
    public $url_add_quote_destination_client;
    public $url_add_quote_origin_company;
    public $cookie;
    protected static $error;

    /**
     * @var SDK\Service\API
     */
    public $API     = null;

    /**
     * @var SDK\Client\Address
     */
    public $Address = null;

    /**
     * @var SDK\Client\Quote
     */
    public $Quote   = null;

    /**
     * @var SDK\Client\People
     */
    public $People  = null;

    /**
     * @var SDK\Client\Order
     */
    public $Order   = null;

    public function __construct()
    {
        $this->module_key = '787992febc148fba30e5885d08c14f8c';
        $this->cookie     = new Cookie('Frete Click');
        $this->cookie->setExpire(time() + 20 * 60);

        $this->name    = 'freteclick';
        $this->tab     = 'shipping_logistics';
        $this->version = '2.0.3';
        $this->author  = 'Frete Click';

        $this->displayName = $this->l('Frete Click');
        $this->description = $this->l('Cálculo de fretes via transportadoras pela API da Frete Click.');

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->confirmUninstall = $this->l('Confirma a desinstalação do módulo Frete Click?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        $moduleManagerBuilder = ModuleManagerBuilder::getInstance();

        $moduleManager = $moduleManagerBuilder->build();

        if ($moduleManager->isInstalled($this->name)) {
            // Verifica se a cidade de origem foi selecionada
            if (!Configuration::get('FC_CITY_ORIGIN')) {
                $this->warning = $this->l('The home city must be configured');
            }
            // Verifica se o CEP de origem foi selecionada
            if (!Configuration::get('FC_CEP_ORIGIN')) {
                $this->warning = $this->l('The home zip code must be configured');
            }
            if (!Configuration::get('FC_STREET_ORIGIN')) {
                $this->warning = $this->l('The street origin field is required.');
            }
            if (!Configuration::get('FC_NUMBER_ORIGIN')) {
                $this->warning = $this->l('The number origin field is required.');
            }
            if (!Configuration::get('FC_DISTRICT_ORIGIN')) {
                $this->warning = $this->l('The district origin field is required.');
            }
            if (!Configuration::get('FC_STATE_ORIGIN')) {
                $this->warning = $this->l('The state origin field is required.');
            }
            if (!Configuration::get('FC_COUNTRY_ORIGIN')) {
                $this->warning = $this->l('The country origin field is required.');
            }
        }

        $this->api_url                          = 'https://api.freteclick.com.br/quotes';
        $this->url_shipping_quote               = '/sales/shipping-quote.json';
        $this->url_city_origin                  = '/carrier/search-city-origin.json';
        $this->url_city_destination             = '/carrier/search-city-destination.json';
        $this->url_search_city_from_cep         = '/carrier/search-city-from-cep.json';
        $this->url_choose_quote                 = '/sales/choose-quote.json';
        $this->url_api_correios                 = '/calculador/CalcPrecoPrazo.asmx?WSDL';
        $this->url_add_quote_destination_client = '/sales/add-quote-destination-client.json';
        $this->url_add_quote_origin_company     = '/sales/add-quote-origin-company.json.json';

        $this->API     = new SDK\Core\Client\API(Configuration::get('FC_API_KEY'));
        $this->Address = new SDK\Client\Address ($this->API);
        $this->Quote   = new SDK\Client\Quote   ($this->API);
        $this->People  = new SDK\Client\People  ($this->API);
        $this->Order   = new SDK\Client\Order   ($this->API);
    }

    public function getAddressByPostalcode(string $postalcode)
    {
      return $this->Address->getAddressByCEP(preg_replace('/[^0-9]/', '', $postalcode));
    }

    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $carrier = $this->addCarrier('Frete Click');
        Configuration::updateValue('FC_CARRIER_ID', (int)$carrier->id);

        Configuration::updateValue('FRETECLICK_LIVE_MODE', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('updateCarrier') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('displayCarrierList') &&
            $this->registerHook('displayProductAdditionalInfo') &&
            $this->registerHook('displayShoppingCartFooter') &&
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->registerHook('actionOrderStatusUpdate') &&
            $this->registerHook('extraCarrier') &&
            $this->registerHook('OrderConfirmation') &&
            Configuration::updateValue('FC_INFO_PROD', 1) &&
            Configuration::updateValue('FC_SHOP_CART', 1) &&
            Configuration::updateValue('FC_CITY_ORIGIN', '');
    }

    public function uninstall()
    {
        Configuration::deleteByName('FRETECLICK_LIVE_MODE');

        $Carrier = new Carrier((int)(Configuration::get('FC_CARRIER_ID')));
        if (Configuration::get('PS_CARRIER_DEFAULT') == (int)($Carrier->id)) {
            $carriersD = Carrier::getCarriers($this->cookie->id_lang, true, false, false, null, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);
            foreach ($carriersD as $carrierD) {
                if ($carrierD['active'] and !$carrierD['deleted'] and ($carrierD['name'] != $this->_config['name'])) {
                    Configuration::updateValue('PS_CARRIER_DEFAULT', $carrierD['id_carrier']);
                }
            }
        }

        $Carrier->deleted = 1;
        try {
            $Carrier->update();
        } catch (Exception $exception) {
            //
        }

        return
            parent::uninstall() &&
            Configuration::deleteByName('FC_INFO_PROD') &&
            Configuration::deleteByName('FC_SHOP_CART') &&
            Configuration::deleteByName('FC_CITY_ORIGIN') &&
            $this->unregisterHook('header') &&
            $this->unregisterHook('backOfficeHeader') &&
            $this->unregisterHook('actionPaymentConfirmation') &&
            $this->unregisterHook('displayCarrierList') &&
            $this->unregisterHook('updateCarrier') &&
            $this->unregisterHook('extraCarrier') &&
            $this->unregisterHook('displayProductActions') &&
            $this->unregisterHook('OrderConfirmation') &&
            $this->unregisterHook('displayShoppingCartFooter') &&
            $this->unregisterHook('actionFrontControllerSetMedia') &&
            $this->unregisterHook('actionOrderStatusUpdate');
    }

    public function hookActionFrontControllerSetMedia()
    {
        $this->context->controller->registerStylesheet(
            'freteclick-style',
            $this->_path . 'views/css/front.css',
            [
                'media'    => 'all',
                'priority' => 1000,
            ]
        );
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $this->context->controller->addJqueryUI('ui.autocomplete');
        $this->context->controller->addJS($this->_path . 'views/js/FreteClick.js');
        /**
         * If values have been submitted in the form, process.
         */
        if ((bool)Tools::isSubmit('btnSubmit') === true) {
            $this->_postProcess();

            if (count($this->_postErrors)) {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        $this->_html .= $this->renderForm();
        return $this->_html;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Freteclick configuration'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Home zip code'),
                        'hint' => $this->l('Enter the zip code where the merchandise will be collected'),
                        'name' => 'FC_CEP_ORIGIN',
                        'id' => 'cep-origin',
                        'required' => true,
                        'maxlength' => 9,
                        'class' => 'form-control fc-input-cep'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Street'),
                        'hint' => $this->l('Enter the Street where the merchandise will be collected'),
                        'name' => 'FC_STREET_ORIGIN',
                        'id' => 'street-origin',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Number'),
                        'hint' => $this->l('Enter the number where the merchandise will be collected'),
                        'name' => 'FC_NUMBER_ORIGIN',
                        'id' => 'number-origin',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Complement'),
                        'hint' => $this->l('Enter the complement where the merchandise will be collected'),
                        'name' => 'FC_COMPLEMENT_ORIGIN',
                        'id' => 'complement-origin',
                        'required' => false,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('District'),
                        'hint' => $this->l('Enter the district where the merchandise will be collected'),
                        'name' => 'FC_DISTRICT_ORIGIN',
                        'id' => 'district-origin',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Home city'),
                        'hint' => $this->l('Enter the city where the merchandise will be collected'),
                        'name' => 'FC_CITY_ORIGIN',
                        'id' => 'city-origin',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Home state'),
                        'hint' => $this->l('Enter the state where the merchandise will be collected'),
                        'name' => 'FC_STATE_ORIGIN',
                        'id' => 'state-origin',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Home Country'),
                        'hint' => $this->l('Enter the contry where the merchandise will be collected'),
                        'name' => 'FC_COUNTRY_ORIGIN',
                        'id' => 'country-origin',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('API Key'),
                        'hint' => $this->l('Enter the API key found in your dashboard http://www.freteclick.com.br'),
                        'name' => 'FC_API_KEY',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Free Shipping price'),
                        'name' => 'FC_FREE_SHIPPING_PRICE',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Shipping deadline'),
                        'name' => 'FC_SHIPPING_DEADLINE',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Product Information'),
                        'hint' => $this->l('Displays a shipping quote box on the product description screen.'),
                        'name' => 'FC_INFO_PROD',
                        'required' => true,
                        'class' => 't',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'fcip_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'fcip_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        )
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Shopping cart'),
                        'hint' => $this->l('Displays a shipping quote box on the Shopping Cart screen.'),
                        'name' => 'FC_SHOP_CART',
                        'required' => true,
                        'class' => 't',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'fcsc_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'fcsc_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        )
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save')
                )
            )
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'FRETECLICK_LIVE_MODE'        => Configuration::get('FRETECLICK_LIVE_MODE', true),
            'FRETECLICK_ACCOUNT_EMAIL'    => Configuration::get('FRETECLICK_ACCOUNT_EMAIL', 'contact@prestashop.com'),
            'FRETECLICK_ACCOUNT_PASSWORD' => Configuration::get('FRETECLICK_ACCOUNT_PASSWORD', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    public function getOrderShippingCost($cart, $shipping_cost)
    {
      $carrier = new Carrier($this->id_carrier);
      if ($carrier->name !== 'Frete Click') {
        return false;
      }

      if ($cart->orderExists()) {
        return false;
      }

      if (empty($cart->id_address_delivery)) {
        return false;
      }

      $address = new Address($cart->id_address_delivery);
      if (strlen($address->postcode) < 8) {
        return false;
      }

      try {

        $simulation = $this->getQuoteSimulation([
          'cart'        => $cart,
          'total'       => $this->getCartTotal($cart),
          'packages'    => $this->getCartPackages($cart),
          'categories'  => $this->getProductsCategories($cart),
          'destination' => $address->postcode
        ]);

        $bestQuote = $simulation->quotes[0];
        $deadLine  = $this->getDeadLineFromQuote($bestQuote);
        $deadLine  = (string) $deadLine;

        foreach (Language::getLanguages(true) as $lang) {
          $carrier->delay[$lang['id_lang']] = $this->l("Entrega em até {$deadLine} dias úteis");
        }
        $carrier->save();

        return $this->isFreeShipping($this->getCartTotal($cart)) ? 0 : $bestQuote->total;

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

    public function getProductsCategories($cart): string
    {
        $categories = [];
        foreach ($cart->getProducts() as $product) {
            $categories[] = ucfirst(strtolower($product['category']));
        }
        return implode(',', array_unique($categories));
    }

    public function getCartTotal($cart)
    {
        $total = 0;
        foreach ($cart->getProducts() as $product) {
            $total += $product['total_wt'];
        }
        return $total;
    }

    public function getOrderShippingCostExternal($params)
    {
        return 150;
    }

    protected function addCarrier($name)
    {
        $carrier = new Carrier();
        $carrier->name = $this->l($name);
        $carrier->is_module = true;
        $carrier->active = true;
        $carrier->url = 'https://www.freteclick.com.br';
        $carrier->range_behavior = 0;
        $carrier->need_range = true;
        $carrier->shipping_external = true;
        $carrier->external_module_name = $this->name;

        foreach (Language::getLanguages(true) as $lang) {
            $carrier->delay[$lang['id_lang']] = $this->l($name);
        }

        $carrier->id_tax_rules_group = 0;
        $carrier->id_zone = 1;
        $carrier->deleted = 0;
        $carrier->shipping_handling = false;

        $db = Db::getInstance();

        if ($carrier->add() == true) {

            $groups = Group::getGroups(true);
            foreach ($groups as $group) {
                $db->insert('carrier_group', array(
                    'id_carrier' => (int)($carrier->id),
                    'id_group' => (int)($group['id_group'])
                ));
            }

            $rangePrice = new RangePrice();
            $rangePrice->id_carrier = $carrier->id;
            $rangePrice->delimiter1 = '0';
            $rangePrice->delimiter2 = '10000';
            $rangePrice->add();

            $rangeWeight = new RangeWeight();
            $rangeWeight->id_carrier = $carrier->id;
            $rangeWeight->delimiter1 = '0';
            $rangeWeight->delimiter2 = '10000';
            $rangeWeight->add();

            $zones = Zone::getZones(true);
            foreach ($zones as $zone) {
                $db->insert('carrier_zone', array('id_carrier' => (int)($carrier->id), 'id_zone' => (int)($zone['id_zone'])));
                $db->insert('delivery', array('id_carrier' => (int)($carrier->id), 'id_range_price' => (int)($rangePrice->id), 'id_range_weight' => null, 'id_zone' => (int)($zone['id_zone']), 'price' => '0'), true);
                $db->insert('delivery', array('id_carrier' => (int)($carrier->id), 'id_range_price' => null, 'id_range_weight' => (int)($rangeWeight->id), 'id_zone' => (int)($zone['id_zone']), 'price' => '0'), true);
            }

            // Copy Logo
            if (!copy(dirname(__FILE__) . '/views/img/logo_carrier.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int)$carrier->id . '.jpg')) {
                return false;
            }
            Configuration::updateValue('FC_CARRIER_ID', (int)($carrier->id));

            return $carrier;
        }

        return false;
    }

    protected function addGroups($carrier)
    {
        $groups_ids = array();
        $groups = Group::getGroups(Context::getContext()->language->id);
        foreach ($groups as $group)
            $groups_ids[] = $group['id_group'];

        $carrier->setGroups($groups_ids);
    }

    protected function addRanges($carrier)
    {
        $range_price = new RangePrice();
        $range_price->id_carrier = $carrier->id;
        $range_price->delimiter1 = '0';
        $range_price->delimiter2 = '10000';
        $range_price->add();

        $range_weight = new RangeWeight();
        $range_weight->id_carrier = $carrier->id;
        $range_weight->delimiter1 = '0';
        $range_weight->delimiter2 = '10000';
        $range_weight->add();
    }

    protected function addZones($carrier)
    {
        $zones = Zone::getZones();

        foreach ($zones as $zone)
            $carrier->addZone($zone['id_zone']);
    }

    /**
     * @param $params
     * Hook update carrier
     *
     */

    public function hookupdateCarrier($params)
    {
        /**
         * Not needed since 1.5
         * You can identify the carrier by the id_reference
         */
        if ((int)($params['id_carrier']) == (int)(Configuration::get('FC_CARRIER_ID'))) {
            Configuration::updateValue('FC_CARRIER_ID', (int)($params['carrier']->id));
        }
    }

    public function hookActionPaymentConfirmation($params)
    {
    }

    public function hookActionOrderStatusUpdate($params)
    {
        $order = new Order($params['id_order']);

        if (((bool) $params['newOrderStatus']->paid) === true) {
            $this->setOrderCheckoutAsFinished($order);
        }
    }

    public function setOrderCheckoutAsFinished($order)
    {
        try {
            $carrier = new OrderCarrier($order->getIdOrderCarrier());

            if (preg_match('/^(\d+)[\-.](\d+)$/', $carrier->tracking_number, $tracking) !== 1) {
                return false;
            }

            $orderId = $tracking[1];
            $quoteId = $tracking[2];

            if (empty($orderId)) {
                throw new \Exception('Order Id is not defined');
            }

            if (empty($quoteId)) {
                throw new \Exception('Quote Id is not defined');
            }

            // retrieve customer data

            $customer   = new Customer($order->id_customer);
            $customerId = $this->People->getIdByEmail($customer->email);
            $deliveryTo = $this->loadDeliveryAddress($order->id_address_delivery);

            if ($customerId === null) {
                $payload = [
                    'name'    => $customer->firstname,
                    'alias'   => $customer->lastname,
                    'type'    => 'F',
                    'email'   => $customer->email,
                    'address' => $deliveryTo
                ];
                $customerId = $this->People->createCustomer($payload);
                if ($customerId === null) {
                  throw new \Exception('Customer was not created');
                }
            }

            // update freteclick order

            if (($price = (float) $carrier->shipping_cost_tax_incl) <= 0) {
              $price = $this->Order->getQuotationTotal($quoteId);
              if ($price === null) {
                throw new \Exception('Order quotation total value is not valid');
              }
            }

            $shopOwner = $this->People->getMe();
            $payload   = [
                'quote'    => $quoteId,
                'price'    => $price,
                'payer'    => $shopOwner->companyId,
                'retrieve' => [
                    'id'      => $shopOwner->companyId,
                    'address' => $this->loadRetrieveAddress(),
                    'contact' => $shopOwner->peopleId,
                ],
                'delivery' => [
                    'id'      => $customerId,
                    'address' => $deliveryTo,
                    'contact' => $customerId,
                ],
            ];

            if ($this->Order->finishCheckout($orderId, $payload) === true) {

              // update order shop

              $order->setWsShippingNumber($orderId);
              $order->save();

              return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function loadDeliveryAddress($addressId)
    {
      $address     = new Address($addressId);
      $destination = $this->getAddressByPostalcode($address->postcode);

      if (!empty($address->vat_number)) {
        $addressNumber = $address->vat_number;
      }
      else {
        $addressNumber = preg_replace('/[^0-9]/', '', $address->address1);
        if (empty($addressNumber)) {
          $addressNumber = '1';
        }
      }

      if (empty($destination->district)) {
        $destination->district = '[CEP SEM BAIRRO]';
      }

      if (empty($destination->street)) {
        if (!empty($address->address1)) {
          $destination->street = $address->address1;
        }
        else {
          $destination->street = '[CEP SEM RUA]';
        }
      }

      return [
        'country'     => $destination->country,
        'state'       => $destination->state,
        'city'        => $destination->city,
        'district'    => $destination->district,
        'street'      => $destination->street,
        'number'      => $addressNumber,
        'postal_code' => $destination->id,
        'address'     => $destination->description
      ];
    }

    public function loadRetrieveAddress()
    {
      return [
        'country'     => Configuration::get('FC_COUNTRY_ORIGIN'),
        'state'       => Configuration::get('FC_STATE_ORIGIN'),
        'city'        => Configuration::get('FC_CITY_ORIGIN'),
        'district'    => Configuration::get('FC_DISTRICT_ORIGIN'),
        'street'      => Configuration::get('FC_STREET_ORIGIN'),
        'number'      => Configuration::get('FC_NUMBER_ORIGIN'),
        'postal_code' => preg_replace('/[^0-9]/', '', Configuration::get('FC_CEP_ORIGIN')),
        'address'     => Configuration::get('FC_STREET_ORIGIN'),
        'complement'  => Configuration::get('FC_COMPLEMENT_ORIGIN'),
      ];
    }

    public function hookDisplayProductAdditionalInfo($params)
    {
      if (Configuration::get('FC_INFO_PROD') != '1') {
        return false;
      }

      $this->context->smarty->assign('url_shipping_quote', $this->context->link->getModuleLink('freteclick', 'calcularfrete'));
      $this->context->smarty->assign('cep'               , $this->cookie->cep);

      return $this->context->smarty->fetch('module:freteclick/views/templates/hook/simularfrete.tpl');
    }

    public function hookDisplayShoppingCartFooter($params)
    {
        $langID     = $this->context->language->id;
        $products   = Context::getContext()->cart->getProducts();
        $prod_name  = false;
        $categories = [];

        foreach ($products as $prod) {
          $product      = new Product((int) $prod["id_product"], false, $langID);
          $categories[] = ucfirst(strtolower($product->category));
        }
        $prod_name = implode(',', array_unique($categories));

        if (Configuration::get('FC_SHOP_CART') != '1') {
            return false;
        }

        $this->context->smarty->assign('products'          , $products);
        $this->context->smarty->assign('url_shipping_quote', $this->context->link->getModuleLink('freteclick', 'calcularfrete'));
        $this->context->smarty->assign('cep'               , $this->cookie->cep);
        $this->context->smarty->assign('product_name'      , $prod_name);

        return $this->display(__FILE__, 'views/templates/hook/simularfrete_cart.tpl');
    }

    private function getConfigFieldsValues()
    {
        $values = array(
            'FC_CITY_ORIGIN' => Tools::getValue('FC_CITY_ORIGIN', Configuration::get('FC_CITY_ORIGIN')),
            'FC_CEP_ORIGIN' => Tools::getValue('FC_CEP_ORIGIN', Configuration::get('FC_CEP_ORIGIN')),
            'FC_STREET_ORIGIN' => Tools::getValue('FC_STREET_ORIGIN', Configuration::get('FC_STREET_ORIGIN')),
            'FC_NUMBER_ORIGIN' => Tools::getValue('FC_NUMBER_ORIGIN', Configuration::get('FC_NUMBER_ORIGIN')),
            'FC_COMPLEMENT_ORIGIN' => Tools::getValue('FC_COMPLEMENT_ORIGIN', Configuration::get('FC_COMPLEMENT_ORIGIN')),
            'FC_STATE_ORIGIN' => Tools::getValue('FC_STATE_ORIGIN', Configuration::get('FC_STATE_ORIGIN')),
            'FC_COUNTRY_ORIGIN' => Tools::getValue('FC_COUNTRY_ORIGIN', Configuration::get('FC_COUNTRY_ORIGIN')),
            'FC_DISTRICT_ORIGIN' => Tools::getValue('FC_DISTRICT_ORIGIN', Configuration::get('FC_DISTRICT_ORIGIN')),
            'FC_INFO_PROD' => Tools::getValue('FC_INFO_PROD', Configuration::get('FC_INFO_PROD')),
            'FC_SHOP_CART' => Tools::getValue('FC_SHOP_CART', Configuration::get('FC_SHOP_CART')),
            'FC_API_KEY' => Tools::getValue('FC_API_KEY', Configuration::get('FC_API_KEY')),
            'FC_FREE_SHIPPING_PRICE' => Tools::getValue('FC_FREE_SHIPPING_PRICE', Configuration::get('FC_FREE_SHIPPING_PRICE')),
            'FC_SHIPPING_DEADLINE' => Tools::getValue('FC_SHIPPING_DEADLINE', Configuration::get('FC_SHIPPING_DEADLINE'))
        );
        return $values;
    }

    private function _postProcess()
    {
        try {
            if (empty(Tools::getValue('FC_CITY_ORIGIN'))) {
                $this->addError('The city of origin field is required.');
            }
            if (empty(Tools::getValue('FC_CEP_ORIGIN'))) {
                $this->addError('The Zip Code field is required.');
            }
            if (empty(Tools::getValue('FC_STREET_ORIGIN'))) {
                $this->addError('The street origin field is required.');
            }
            if (empty(Tools::getValue('FC_NUMBER_ORIGIN'))) {
                $this->addError('The number origin field is required.');
            }
            if (empty(Tools::getValue('FC_DISTRICT_ORIGIN'))) {
                $this->addError('The district origin field is required.');
            }
            if (empty(Tools::getValue('FC_STATE_ORIGIN'))) {
                $this->addError('The state origin field is required.');
            }
            if (empty(Tools::getValue('FC_COUNTRY_ORIGIN'))) {
                $this->addError('The country origin field is required.');
            }
            Configuration::updateValue('FC_CITY_ORIGIN', Tools::getValue('FC_CITY_ORIGIN'));
            Configuration::updateValue('FC_CEP_ORIGIN', Tools::getValue('FC_CEP_ORIGIN'));
            Configuration::updateValue('FC_STREET_ORIGIN', Tools::getValue('FC_STREET_ORIGIN'));
            Configuration::updateValue('FC_COMPLEMENT_ORIGIN', Tools::getValue('FC_COMPLEMENT_ORIGIN'));
            Configuration::updateValue('FC_NUMBER_ORIGIN', Tools::getValue('FC_NUMBER_ORIGIN'));
            Configuration::updateValue('FC_DISTRICT_ORIGIN', Tools::getValue('FC_DISTRICT_ORIGIN'));
            Configuration::updateValue('FC_STATE_ORIGIN', Tools::getValue('FC_STATE_ORIGIN'));
            Configuration::updateValue('FC_COUNTRY_ORIGIN', Tools::getValue('FC_COUNTRY_ORIGIN'));
            Configuration::updateValue('FC_INFO_PROD', Tools::getValue('FC_INFO_PROD'));
            Configuration::updateValue('FC_SHOP_CART', Tools::getValue('FC_SHOP_CART'));
            Configuration::updateValue('FC_API_KEY', Tools::getValue('FC_API_KEY'));
            Configuration::updateValue('FC_FREE_SHIPPING_PRICE', Tools::getValue('FC_FREE_SHIPPING_PRICE'));
            Configuration::updateValue('FC_SHIPPING_DEADLINE', Tools::getValue('FC_SHIPPING_DEADLINE'));
            $this->_html .= $this->displayConfirmation($this->l('Configurações atualizadas'));
        } catch (Exception $ex) {
            $this->_postErrors[] = $ex->getMessage();
        }
    }

    public function hookextraCarrier($params)
    {
        $smarty = $this->smarty;
        $this->context->controller->addJS($this->_path . 'views/js/FreteClick.js');
        $arrSmarty = array(
            'display_name' => $this->displayName,
            'carrier_checked' => $params['cart']->id_carrier,
            'fc_carrier_id' => (int)(Configuration::get('FC_CARRIER_ID')),
            'url_transportadora' => $this->context->link->getModuleLink('freteclick', 'transportadora') . '?_=' . microtime()
        );

        try {
            if ($params['cart']->id_carrier == (int)(Configuration::get('FC_CARRIER_ID'))) {
                $arrPostFields = array(
                    'city-origin' => Configuration::get('FC_CITY_ORIGIN'),
                    'cep-origin' => Configuration::get('FC_CEP_ORIGIN'),
                    'street-origin' => Configuration::get('FC_STREET_ORIGIN'),
                    'address-number-origin' => Configuration::get('FC_NUMBER_ORIGIN'),
                    'complement-origin' => Configuration::get('FC_COMPLEMENT_ORIGIN') ?: "",
                    'district-origin' => Configuration::get('FC_DISTRICT_ORIGIN'),
                    'state-origin' => Configuration::get('FC_STATE_ORIGIN'),
                    'country-origin' => Configuration::get('FC_COUNTRY_ORIGIN'),
                    'product-type' => $this->getListProductsName(),
                    'product-total-price' => number_format($this->context->cart->getOrderTotal(), 2, ',', '.')
                );
                $arrSmarty['arr_transportadoras'] = $this->getTransportadoras($arrPostFields);

                $arrSmarty['quote_id'] = (isset($this->cookie->quote_id) ? $this->cookie->quote_id : null);
                $smarty->assign($arrSmarty);
            }
        } catch (Exception $ex) {
            $arrSmarty['error_message'] = $ex->getMessage();
            $smarty->assign($arrSmarty);
        }
        return $this->display(__FILE__, 'views/templates/hook/order_shipping.tpl');
    }

    /**
     * Hook that will run when finalizing an order
     */
    public function hookOrderConfirmation($params)
    {
      $shippingNumber = $params['order']->getWsShippingNumber();

      if (empty($shippingNumber)) {
        $orderCart  = new Cart($params['order']->id_cart);
        $simulation = $this->getCartSimulation($orderCart);

        if (!empty($simulation)) {
          if ($simulation->quotes) {
            $shippingNumber = $simulation->id;

            $quotes = $this->orderByPrice($simulation->quotes);
            if (isset($quotes[0])) {
              $shippingNumber .= '-' . $quotes[0]->id;
            }

            $params['order']->setWsShippingNumber($shippingNumber);
            $params['order']->save();

            // clear cart cache

            $this->clearCartCache($orderCart);
          }
        }
      }
    }

    private function getListProductsName()
    {
        $arrProductsName = array();
        foreach ($this->context->cart->getProducts() as $product) {
            $arrProductsName[] = ucfirst(strtolower($product['category']));
        }
        return implode(",", array_unique($arrProductsName));
    }

    public function quote($data)
    {
      try {
        if (!isset($data->destination)) {
            throw new Exception('Estão faltando os dados de destino');
        }

        $simulation = $this->getQuoteSimulation([
          'cart'        => Context::getContext()->cart,
          'total'       => $data->productTotalPrice,
          'packages'    => $data->packages,
          'categories'  => $data->productType,
          'destination' => $data->destination->cep,
        ]);

        return json_encode([
          'response' => [
              'success' => true,
              'data'    => $simulation,
          ]
        ]);
      }
      catch (Exception $ex) {
        return json_encode([
            'response' => [
                'success' => false,
                'error'   => $ex->getMessage()
            ]
        ]);
      }
    }

    public function getCartPackages($cart)
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

    public function getSimulationId(array $payload)
    {
      $data = [
        'origin'      => $payload['origin'],
        'destination' => $payload['destination'],
        'packages'    => $payload['packages']
      ];

      return md5(json_encode($data));
    }

    public function getCartSimulation($cart)
    {
      if (!isset($_SESSION)) {
          session_start();
      }

      if (!empty($cart->id)) {
        $cartId = md5('cart'.$cart->id);

        if (isset($_SESSION[$cartId])) {
          $simulationId = $_SESSION[$cartId];

          if (isset($_SESSION[$simulationId])) {
            return $_SESSION[$simulationId];
          }
        }
      }

      return null;
    }

    public function clearCartCache($cart)
    {
      if (!isset($_SESSION)) {
        session_start();
      }

      $cartId  = md5('cart'.$cart->id);
      $quoteId = $_SESSION[$cartId];

      unset($_SESSION[$cartId ]);
      unset($_SESSION[$quoteId]);

      return true;
    }

    public function getQuoteSimulation(array $data)
    {
      try {

        if (!isset($_SESSION)) {
          session_start();
        }

        $destination = $this->getAddressByPostalcode($data['destination']);
        if ($destination === null) {
          throw new Exception('CEP não encontrado');
        }

        $payload = [
          'productTotalPrice' => $data['total'],
          'packages'          => $data['packages'],
          'productType'       => $data['categories'],
          'destination'       => [
            'city'    => $destination->city,
            'state'   => $destination->state,
            'country' => $destination->country
          ],
          'origin'            => [
            'city'    => Configuration::get('FC_CITY_ORIGIN'),
            'state'   => Configuration::get('FC_STATE_ORIGIN'),
            'country' => Configuration::get('FC_COUNTRY_ORIGIN')
          ]
        ];

        $simulationId = $this->getSimulationId($payload);

        // always save last simulation request in cart

        if (isset($data['cart']) && !empty($data['cart']->id)) {
          $_SESSION[md5('cart'.$data['cart']->id)] = $simulationId;
        }

        if (isset($_SESSION[$simulationId])) {
          return $_SESSION[$simulationId];
        }

        // set shop owner as pickup

        $owner = $this->People->getMe();
        if (isset($owner->companyId) && isset($owner->peopleId)) {
          $payload['pickup'] = [
            'people'  => $owner->companyId,
            'address' => $this->loadRetrieveAddress(),
            'contact' => $owner->peopleId,
          ];
        }

        // set customer as delivery

        if (isset($data['cart']) && !empty($data['cart']->id_customer)) {
          $customer = new Customer($data['cart']->id_customer);
          $address  = new Address($data['cart']->id_address_delivery);

          if (!empty($customer->email)) {
            $customerId = $this->People->getIdByEmail($customer->email);
            $deliveryTo = $this->loadDeliveryAddress($address->id);

            if ($customerId === null) {
              $customerId = $this->People->createCustomer([
                'name'    => $customer->firstname,
                'alias'   => $customer->lastname,
                'type'    => 'F',
                'email'   => $customer->email,
                'address' => $deliveryTo
              ]);

              if ($customerId === null) {
                throw new \Exception('Customer was not created');
              }
            }

            $payload['delivery'] = [
              'people'  => $customerId,
              'address' => $deliveryTo,
              'contact' => $customerId,
            ];
          }
        }

        $simulation = $this->Quote->simulate($payload);

        return $_SESSION[$simulationId] = $simulation;

      } catch (\Exception $e) {

        if (empty($data['cart']->id) === false) {
          $this->clearCartCache($data['cart']);
        }

        throw new \Exception($e->getMessage());
      }
    }

    public function getTransportadoras($postFields)
    {
        $langID = $this->context->language->id;
        foreach ($this->context->cart->getProducts() as $key => $product) {
            $postFields['product-package'][$key]['qtd'] = $product['cart_quantity'];
            $postFields['product-package'][$key]['weight'] = number_format($product['weight'], 10, ',', '');
            $postFields['product-package'][$key]['height'] = number_format($product['height'] / 100, 10, ',', '');
            $postFields['product-package'][$key]['width'] = number_format($product['width'] / 100, 10, ',', '');
            $postFields['product-package'][$key]['depth'] = number_format($product['depth'] / 100, 10, ',', '');
        }
        $address = new Address((int)$this->context->cart->id_address_delivery);

        $postFields['cep-destination'] = $address->postcode;
        $postFields['city-destination'] = $address->city;
        $postFields['street-destination'] = preg_replace('/[^A-Z a-z]/', '', preg_replace(array("/(á|à|ã|â|ä)/", "/(Á|À|Ã|Â|Ä)/", "/(é|è|ê|ë)/", "/(É|È|Ê|Ë)/", "/(í|ì|î|ï)/", "/(Í|Ì|Î|Ï)/", "/(ó|ò|õ|ô|ö)/", "/(Ó|Ò|Õ|Ô|Ö)/", "/(ú|ù|û|ü)/", "/(Ú|Ù|Û|Ü)/", "/(ñ)/", "/(Ñ)/"), explode(" ", "a A e E i I o O u U n N"), $address->address1));
        $postFields['address-number-destination'] = preg_replace('/[^0-9]/', '', $address->address1);
        $postFields['complement-destination'] = "";
        $postFields['district-destination'] = $address->address2 ?: 'Bairro não informado';

        $estados = State::getStates($langID, true);
        $iso_estado = "";
        foreach ($estados as $key => $estado) {
            if ($estado["id_state"] == $address->id_state) {
                $iso_estado = $estado["iso_code"];
                break;
            }
        }
        $postFields['state-destination'] = $iso_estado;
        $postFields['country-destination'] = "Brasil";
        $arrJson = json_decode($this->orderByPrice($this->quote($postFields)), false);

        if ($arrJson->response->success === false || $arrJson->response->data === false) {
            $this->addError('Nenhuma transportadora disponível.');
        } else {
            $this->cookie->fc_data = $postFields;
            $this->cookie->fc_quotes = $arrJson;
            $this->cookie->write();
        }
        return $arrJson;
    }

    public function orderByPrice($quotes)
    {
        if (isset($quotes->quotes)) {
            usort($quotes->quotes, function ($a, $b) {
                return $a->total > $b->total;
            });
        }
        return $quotes;
    }

    public function getErrors()
    {
        return self::$error ? array(
            'response' => array(
                'data' => 'false',
                'count' => 0,
                'success' => false,
                'error' => self::$error
            )
        ) : false;
    }

    public function addError($error)
    {
        self::$error[] = array(
            'code' => md5($error),
            'message' => $error
        );
        return $this->getErrors();
    }

    public function isFreeShipping(float $totalPrice): bool
    {
        $freePrice = $this->getFreeShippingPrice();
        if ($freePrice === false)
            return false;

        return $totalPrice >= $freePrice;
    }

    public function getFreeShippingPrice()
    {
        $price = Configuration::get('FC_FREE_SHIPPING_PRICE');
        if (!is_numeric($price))
            return false;

        return (float) $price;
    }
}
