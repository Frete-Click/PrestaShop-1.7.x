<?php

namespace SDK\Client;

use SDK\Core\Client\API;

class Address
{
  /**
   * API Service
   *
   * @var API
   */
  private $api;

  public function __construct(API $api)
  {
    $this->api = $api;
  }

  public function getAddressByCEP(string $cep)
  {
    try {

      $response = $this->api->private('GET', sprintf('/cep_address/%s', $cep));

      if ($response->getStatusCode() === 200) {
        $result = json_decode($response->getBody());

        if (isset($result->id))
          return $result;
      }

      return null;

    } catch (\Exception $e) {
      return null;
    }
  }
}
