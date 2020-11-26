<?php declare(strict_types=1);

namespace Lampsolutions\LampSCryptoGate6\Controller;

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
class CredentialsController extends AbstractController
{

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    protected static $api_endpoint_create = 'api/shopware/create';

    public function __construct(SystemConfigService $systemConfigService, \Monolog\Logger $logger)
    {
        $this->systemConfigService = $systemConfigService;
        $this->logger=$logger;
    }


    /**
     * @Route("/api/v{version}/_action/cryptogate/checkCredentials", name="api.action.cryptogatecheckCredentials", methods={"GET"})
     */
    public function checkCredentials(): JsonResponse
    {
        $result=$this->createPaymentUrl();
        if($result){
            return new JsonResponse($result);
        }
        else{
            return new JsonResponse(["message" => "error", "credentialsValid" => false]);
        }

    }

    private function createPaymentUrl(){
        $apiUrl = $this->systemConfigService->get('LampSCryptoGate6.config.apiUrl');
        $apiKey = $this->systemConfigService->get('LampSCryptoGate6.config.apiToken');
        $transmitCustomerData = (bool) $this->systemConfigService->get('LampSCryptoGate6.config.transmitCustomerData');

        if(empty($apiUrl)){
            $this->logger->error('[LampsCryptoGate6] ApiURL missing');
            return ["status" => false, "message" => "API Url missing"];
        }
        if(empty($apiKey)){
            $this->logger->error('[LampsCryptoGate6] ApiKey missing');

            return ["status" => false, "message" => "API Key missing"];
        }

        $parameters = [
            'amount' => 1.00,
            'currency' => "EUR",
            'first_name' => "first_name",
            'last_name' => "last_name",
            'payment_id' => 42,
            'email' => "test@example.com",
            'return_url' => "__not_set__",
            'callback_url' => "__not_set__",
            'cancel_url' => "__not_set__",
            'seller_name' => "",
            'memo' => '' . $_SERVER['SERVER_NAME']
        ];

        $parameters['token'] = "test123";
        $parameters['api_key'] = $apiKey;
        $parameters["plugin_version"] = "test";
        $parameters['selected_currencies'] = 'BTC,LTC,DASH,BCH';
        $parameters["first_name"] = "";
        $parameters["last_name"] = "";
        $parameters["email"] = "";



        $client = new Client();


        try {
            $response = $client->request(
                'POST',
                $apiUrl.$this::$api_endpoint_create,
                ['form_params' => $parameters]
            );

            $url=json_decode($response->getBody()->getContents(), true)['payment_url'];

            if(filter_var($url,FILTER_VALIDATE_URL)){
                $this->systemConfigService->set('LampSCryptoGate6.config.validated',true);
                return ["message" => "success", "credentialsValid" => true];

            }

            return $url;
        }catch (\Exception $e) {
            $this->systemConfigService->set('LampSCryptoGate6.config.validated',false);

            $this->logger->error('[LampsCryptoGate6]', [$e->getMessage()]);

            return false;
        }
    }
}