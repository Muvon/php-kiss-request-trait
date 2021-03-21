<?php
namespace Muvon\KISS;

use Error;
use Throwable;
use CurlHandle;
use CurlMultiHandle;

trait RequestTrait {
  protected int $request_connect_timeout = 5;
  protected int $request_timeout = 12;
  protected int $request_keepalive = 20;
  protected bool $request_json = true;
  protected array $request_handlers = [];
  protected ?CurlMultiHandle $request_mh = null;

  /**
   * Run multi model
   *
   * @return self
   */
  protected function multi(): self {
    $this->request_mh = curl_multi_init();
    return $this;
  }

  /**
   * Do single or multi request if multi() caleld before
   *
   * @param string $url
   * @param array $payload
   * @param string $method Can be POST or GET only
   * @param array $headers Array with headers. Each entry as string
   * @return self|array in case multi() mode reqturns self otherswise array
   */
  protected function request(string $url, array $payload = [], string $method = 'POST', array $headers = []): self|array {
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
    if ($this->request_mh) {
      $this->request_handlers[] = $ch;
      curl_multi_add_handle($this->request_mh, $ch);
      return $this;
    }

    return $this->process($ch);
  }

  /**
   * This method execute multiple request in multi() mode
   * If we call this methods without multi it throws Exception
   * In case if one or more responses failed it throws Exception
   *
   * @return array
   */
  protected function exec(): array {
    if (!$this->request_mh) {
      throw new Error('Trying to exec request that ws not inited');
    }
    do {
      $status = curl_multi_exec($this->request_mh, $active);
      if ($active) {
        curl_multi_select($this->request_mh);
      }
    } while ($active && $status == CURLM_OK);

    $result = [];
    foreach ($this->request_handlers as $ch) {
      [$err, $resp] = $this->process($ch);
      if ($err) {
        throw new Error('One of the requests has response error: ' . $err);
      }
      $result[] = $resp;
    }
    curl_multi_close($this->request_mh);
    unset($this->request_handlers);
    $this->request_mh = null;

    return $result;
  }

  private function process(CurlHandle $ch): array {
    try {
      $fetch_fn = $this->request_mh ? 'curl_multi_getcontent' : 'curl_exec';
      $response = $fetch_fn($ch);
      $err_code = curl_errno($ch);
      if ($err_code) {
        return [match ($err_code) {
          7 => 'e_request_refused',
          28 => 'e_request_timedout',
          default => 'e_request_failed',
        }, curl_error($ch)];
      }
      $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if (($httpcode !== 200 && $httpcode !== 201)) {
        return ['e_request_failed', null];
      }

      if (!$response) {
        return ['e_request_response_empty', $response];
      }

      return [null, $this->request_json ? json_decode($response, true) : $response];
    } catch (Throwable $T) {
      return ['e_request_failed', $T->getMessage()];
    } finally {
      if ($this->request_mh) {
        curl_multi_remove_handle($this->request_mh, $ch);
      }
    }
  }
}
