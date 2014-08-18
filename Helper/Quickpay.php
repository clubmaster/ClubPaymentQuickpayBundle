<?php

namespace Club\Payment\QuickpayBundle\Helper;

class Quickpay
{
    public $response;
    private $config;

    public function setConfig(\Club\Payment\QuickpayBundle\Model\Config $config)
    {
        $this->config = $config;

        return $this;
    }

    public function recurring(\Club\Payment\QuickpayBundle\Model\Recurring $recurring)
    {
        try {
            $method = 'recurring';

            $md5check = md5(sprintf('%s%s%s%s%s%s%s%s%s',
                $this->config->protocol,
                $method,
                $this->config->merchant,
                $recurring->order_number,
                $recurring->amount,
                $this->config->currency,
                $this->config->autocapture,
                $recurring->transaction,
                $this->config->md5secret
            ));

            $fields = array(
                'protocol' => $this->config->protocol,
                'msgtype' => $method,
                'merchant' => $this->config->merchant,
                'ordernumber' => $recurring->order_number,
                'amount' => $recurring->amount,
                'currency' => $this->config->currency,
                'autocapture' => $this->config->autocapture,
                'transaction' => $recurring->transaction,
                'md5check' => $md5check
            );

            $data = http_build_query($fields);

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $this->config->api_hostname);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ClubMaster');
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            $response = curl_exec($ch);
            $error = curl_error($ch);

            curl_close($ch);

            $xml = new \SimpleXMLElement($response);

            $response = new \stdClass();
            $md5string = '';
            foreach($xml as $key => $value) {
                $response->{$key} = (string)$value;
                if($key != 'md5check' && $key != 'history') {
                    $md5string .= (string)$value;
                }
            }

            $response->is_valid = (md5($md5string.$this->config->md5secret) == $response->md5check);
            $this->response = $response;

        } catch (\Exception $e) {
        }

        return $this;
    }

    public function getResponse()
    {
        return $this->response;
    }
}
