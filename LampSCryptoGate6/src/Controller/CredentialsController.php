<?php declare(strict_types=1);

namespace Lampsolutions\LampSCryptoGate6\Controller;

use Lampsolutions\LampSCryptoGate6\Service\CryptoPayment;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @RouteScope(scopes={"api"})
 */
class CredentialsController extends AbstractController {

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct() { }

    /**
     * @Route("/api/v{version}/_action/cryptogate/checkCredentials", name="api.action.cryptogatecheckCredentials", methods={"GET"})
     */
    public function checkCredentials(): JsonResponse {
        /**
         * @var $cryptoPayment CryptoPayment
         */
        $cryptoPayment = $this->container->get("Lampsolutions\\LampSCryptoGate6\\Service\\CryptoPayment");

        if($cryptoPayment->hasCredentials()) {
            $url = $cryptoPayment->testPayment();
            if(filter_var($url,FILTER_VALIDATE_URL)){
                $this->systemConfigService->set('LampSCryptoGate6.config.validated',true);
                return  new JsonResponse(["message" => "success", "credentialsValid" => true]);
            }
        }

        return new JsonResponse(["message" => "error", "credentialsValid" => false]);
    }
}