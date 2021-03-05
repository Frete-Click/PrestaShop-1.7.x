<?php

namespace SDK\Client;

use SDK\Core\Client\API;
use SDK\Exception\FCClientException;

class Order
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

  public function finishCheckout(int $orderId, array $data)
  {
    try {

      $endpoint = sprintf('/purchasing/orders/%s/choose-quote', $orderId);
      $response = $this->api->private('PUT', $endpoint, ['json' => $data]);

      if ($response->getStatusCode() === 200) {
        $result = json_decode($response->getBody());

        if (empty($result) === false)
          return true;
      }

      return null;

    } catch (\Exception $e) {
      if ($e instanceof FCClientException)
        throw new \Exception($e->getMessage());

      return null;
    }
  }

  public function getQuotationTotal(int $quotationId)
  {
    try {

      $response = $this->api->private('GET', sprintf('/quotations/%s', $quotationId));

      if ($response->getStatusCode() === 200) {
        $result = json_decode($response->getBody());

        if (empty($result) === false) {
          return ((float) $result->total);
        }
      }

      return null;

    } catch (\Exception $e) {
      if ($e instanceof FCClientException)
        throw new \Exception($e->getMessage());

      return null;
    }
  }
}
