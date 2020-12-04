<?php
require_once(dirname(__FILE__).'./vendor/autoload.php');

use PrestaShop\PrestaShop\Core\Addon\Module\ModuleManager;
use PrestaShop\PrestaShop\Core\Addon\Module\ModuleManagerBuilder;
use freteclick\SDK;

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

    public function __construct()
    {
        $this->module_key = '787992febc148fba30e5885d08c14f8c';
        $this->cookie = new Cookie('Frete Click');
        $this->cookie->setExpire(time() + 20 * 60);

        $this->name = 'freteclick';
        $this->tab = 'shipping_logistics';
        $this->version = '2.0.3';
        $this->author = 'Frete Click';

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
        $this->api_url = 'https://api.freteclick.com.br/quotes';
        $this->url_shipping_quote = '/sales/shipping-quote.json';
        $this->url_city_origin = '/carrier/search-city-origin.json';
        $this->url_city_destination = '/carrier/search-city-destination.json';
        $this->url_search_city_from_cep = '/carrier/search-city-from-cep.json';
        $this->url_choose_quote = '/sales/choose-quote.json';
        $this->url_api_correios = '/calculador/CalcPrecoPrazo.asmx?WSDL';
        $this->url_add_quote_destination_client = '/sales/add-quote-destination-client.json';
        $this->url_add_quote_origin_company = '/sales/add-quote-origin-company.json.json';
    }


    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $carrier = $this->addCarrier('Frete Click');
        Configuration::updateValue('FC_CARRIER_ID', (int)$carrier->id);

