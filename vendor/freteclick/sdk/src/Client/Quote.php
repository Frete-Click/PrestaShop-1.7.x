<?php

namespace SDK\Client;

use SDK\Core\Client\API;
use SDK\Exception\FCClientException;
use GuzzleHttp\Exception\ClientException;

class Quote
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

  public function simulate(array $data)
  {
    try {

      $response = $this->api->private('POST', '/quotes', ['json' => $data]);

      if ($response->getStatusCode() === 200) {
        $result = json_decode($response->getBody());

        if (isset($result->response)) {
          if ($result->response->success === false)
            throw new FCClientException($result->response->error);

          return $result->response->data->order;
        }
      }

      return null;

    } catch (\Exception $e) {
      if ($e instanceof FCClientException)
        throw new \Exception($e->getMessage());

      if ($e instanceof ClientException && $e->hasResponse()) {
        $response = $e->getResponse();

        if ($response->getStatusCode() === 400) {
          $contents = $response->getBody() !== null ? json_decode($response->getBody()->getContents()) : null;

          throw new \Exception($contents->detail);
        }
      }

      return null;
    }
  }
}
