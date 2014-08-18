<?php

namespace Club\Payment\QuickpayBundle\Listener;

use Club\Payment\QuickpayBundle\Model\Config;
use Club\Payment\QuickpayBundle\Model\Recurring;
use Club\Payment\QuickpayBundle\Entity\Draw;
use Club\ShopBundle\Entity\PurchaseLog;

class SubscriptionDrawListener
{
    protected $container;
    protected $em;

    public function __construct($container)
    {
        $this->container = $container;
        $this->em = $container->get('doctrine.orm.default_entity_manager');
    }

    public function onSubscriptionDrawTask(\Club\TaskBundle\Event\FilterTaskEvent $event)
    {
        $draws = $this->em->getRepository('ClubPaymentQuickpayBundle:Draw')->findBy(array(
            'status' => Draw::DRAW_NEW
        ));

        foreach ($draws as $draw) {
            $d = $this->em->find('ClubPaymentQuickpayBundle:Draw', $draw->getId());

            if ($d->getStatus() > Draw::DRAW_NEW) {
                continue;
            }

            $d->setStatus(Draw::DRAW_PROCESSING);
            $this->em->persist($d);
            $this->em->flush();

            $config = new Config();
            $config->api_hostname = $this->container->getParameter('club_payment_quickpay.quickpay_url').'/api';
            $config->merchant = $this->container->getParameter('club_payment_quickpay.merchant');
            $config->protocol = $this->container->getParameter('club_payment_quickpay.protocol');
            $config->currency = $this->container->getParameter('club_payment_quickpay.currency');
            $config->autocapture = $this->container->getParameter('club_payment_quickpay.autocapture');
            $config->md5secret = $this->container->getParameter('club_payment_quickpay.secret');

            $recurring = new Recurring();
            $recurring->order_number = $d->getOrder()->getId().'_'.rand(1,9999);
            $recurring->amount = $d->getOrder()->getPrice()*100;
            $recurring->transaction = $d->getTransaction();

            $response = $this->container->get('club_payment_quickpay.quickpay')
                ->setConfig($config)
                ->recurring($recurring)
                ->getResponse()
                ;

            $d->setStatus(Draw::DRAW_COMPLETED);
            $this->em->persist($d);

            $accepted = ($response->qpstat == '000');
            $t = new PurchaseLog();
            $t->setAmount($response->amount);
            $t->setCurrency($response->currency);
            $t->setMerchant($response->merchant);
            $t->setTransaction($response->transaction);
            $t->setCardtype($response->cardtype);
            $t->setOrder($d->getOrder());
            $t->setAccepted($accepted);
            $t->setResponse(json_encode($response));

            $this->em->persist($t);

            $this->container->get('order')->setOrder($d->getOrder());
            $this->container->get('order')->makePayment($t);

            $this->em->flush();
        }
    }
}