//        $this->addZones($carrier);
//        $this->addGroups($carrier);
//        $this->addRanges($carrier);
        Configuration::updateValue('FRETECLICK_LIVE_MODE', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('updateCarrier') &&
//            $this->registerHook('actionCarrierUpdate') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('displayCarrierList') &&
            $this->registerHook('displayProductAdditionalInfo') &&
            $this->registerHook('displayShoppingCartFooter') &&
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
//            $this->unregisterHook('actionCarrierUpdate') &&
            $this->unregisterHook('actionPaymentConfirmation') &&
            $this->unregisterHook('displayCarrierList') &&
            $this->unregisterHook('updateCarrier') &&
            $this->unregisterHook('extraCarrier') &&
            $this->unregisterHook('displayProductActions') &&
            $this->unregisterHook('OrderConfirmation') &&
            $this->unregisterHook('displayShoppingCartFooter');
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

//        $this->context->smarty->assign('module_dir', $this->_path);
//
//        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
//
//        return $output . $this->renderForm();


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
//        $helper->default_form_language = $lang->id;
        $helper->default_form_language = $this->context->language->id;
//        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
//        $helper->submit_action = 'submitFreteclickModule';
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
//        return array(
//            'form' => array(
//                'legend' => array(
//                    'title' => $this->l('Settings'),
//                    'icon' => 'icon-cogs',
//                ),
//                'input' => array(
//                    array(
//                        'type' => 'switch',
//                        'label' => $this->l('Live mode'),
//                        'name' => 'FRETECLICK_LIVE_MODE',
//                        'is_bool' => true,
//                        'desc' => $this->l('Use this module in live mode'),
//                        'values' => array(
//                            array(
//                                'id' => 'active_on',
//                                'value' => true,
//                                'label' => $this->l('Enabled')
//                            ),
//                            array(
//                                'id' => 'active_off',
//                                'value' => false,
//                                'label' => $this->l('Disabled')
//                            )
//                        ),
//                    ),
//                    array(
//                        'col' => 3,
//                        'type' => 'text',
//                        'prefix' => '<i class="icon icon-envelope"></i>',
//                        'desc' => $this->l('Enter a valid email address'),
//                        'name' => 'FRETECLICK_ACCOUNT_EMAIL',
//                        'label' => $this->l('Email'),
//                    ),
//                    array(
//                        'type' => 'password',
//                        'name' => 'FRETECLICK_ACCOUNT_PASSWORD',
//                        'label' => $this->l('Password'),
//                    ),
//                ),
//                'submit' => array(
//                    'title' => $this->l('Save'),
//                ),
//            ),
//        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'FRETECLICK_LIVE_MODE' => Configuration::get('FRETECLICK_LIVE_MODE', true),
            'FRETECLICK_ACCOUNT_EMAIL' => Configuration::get('FRETECLICK_ACCOUNT_EMAIL', 'contact@prestashop.com'),
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

    public function getHookController($hook_name)
    {
        require_once(dirname(__FILE__) . '/controllers/hook/' . $hook_name . '.php');
        $controller_name = $this->name . $hook_name . 'Controller';
        $controller = new $controller_name($this, __FILE__, $this->_path);
        return $controller;
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        $controller = $this->getHookController('getOrderShippingCost');
        return $controller->run($params, $shipping_cost);

        if (!$this->cookie->fc_valorFrete) {
            $total = 0;
            foreach ($this->context->cart->getProducts() as $product) {
                $total += $product['total'];
            }

            if ($total >= 300) {
                return 0;
            }

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
                'product-total-price' => number_format($total, 2, ',', '.')
            );
            $this->getTransportadoras($arrPostFields);
        }
        return (isset($this->cookie->fc_valorFrete) ? $this->cookie->fc_valorFrete : 0);
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

//    /**
//     * Add the CSS & JavaScript files you want to be loaded in the BO.
//     */
//    public function hookBackOfficeHeader()
//    {
//        if (Tools::getValue('module_name') == $this->name) {
//            $this->context->controller->addJS($this->_path . 'views/js/back.js');
//            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
//        }
//    }

//    /**
//     * Add the CSS & JavaScript files you want to be added on the FO.
//     */
//    public function hookHeader()
//    {
//        $this->context->controller->addJS($this->_path . '/views/js/front.js');
//        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
//    }

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

//    public function hookActionCarrierUpdate()
//    {
//        /* Place your code here. */
//    }

    public function hookActionPaymentConfirmation($params)
    {
        $order = new Order($params['id_order']);
        $orderCarrier = new OrderCarrier($order->getIdOrderCarrier());
        try {
            $customer = new Customer($params['cart']->id_customer);

            $controller = $this->getHookController('getContact');
            $DeliveryPeople = $controller->run($customer->email);

            if (!$DeliveryPeople) {
                $controller = $this->getHookController('postContact');
                $paramsPeople = [
                    "name" => $customer->firstname . ' ' . $customer->lastname,
                    "email" => $customer->email,
                    "phone" => '(00) 90000-0000',
                    "document" => '22121212121'
                ];
                $DeliveryPeople = $controller->run($paramsPeople);
            }


            $orderId = substr($orderCarrier->tracking_number, 0, strpos($orderCarrier->tracking_number, '.'));
            $quoteId = substr($orderCarrier->tracking_number, (strpos($orderCarrier->tracking_number, '.') + 1));

            $params = [
                "quote" => $quoteId,
                "price" => $orderCarrier->shipping_cost_tax_incl,
                "retrieve" => [
                    "address" => $this->loadRetrieveAddress()
                ],
                "delivery" => [
                    "id" => $DeliveryPeople->people_id,
                    "contact" => $DeliveryPeople->people_id,
                    "address" => $this->loadDeliveryAddress($params['cart'])
                ],
            ];

            $order->setWsShippingNumber($orderId);
            $order->save();

            $controller = $this->getHookController('setOrderPaid');
            $result = $controller->run($params);
        } catch
        (Exception $e) {
            error_log($e->getMessage());
        }
        return true;
    }

    public function loadDeliveryAddress($cart)
    {
        $address = new Address($cart->id_address_delivery);
        $destination = self::getAddressByCep($address->postcode);

        $arr = explode(' ', $address->address1);
        $number = $arr[count($arr) - 1];

        return [
            'country' => $destination->response->data[0]->country,
            'state' => $destination->response->data[0]->state,
            'city' => $destination->response->data[0]->city,
            'district' => $destination->response->data[0]->district,
            'street' => $destination->response->data[0]->street,
            'number' => $number,
            'postal_code' => $destination->response->data[0]->postal_code,
            'address' => $destination->response->data[0]->description,
        ];
    }

    public function loadRetrieveAddress()
    {
        return [
            'country' => Configuration::get('FC_COUNTRY_ORIGIN'),
            'state' => Configuration::get('FC_STATE_ORIGIN'),
            'city' => Configuration::get('FC_CITY_ORIGIN'),
            'district' => Configuration::get('FC_DISTRICT_ORIGIN'),
            'street' => Configuration::get('FC_STREET_ORIGIN'),
            'number' => Configuration::get('FC_NUMBER_ORIGIN'),
            'postal_code' => Configuration::get('FC_CEP_ORIGIN'),
            'address' => Configuration::get('FC_STREET_ORIGIN'),
            'complement' => Configuration::get('FC_COMPLEMENT_ORIGIN'),
        ];
    }

    public function hookDisplayCarrierList()
    {
        /* Place your code here. */
    }

    public function hookDisplayProductAdditionalInfo($params)
//    public function hookDisplayRightColumnProduct($params)
    {
        $product = new Product((int)Tools::getValue('id_product'));
        $product_carriers = $product->getCarriers();
//        $carriers = Carrier::getCarriers($this->cookie->id_lang, true, false, false, null,
//            PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);

        $num_car = count($product_carriers);
        $fc_is_active = $num_car == 0 ? true : false;

//        if ($num_car > 0) {
//            foreach ($product_carriers as $key => $carrier) {
//                if ($carrier["external_module_name"] === "freteclick") {
//
//                    $fc_is_active = $carrier["active"];
//                    break;
//                }
//            }
//        } else {
//            $fc_is_active = false;
//            foreach ($carriers as $key => $carrier) {
//                if ($carrier["external_module_name"] === "freteclick") {
//                    $fc_is_active = true;
//                    break;
//                }
//            }
//        }
        $smarty = $this->context->smarty;

        if (Configuration::get('FC_INFO_PROD') != '1' || !$fc_is_active) {
            return false;
        }

//        if ('product' === $this->context->controller->php_self) {
//            $this->context->controller->registerStylesheet(
//                'module-modulename-style',
//                'modules/'.$this->name.'/css/modulename.css',
//                [
//                    'media' => 'all',
//                    'priority' => 200,
//                ]
//            );

//            $this->context->controller->addJS($this->_path . 'views/js/FreteClick.js');
//        $this->context->controller->addJS($this->_path . '/views/js/FreteClick.js');
//        $this->context->controller->addCSS($this->_path . '/views/css/FreteClickcss');
//        $this->context->controller->registerJavascript(
//            'modules-freteclick-lib',
//            'modules/' . $this->name . '/views/js/FreteClick.js'
//        );
//        }

        $smarty->assign('cep_origin', Configuration::get('FC_CEP_ORIGIN'));
        $smarty->assign('street_origin', Configuration::get('FC_STREET_ORIGIN'));
        $smarty->assign('number_origin', Configuration::get('FC_NUMBER_ORIGIN'));
        $smarty->assign('complement_origin', Configuration::get('FC_COMPLEMENT_ORIGIN'));
        $smarty->assign('district_origin', Configuration::get('FC_DISTRICT_ORIGIN'));
        $smarty->assign('city_origin', Configuration::get('FC_CITY_ORIGIN'));
        $smarty->assign('state_origin', Configuration::get('FC_STATE_ORIGIN'));
        $smarty->assign('country_origin', Configuration::get('FC_COUNTRY_ORIGIN'));
        $smarty->assign('url_shipping_quote', $this->context->link->getModuleLink('freteclick', 'calcularfrete'));
        $smarty->assign('url_city_destination', $this->context->link->getModuleLink('freteclick', 'citydestination'));
        $smarty->assign('url_city_origin', $this->context->link->getModuleLink('freteclick', 'cityorigin'));
        $smarty->assign('cep', $this->cookie->cep);
        return $this->context->smarty->fetch('module:freteclick/views/templates/hook/simularfrete.tpl');
    }

    public function hookdisplayShoppingCartFooter($params)
    {
        $langID = $this->context->language->id;
        $products = Context::getContext()->cart->getProducts();

//        $context = Context::getContext();
//        $id_lang = $context->language->id;
//        $ca'rriers = Carrier::getCarriers($id_lang, true, false, false, null,
//            PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);
//        $fc_is_active = false;
//        foreach ($carriers as $key => $carrier) {
//            if ($carrier["external_module_name"] === "freteclick") {
//                $fc_is_active = true;
//                break;
//            }
//        }

        $prod_name = false;

//        if ($fc_is_active === true) {
        foreach ($products as $key => $prod) {
            $product = new Product((int)$prod["id_product"], false, $langID);
            if (!$prod_name) {
                $prod_name = $product->name;
            }
//                $product_carriers = $product->getCarriers();
//                $num_car = count($product_carriers);
//                if ($num_car > 0) {
//                    $fc_is_active = false;
//                    foreach ($product_carriers as $key2 => $carrier) {
//                        if ($carrier["external_module_name"] === "freteclick") {
//                            $fc_is_active = $carrier["active"];
//                            break;
//                        }
//                    }
//                    if ($fc_is_active === false) {
//                        break;
//                    }
//                }
        }
//        }

        $smarty = $this->smarty;

        if (Configuration::get('FC_SHOP_CART') != '1') {
            return false;
        }

//        $this->context->controller->addJS($this->_path . 'views/js/FreteClick.js');
        $smarty->assign('products', $products);
        $smarty->assign('cep_origin', Configuration::get('FC_CEP_ORIGIN'));
        $smarty->assign('street_origin', Configuration::get('FC_STREET_ORIGIN'));
        $smarty->assign('number_origin', Configuration::get('FC_NUMBER_ORIGIN'));
        $smarty->assign('complement_origin', Configuration::get('FC_COMPLEMENT_ORIGIN'));
        $smarty->assign('district_origin', Configuration::get('FC_DISTRICT_ORIGIN'));
        $smarty->assign('city_origin', Configuration::get('FC_CITY_ORIGIN'));
        $smarty->assign('state_origin', Configuration::get('FC_STATE_ORIGIN'));
        $smarty->assign('country_origin', Configuration::get('FC_COUNTRY_ORIGIN'));
        $smarty->assign('url_shipping_quote', $this->context->link->getModuleLink('freteclick', 'calcularfrete'));
        $smarty->assign('cep', $this->cookie->cep);
        $smarty->assign('product_name', $prod_name);
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
        session_start();
        $order = $_SESSION[$_SESSION['lastQuote']];
        $shippingNumber = $order->id;
        $carrier = new Carrier($params['order']->id_carrier);
        foreach ($order->quotes as $quote) {
            if ($quote->carrier->name === $carrier->name) {
                $shippingNumber .= '.' . $quote->id;
                break;
            }
        }

        $params['order']->setWsShippingNumber($shippingNumber);
        $params['order']->save();
//        $this->addQuoteOriginCompany($params['objOrder']);
//        $this->addQuoteDestinationClient($params['objOrder']);
    }

//    private function addQuoteDestinationClient($order)
//    {
//        $langID = $this->context->language->id;
//        $data = array();
//        $address = new Address((int)$order->id_address_delivery);
//        $customer = new Customer($order->id_customer);
//        $data['quote'] = $this->cookie->quote_id;
//        if ($address->company) {
//            $data['choose-client'] = 'company';
//            $data['company-document'] = '44.444.444/4444-44';
//            $data['company-alias'] = $address->company;
//            $data['company-name'] = $address->company;
//        } else {
//            $data['choose-client'] = 'client';
//            $data['client-document'] = '444.444.444-44';
//        }
//        $data['email'] = $customer->email;
//        $data['contact-name'] = $address->firstname . ' ' . $address->lastname;
//        $data['ddd'] = Tools::substr(preg_replace('/[^0-9]/', '', $address->phone), 2);
//        $data['phone'] = Tools::substr(preg_replace('/[^0-9]/', '', $address->phone), -9);
//        $data['address-nickname'] = $address->alias;
//        $data['cep'] = $address->postcode;
//        $data['street'] = preg_replace('/[^A-Z a-z]/', '', preg_replace(array("/(á|à|ã|â|ä)/", "/(Á|À|Ã|Â|Ä)/", "/(é|è|ê|ë)/", "/(É|È|Ê|Ë)/", "/(í|ì|î|ï)/", "/(Í|Ì|Î|Ï)/", "/(ó|ò|õ|ô|ö)/", "/(Ó|Ò|Õ|Ô|Ö)/", "/(ú|ù|û|ü)/", "/(Ú|Ù|Û|Ü)/", "/(ñ)/", "/(Ñ)/"), explode(" ", "a A e E i I o O u U n N"), $address->address1));
//        $data['address-number'] = preg_replace('/[^0-9]/', '', $address->address1);
//        $data['complement'] = "";
//        $data['district'] = $address->address2;
//        $data['city'] = $address->city;
//
//        $estados = State::getStates($langID, true);
//        $iso_estado = "";
//        foreach ($estados as $key => $estado) {
//            if ($estado["id_state"] == $address->id_state) {
//                $iso_estado = $estado["iso_code"];
//                break;
//            }
//        }
//        $data['state'] = $iso_estado;
//        $data['country'] = $address->country;
//
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, $this->api_url . $this->url_add_quote_destination_client . '?api-key=' . Configuration::get('FC_API_KEY'));
//        curl_setopt($ch, CURLOPT_POST, true);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
//        $resp = curl_exec($ch);
//        curl_close($ch);
//        return $resp;
//    }

