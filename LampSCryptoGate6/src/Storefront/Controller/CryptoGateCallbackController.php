<?php declare(strict_types=1);

namespace Lampsolutions\LampSCryptoGate6\Storefront\Controller;

use Lampsolutions\LampSCryptoGate6\Service\CryptoPayment;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTransactionException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Storefront\Controller\StorefrontController;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CryptoGateCallbackController extends StoreFrontController
{
    /**
     * @var PaymentService
     */
    private $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @Route("/cryptogate/callback", name="frontend.checkout.cryptogatecallback", defaults={"auth_required"=false,"csrf_protected"=false}, options={"seo"="false"}, methods={"POST","GET"})
     */
    public function finalizeTransaction(Request $request, SalesChannelContext $salesChannelContext): Response
    {

        /**
         * @var $cryptoPayment CryptoPayment
         */
        $cryptoPayment = $this->container->get("Lampsolutions\\LampSCryptoGate6\\Service\\CryptoPayment");

        if($cryptoPayment->hasCredentials()) {
            $cryptoPayment->testPayment();
        }

        $paymentToken = $request->get('_sw_payment_token');

        $paymentTokenStruct = $this->paymentService->finalizeTransaction($paymentToken, $request, $salesChannelContext);

        if ($paymentTokenStruct->getFinishUrl()) {
            return new RedirectResponse($paymentTokenStruct->getFinishUrl());
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}