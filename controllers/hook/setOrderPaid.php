<?php


class FreteClickSetOrderPaidController
{
    public function __construct($module, $file, $path) {
        $this->file = $file;
        $this->module = $module;
        $this->context = Context::getContext();
        $this->_path = $path;
    }

    public function run($payload) {
        try {
            error_log('setOrderPaid:payload='.json_encode($payload));
            $apiKey = Configuration::get('FC_API_KEY');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.freteclick.com.br/presta-shop/order/paid?api-key={$apiKey}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept' => 'application/json',
                'content-type' => 'application/ld+json',
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $response = json_decode($response, false);

            if (!isset($response->response->data->id)) {
                throw new Exception('Cep não encontrado!');
            }
            return $response->response->data;

        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return null;
    }
}