<?php declare(strict_types=1);

namespace Lampsolutions\LampSCryptoGate6\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;
use Lampsolutions\LampSCryptoGate6\Service\CryptoPayment;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Shopware\Storefront\Controller\StorefrontController;


/**
 * @RouteScope(scopes={"storefront"})
 */
class CryptoGateController extends StoreFrontController
{
    /**
     * @var CryptoPayment
     */
    private $cryptoPayment;

    public function __construct(CryptoPayment $cryptoPayment)
    {
        $this->cryptoPayment = $cryptoPayment;
    }

    /**
     * @Route("/cryptogate/gate", name="frontend.checkout.gate", defaults={"auth_required"=false,"csrf_protected"=true}, options={"seo"="false"}, methods={"POST","GET"})
     */
    public function gate(Request $request, SalesChannelContext $salesChannelContext): Response {

        $uuid = $request->get('uuid');
        $paymentUrl = $this->cryptoPayment->buildPaymentUrlByUuid($uuid);

        return $this->renderStorefront('@LampSCryptoGate6/storefront/page/checkout/gate.html.twig', [
            'paymentUrl' => $paymentUrl
        ]);
    }
}