//    private function addQuoteOriginCompany($order)
//    {
//        $data = array();
//        $data['quote'] = $this->cookie->quote_id;
//        $data['contact-name'] = Configuration::get('PS_SHOP_NAME');
//        $data['email'] = Configuration::get('PS_SHOP_EMAIL');
//        $data['ddd'] = Tools::substr(preg_replace('/[^0-9]/', '', Configuration::get('PS_SHOP_PHONE')), 2);
//        $data['phone'] = Tools::substr(preg_replace('/[^0-9]/', '', Configuration::get('PS_SHOP_PHONE')), -9);
//        $data['address-nickname'] = "Endereço Principal";
//        $data['cep'] = Configuration::get('FC_CEP_ORIGIN');
//        $data['street'] = preg_replace('/[^A-Z a-z]/', '', preg_replace(array("/(á|à|ã|â|ä)/", "/(Á|À|Ã|Â|Ä)/", "/(é|è|ê|ë)/", "/(É|È|Ê|Ë)/", "/(í|ì|î|ï)/", "/(Í|Ì|Î|Ï)/", "/(ó|ò|õ|ô|ö)/", "/(Ó|Ò|Õ|Ô|Ö)/", "/(ú|ù|û|ü)/", "/(Ú|Ù|Û|Ü)/", "/(ñ)/", "/(Ñ)/"), explode(" ", "a A e E i I o O u U n N"), Configuration::get('FC_STREET_ORIGIN')));
//        $data['address-number'] = Configuration::get('FC_NUMBER_ORIGIN');
//        $data['complement'] = Configuration::get('FC_COMPLEMENT_ORIGIN') ?: "";
//        $data['district'] = Configuration::get('FC_DISTRICT_ORIGIN');
//        $data['city'] = Configuration::get('FC_CITY_ORIGIN');
//        $data['state'] = Configuration::get('FC_STATE_ORIGIN');
//        $data['country'] = Configuration::get('FC_COUNTRY_ORIGIN');
//
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, $this->api_url . $this->url_add_quote_origin_company . '?api-key=' . Configuration::get('FC_API_KEY'));
//        curl_setopt($ch, CURLOPT_POST, true);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
//        $resp = curl_exec($ch);
//        curl_close($ch);
//        return $resp;
//    }

    private function getListProductsName()
    {
        $arrProductsName = array();
        foreach ($this->context->cart->getProducts() as $product) {
            $arrProductsName[] = $product['name'];
        }
        return implode(", ", $arrProductsName);
    }

    public function quote($data)
    {
        try {
            session_start();
            $this->cookie->fc_cep = $data->destination->cep;
            $_SESSION['cep'] = $data->destination->cep;
//            $this->cookie->fc_cep = Tools::getValue('cep');

            if (!isset($data->destination)) {
                throw new Exception('Error!');
            }

            $destination = self::getAddressByCep($data->destination->cep);

            if (!isset($destination->response->data[0])) {
                throw new Exception('Cep não encontrado!');
            }

            $data->destination->city = $destination->response->data[0]->city;
            $data->destination->country = $destination->response->data[0]->country;
            $data->destination->state = $destination->response->data[0]->state;

            if ($data->productType === '') {
                $data->productType = 'Material de Escritório';
            }

            $resp = self::getQuotes(Configuration::get('FC_API_KEY'), json_encode($data));

            if (!isset($resp->id)) {
                throw new Exception('Error!');
            }

            $arrJson = $this->orderByPrice($resp);

            if (!$this->cookie->fc_valorFrete) {
                $this->cookie->fc_valorFrete = $arrJson->quotes[0]->total;
            }

            $context = Context::getContext();
            $id_lang = $context->language->id;

            $carriers = Carrier::getCarriers($id_lang, true, false, false, null,
                PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);

            $this->cookie->fc_order_id = $arrJson->id;

            foreach ($arrJson->quotes as $key => $quote) {
                $quote_price = number_format($quote->total, 2, ',', '.');
                $arrJson->quotes[$key]->raw_total = $quote->total;
                $arrJson->quotes[$key]->total = "R$ {$quote_price}";

                $count = 0;
                foreach ($carriers as $carrier) {
                    if ($carrier['name'] === $quote->carrier->name) {
                        $count++;
                    }
                }
//                if ($count === 0) {
//                    $quote->carrier->image = 'https://' . str_replace('api.', 'app.', $quote->carrier->image);

//                    $this->addCarrier($quote->carrier->name, $quote->carrier->image);
//                }
            }

            $this->cookie->fc_valorFrete = $arrJson->quotes[0]->raw_total;
            return json_encode($arrJson);

        } catch (Exception $ex) {
            return json_encode(['response' => array('success' => false, 'error' => $ex->getMessage())]);
        }
    }

    public static function getQuotes($apiKey, $body)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.freteclick.com.br/quotes');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept:application/json',
                'Content-Type:application/json',
                'api-token:' . $apiKey,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $response = json_decode($response, false);

            if ($response && $response->response && $response->response->data && $response->response->data->order) {
                return $response->response->data->order;
            }
        } catch (\Exception $e) {
            die($e->getMessage());

            //return null;
        }

        return null;
    }

    public static function getAddressByCep($cep)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.freteclick.com.br/geo_places?input=' . $cep);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept' => 'application/json',
                'content-type' => 'application/ld+json',
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            return json_decode($response, false);

        } catch (\Exception $e) {
            self::writeLog($e->getMessage());
        }

        return null;
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
//            $this->cookie->fc_valorFrete = $arrJson->response->data->quote[0]->raw_total;
//            $this->cookie->delivery_order_id = $arrJson->response->data->id;
//            $this->cookie->write();

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
//            $arrJson->response->data->quote = $quotes;
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
}
