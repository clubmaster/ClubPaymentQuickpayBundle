<?php

namespace Club\Payment\QuickpayBundle\Model;

class Response
{
  public $msgtype;
  public $ordernumber;
  public $amount;
  public $currency;
  public $time;
  public $state;
  public $qpstate;
  public $qpstatmsg;
  public $chstat;
  public $chstatmsg;
  public $merchant;
  public $merchantemail;
  public $transaction;
  public $cardtype;
  public $cardnumber;
  public $cardexpire;
  public $splitpayment;
  public $fraudprobability;
  public $fraudremarks;
  public $fraudreport;
  public $fee;
  public $md5check;
  public $md5secret;

  public function getSecret()
  {
    return md5(
      $this->msgtype.
      $this->ordernumber.
      $this->amount.
      $this->currency.
      $this->time.
      $this->state.
      $this->qpstat.
      $this->qpstatmsg.
      $this->chstat.
      $this->chstatmsg.
      $this->merchant.
      $this->merchantemail.
      $this->transaction.
      $this->cardtype.
      $this->cardnumber.
      $this->cardexpire.
      $this->splitpayment.
      $this->fraudprobability.
      $this->fraudremarks.
      $this->fraudreport.
      $this->fee.
      $this->md5secret
    );
  }

  public function isValid()
  {
    if ($this->md5check == $this->getSecret())
      return true;

    return false;
  }

  public function isAccepted()
  {
    if ($this->isValid() && $this->qpstat == '000')
      return true;

    return false;
  }
}
