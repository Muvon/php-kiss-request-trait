<?php
namespace Muvon\KISS;

use Throwable;

trait RequestTrait {
  protected int $request_connect_timeout = 5;
  protected int $request_timeout = 12;
  protected int $request_keepalive = 20;
  protected bool $request_json = true;

  protected function request(string $url, array $payload = [], string $method = 'POST', array $headers = []): array {
    $get_params = [];
    if ($method === 'GET') {
      $get_params = array_merge($get_params, $payload);
    }

    $url .= '?' . http_build_query($get_params, false, '&');
    $ch = curl_init($url);

    if ($this->request_json) {
      array_push($headers, 'Content-type: application/json', 'Accept: application/json');
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->request_connect_timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->request_timeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_ACCEPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, $this->request_keepalive);
    if ($method === 'POST') {
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->request_json ? json_encode($payload) : http_build_query($payload, false, '&'));
    }
    try {
      $err_code = curl_error($ch);
      if ($err_code) {
        switch ($err_code) {
          case 28:
            $err = 'e_request_timedout';
            break;
          default:
            $err = 'e_request_failed';
            break;
        }
        return [$err, false];
      }

      $response = curl_exec($ch);
      $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if (($httpcode !== 200 && $httpcode !== 201)) {
        return ['e_request_failed', $response];
      }

      if (!$response) {
        return ['e_request_response_empty', $response];
      }

      return [null, $this->request_json ? json_decode($response, true) : $response];
    } catch (Throwable $T) {
      return ['e_request_failed', $T->getMessage()];
    }
  }
}
