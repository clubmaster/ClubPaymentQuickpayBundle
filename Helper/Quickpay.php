<?php

namespace Club\Payment\QuickpayBundle\Helper;

class Quickpay
{
  public $response;
  private $config;

  public function setConfig(\Club\Payment\QuickpayBundle\Model\Config $config)
  {
    $this->config = $config;
  }

  private function send($out)
  {
    $str_len = strlen($out);

    $out = <<<EOF
POST /api HTTP/1.1
Host: {$this->config->api_hostname}
User-Agent: ClubMaster
Content-Length: {$str_len}
Content-Type: application/x-www-form-urlencoded

{$out}
EOF;

    $fp = fsockopen('ssl://'.$this->config->api_hostname, 443, $errno, $errstr, 30);
    if (!$fp) throw new \Exception($errno.' ('.$errstr.')');

    fwrite($fp, $out);

    $is_header = true;
    $header = '';
    $body = '';

    while (!feof($fp)) {
      $b = trim(fgets($fp, 128));

      if (!strlen($b)) {
        $is_header = false;
      } elseif ($is_header) {
        $header .= $b;
      } else {
        $body .= $b;
      }
    }
    fclose($fp);

    $this->response = simplexml_load_string($body);
  }

  public function recurring(\Club\Payment\QuickpayBundle\Model\Recurring $recurring)
  {
    $method = 'recurring';

    $md5check = md5(
      $this->config->protocol.
      $method.
      $this->config->merchant.
      $recurring->order_number.
      $recurring->amount.
      $this->config->currency.
      $this->config->autocapture.
      $recurring->transaction.
      $this->config->md5secret
    );

    $out = <<<EOF
protocol={$this->config->protocol}
&msgtype={$method}
&merchant={$this->config->merchant}
&ordernumber={$recurring->order_number}
&amount={$recurring->amount}
&currency={$this->config->currency}
&autocapture={$this->config->autocapture}
&transaction={$recurring->transaction}
&md5check={$md5check}
EOF;

    $out = preg_replace("/\n/", "", $out);
    $this->send($out);
  }
}
