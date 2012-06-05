<?php

namespace Club\Payment\QuickpayBundle\Model;

class Request
{
  public $msgtype;
  public $ordernumber;
  public $amount;
  public $continueurl;
  public $cancelurl;
  public $callbackurl;
  public $description;
  public $cardtypelock;
  public $merchant;
  public $protocol;
  public $language;
  public $currency;
  public $autocapture;
  public $testmode;
  public $md5secret;

  public function getSecret()
  {
    return md5(
      $this->protocol.
      $this->msgtype.
      $this->merchant.
      $this->language.
      $this->ordernumber.
      $this->amount.
      $this->currency.
      $this->continueurl.
      $this->cancelurl.
      $this->callbackurl.
      $this->autocapture.
      $this->cardtypelock.
      $this->description.
      $this->testmode.
      $this->md5secret
    );
  }
}
