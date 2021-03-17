<?php

use PHPUnit\Framework\TestCase;
use Muvon\KISS\RequestTrait;

final class Request {
  use RequestTrait;

  public function setRequestJson(bool $value): self {
    $this->request_json = $value;
    return $this;
  }

  public function setRequestConnectTimeout(int $value): self {
    $this->request_connect_timeout = $value;
    return $this;
  }

  public function setRequestTimeout(int $value): self {
    $this->request_timeout = $value;
    return $this;
  }

  public function run(string $url, array $payload = [], string $method = 'POST') {
    return $this->request($url, $payload, $method);
  }
}

final class RequestTest extends TestCase {
  protected Request $Client;

  public function setUp(): void {
    $this->Client = new Request;
  }

  public function testUnavailableUrlRerturnsError() {
    [$err, $res] = $this->Client->run('undefined');
    $this->assertEquals('e_request_failed', $err);
    $this->assertEquals(null, $res);
  }

  public function testRequestGoogleSucceed() {
    $this->Client->setRequestJson(false);
    [$err, $res] = $this->Client->run('https://www.google.com/', [], 'GET');
    $this->assertEquals(null, $err);
    $this->assertIsString($res);
  }

  public function testWeDoNotFollowRedirects() {
    $this->Client->setRequestJson(false);
    [$err, $res] = $this->Client->run('https://google.com', [], 'GET');
    $this->assertEquals('e_request_failed', $err);
  }
}