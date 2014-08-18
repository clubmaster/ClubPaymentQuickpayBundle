<?php

namespace Club\Payment\QuickpayBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class QuickpayController extends Controller
{
    /**
     * @Route("/quickpay/success/{order_id}")
     * @Template()
     */
    public function successAction()
    {
        return array();
    }

    /**
     * @Route("/quickpay/cancel/{order_id}")
     * @Template()
     */
    public function cancelAction()
    {
        return array();
    }

    /**
     * @Route("/quickpay/callback/{order_id}")
     * @Template()
     * @Method("POST")
     */
    public function callbackAction(Request $request, $order_id)
    {
        $accepted = false;

        if ($request->get('qpstat') == '000') {
            $accepted = $this->validateTransaction($request);
        }

        $em = $this->getDoctrine()->getManager();
        $order = $em->find('ClubShopBundle:Order', $order_id);

        $t = new \Club\ShopBundle\Entity\PurchaseLog();
        $t->setAmount($request->get('amount'));
        $t->setCurrency($request->get('currency'));
        $t->setMerchant($request->get('merchant'));
        $t->setTransaction($request->get('transaction'));
        $t->setCardtype($request->get('cardtype'));
        $t->setOrder($order);
        $t->setAccepted($accepted);
        $t->setResponse(json_encode($request->request->all()));

        $payment = $em->getRepository('ClubShopBundle:PaymentMethod')->findOneBy(array(
            'controller' => $this->container->getParameter('club_payment_quickpay.controller')
        ));
        $t->setPaymentMethod($payment);

        $em->persist($t);
        $em->flush();

        if ($accepted) {
            $this->get('order')->setOrder($order);
            $this->get('order')->makePayment($t);
        }

        return new Response('OK');
    }

    /**
     * @Route("/quickpay/{order_id}")
     * @Template()
     */
    public function indexAction(Request $request, $order_id)
    {
        $em = $this->getDoctrine()->getManager();
        $order = $em->find('ClubShopBundle:Order', $order_id);

        $form = $this->getForm($request, $order);

        return array(
            'quickpay_url' => $this->container->getParameter('club_payment_quickpay.quickpay_url'),
            'form' => $form->createView(),
            'order' => $order
        );
    }

    protected function getForm(Request $request, \Club\ShopBundle\Entity\Order $order)
    {
        $msgtype = 'authorize';
        foreach ($order->getOrderProducts() as $prod) {
            if ($prod->getType() == 'subscription') {
                $msgtype = 'subscribe';
            }
        }

        $res = array(
            'msgtype' => $msgtype,
            'ordernumber' => $order->getOrderNumber(),
            'amount' => ($order->getPrice()*100),
            'continueurl' => $this->generateUrl('club_payment_quickpay_quickpay_success', array(
                'order_id' => $order->getId()
            ), true),
            'cancelurl' => $this->generateUrl('club_payment_quickpay_quickpay_cancel', array(
                'order_id' => $order->getId()
            ),true),
            'callbackurl' => $this->generateUrl('club_payment_quickpay_quickpay_callback', array(
                'order_id' => $order->getId()
            ), true),
            'protocol' => $this->container->getParameter('club_payment_quickpay.protocol'),
            'merchant' => $this->container->getParameter('club_payment_quickpay.merchant'),
            'language' => $this->container->getParameter('club_payment_quickpay.language'),
            'currency' => $this->container->getParameter('club_payment_quickpay.currency'),
            'autocapture' => $this->container->getParameter('club_payment_quickpay.autocapture'),
            'autofee' => $this->container->getParameter('club_payment_quickpay.autofee'),
            'cardtypelock' => $this->container->getParameter('club_payment_quickpay.cardtypelock'),
            'description' => 'ClubMaster subscription',
            'testmode' => $this->container->getParameter('club_payment_quickpay.testmode'),
            'splitpayment' => $this->container->getParameter('club_payment_quickpay.splitpayment'),
            'ipaddress' => $request->getClientIP()
        );

        $md5check = md5(
            $res['protocol'].
            $res['msgtype'].
            $res['merchant'].
            $res['language'].
            $res['ordernumber'].
            $res['amount'].
            $res['currency'].
            $res['continueurl'].
            $res['cancelurl'].
            $res['callbackurl'].
            $res['autocapture'].
            $res['autofee'].
            $res['cardtypelock'].
            $res['description'].
            $res['ipaddress'].
            $res['testmode'].
            $res['splitpayment'].
            $this->container->getParameter('club_payment_quickpay.secret')
        );
        $res['md5check'] = $md5check;

        $form = $this->createForm(new \Club\Payment\QuickpayBundle\Form\Quickpay, $res);

        return $form;
    }

    protected function validateTransaction(Request $request)
    {
        $md5check = md5(
            $request->get('msgtype').
            $request->get('ordernumber').
            $request->get('amount').
            $request->get('currency').
            $request->get('time').
            $request->get('state').
            $request->get('qpstat').
            $request->get('qpstatmsg').
            $request->get('chstat').
            $request->get('chstatmsg').
            $request->get('merchant').
            $request->get('merchantemail').
            $request->get('transaction').
            $request->get('cardtype').
            $request->get('cardnumber').
            $request->get('cardexpire').
            $request->get('splitpayment').
            $request->get('fraudprobability').
            $request->get('fraudremarks').
            $request->get('fraudreport').
            $request->get('fee').
            $this->container->getParameter('club_payment_quickpay.secret')
        );

        return ($md5check == $request->get('md5check')) ? true : false;
    }
}
