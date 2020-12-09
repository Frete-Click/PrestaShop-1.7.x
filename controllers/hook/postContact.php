<?php


class FreteClickPostContactController
{
    public function __construct($module, $file, $path) {
        $this->file = $file;
        $this->module = $module;
        $this->context = Context::getContext();
        $this->_path = $path;
    }

    public function run($contact) {
        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.freteclick.com.br/people/contact",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>json_encode($contact),
                CURLOPT_HTTPHEADER => array(
                    "authority: api.freteclick.com.br",
                    "accept: application/ld+json",
                    "api-token: f7bd441d867fd1eb15ec371a34aa9e0f",
                    "user-agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.83 Safari/537.36",
                    "content-type: application/ld+json",
                    "origin: https://cotafacil.freteclick.com.br",
                    "sec-fetch-site: same-site",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://cotafacil.freteclick.com.br/quote",
                    "accept-language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7"
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
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