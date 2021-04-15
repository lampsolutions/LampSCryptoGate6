<?php declare(strict_types=1);

namespace Lampsolutions\LampSCryptoGate6\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;


class CryptoPayment implements AsynchronousPaymentHandlerInterface
{
    protected $currency=null;
    protected static $api_endpoint_verify = '/api/shopware/verify';
    protected static $api_endpoint_create = '/api/shopware/create';

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    /**
     *
     */
    private $pluginRepository;

    public function __construct(SystemConfigService $systemConfigService,
                                OrderTransactionStateHandler $transactionStateHandler,
                                RouterInterface $router,
                                EntityRepositoryInterface $currencyRepository,
                                \Monolog\Logger $logger,
                                EntityRepositoryInterface $pluginRepository)
    {

        $this->transactionStateHandler = $transactionStateHandler;
        $this->systemConfigService = $systemConfigService;
        $this->pluginRepository = $pluginRepository;
        $this->router = $router;
        $this->currencyRepository = $currencyRepository;
        $this->logger=$logger;
    }

    /**
     * @param array $payment_data
     * @return string
     */
    public function createPaymentToken($payment_data)
    {
        unset($payment_data["return_url"]);
        unset($payment_data["callback_url"]);
        unset($payment_data["cancel_url"]);
        unset($payment_data["plugin_version"]);
        unset($payment_data["shopware_version"]);
        return sha1(implode('|', $payment_data));
    }

    private function getCurrency(string $currencyId, Context $context) {
        $criteria = new Criteria([$currencyId]);

        /** @var CurrencyCollection $currencyCollection */
        $currencyCollection = $this->currencyRepository->search($criteria, $context);

        $currency = $currencyCollection->get($currencyId);
        if ($currency === null) {
            return false;
        }

        return $currency;
    }

    private function getCryptoGatePaymentData(AsyncPaymentTransactionStruct $transaction, SalesChannelContext $salesChannelContext) {

        $orderEntity = $transaction->getOrder();

        try {
            $currencyEntity = $orderEntity->getCurrency();
            if ($currencyEntity === null) {
                $currencyEntity = $this->getCurrency($orderEntity->getCurrencyId(), $salesChannelContext->getContext());
            }
            $currencyCode = $currencyEntity->getIsoCode();
        } catch (\Exception $e) {
            $this->logger->error('[LampSCryptogate getCryptoGatePaymentData] '.$e->getMessage());
            return false;
        }

        if(!in_array($currencyCode, ['EUR', 'USD', 'CHF'])) {
            $this->logger->error('[LampSCryptogate getCryptoGatePaymentData] Unsupported currency code '.$currencyCode);
            return false;
        }

        if(!empty($transaction->getReturnUrl())) {
            parse_str(parse_url($transaction->getReturnUrl(), PHP_URL_QUERY), $returnQuery);
            $callBackUrl = $this->router->generate('frontend.checkout.cryptogatecallback', $returnQuery, 0);
        } else {
            $callBackUrl = '';
        }
        $supportData = $this->getSupportData();

        $parameters = [
            'amount' => $orderEntity->getAmountTotal(),
            'currency' => $currencyCode,
            'first_name' => $orderEntity->getOrderCustomer()->getFirstName(),
            'last_name' => $orderEntity->getOrderCustomer()->getLastName(),
            'payment_id' => $transaction->getOrderTransaction()->getId(),
            'email' => $orderEntity->getOrderCustomer()->getEmail(),
            'return_url' => $transaction->getReturnUrl(),
            'callback_url' => $callBackUrl,
            'cancel_url' => $transaction->getReturnUrl(),
            'seller_name' => $salesChannelContext->getSalesChannel()->getName(),
            'memo' => sprintf('Ihre Bestellung %s bei %s', $orderEntity->getOrderNumber(), $salesChannelContext->getSalesChannel()->getName()),
            'plugin_version' => $supportData['pluginVersion'],
            'shopware_version' => $supportData['shopwareVersion']
        ];


        if(is_null($this->currency)) {
            $parameters['selected_currencies'] = 'BTC,LTC,DASH,BCH';
        } else {
            $parameters['selected_currencies'] = $this->currency;
        }

        return $parameters;

    }

    public function hasCredentials() {
        $apiUrl = $this->systemConfigService->get('LampSCryptoGate6.config.apiUrl');
        $apiKey = $this->systemConfigService->get('LampSCryptoGate6.config.apiToken');

        if(empty($apiUrl)){
            return false;
        }
        if(empty($apiKey)){
            return false;
        }

        return true;
    }

    private function getSupportData() {
        try {
            $pluginVersion = 'unknown';

            // from public/index.php
            $versions = \PackageVersions\Versions::VERSIONS;
            if (isset($versions['shopware/core'])) {
                $shopwareVersion = \PackageVersions\Versions::getVersion('shopware/core');
            } else {
                $shopwareVersion = \PackageVersions\Versions::getVersion('shopware/platform');
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', 'LampSCryptoGate6'));

            $resultSet = $this->pluginRepository->search($criteria, Context::createDefaultContext())->getEntities();
            if($resultSet) {
                /** @var PluginEntity $plugin */
                $plugin = $resultSet->first();
                if(method_exists($plugin, 'getVersion')) {
                    $pluginVersion = $plugin->getVersion();
                }
            }


        } catch (\Exception $e) {
            $pluginVersion = 'unknown';
            $shopwareVersion = 'unknown';
        }

        return [
            'shopwareVersion' => $shopwareVersion,
            'pluginVersion' => $pluginVersion
        ];
    }

    public function testPayment() {
        $apiUrl = $this->systemConfigService->get('LampSCryptoGate6.config.apiUrl');
        $apiKey = $this->systemConfigService->get('LampSCryptoGate6.config.apiToken');

        if(empty($apiUrl)){
            return false;
        }
        if(empty($apiKey)){
            return false;
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

        $supportData = $this->getSupportData();
        $parameters["plugin_version"] = $supportData['pluginVersion'];
        $parameters["shopware_version"] = $supportData['shopwareVersion'];

        $client = new Client();


        try {
            $response = $client->request(
                'POST',
                $apiUrl.$this::$api_endpoint_create,
                ['form_params' => $parameters]
            );

            $url=json_decode($response->getBody()->getContents(), true)['payment_url'];

            return $url;
        } catch (\Exception $e) {
            $this->systemConfigService->set('LampSCryptoGate6.config.validated',false);
            $this->logger->error('[LampsCryptoGate6]', [$e->getMessage()]);
            return false;
        }
    }

    public function validatePayment($paymentResponse) {
        $apiUrl = $this->systemConfigService->get('LampSCryptoGate6.config.apiUrl');
        $apiKey = $this->systemConfigService->get('LampSCryptoGate6.config.apiToken');

        if(empty($apiUrl)){
            $this->logger->error('[LampSCryptogate validatePaypent] ApiURL missing');
            return false;

        }
        if(empty($apiKey)){
            $this->logger->error('[LampSCryptogate validatePaypent] ApiKey missing');
            return false;

        }


        $parameters = [
            'uuid' => $paymentResponse['transactionId'],
            'token' => $paymentResponse['token'],
            'api_key' => $apiKey
        ];

        $client = new Client();

        try {
            $response = $client->request(
                'POST',
                $apiUrl.$this::$api_endpoint_verify,
                ['form_params' => $parameters]
            );

            $verify = json_decode($response->getBody()->getContents(), true);

            if($verify['token'] == $paymentResponse['token'] && !empty($paymentResponse['token']) && !empty($verify['token'])) {
                return true;
            }
            $this->logger->error('[LampSCryptogate validatePaypent] wrong token');

            return false;
        } catch (GuzzleException $e) {
            $this->logger->error('[LampSCryptogate validatePaypent] ApiURL missing', $e->getMessage());

            return false;
        }
    }

    public function createPaymentUrl($parameters,$version) {
        $apiUrl = $this->systemConfigService->get('LampSCryptoGate6.config.apiUrl');
        $apiKey = $this->systemConfigService->get('LampSCryptoGate6.config.apiToken');
        $transmitCustomerData = (bool) $this->systemConfigService->get('LampSCryptoGate6.config.transmitCustomerData');


        if(empty($apiUrl)){
            $this->logger->error('[LampsCryptoGate6] ApiURL missing');
            return false;

        }
        if(empty($apiKey)){
            $this->logger->error('[LampsCryptoGate6] ApiKey missing');
            return false;

        }


        $parameters['token'] = $this->createPaymentToken($parameters);
        $parameters['api_key'] = $apiKey;
        $parameters["plugin_version"] = $version;

        if(is_null($this->currency)) {
            $parameters['selected_currencies'] = 'BTC,LTC,DASH,BCH';
        } else {
            $parameters['selected_currencies'] = $this->currency;
        }

        if($transmitCustomerData===false){
            $parameters["first_name"] = "";
            $parameters["last_name"] = "";
            $parameters["email"] = "";
        }

        $client = new Client();

        try {
            $response = $client->request(
                'POST',
                $apiUrl.$this::$api_endpoint_create,
                ['form_params' => $parameters]
            );

            return json_decode($response->getBody()->getContents(), true)['payment_url'];
        }catch (GuzzleException $e) {
            $this->logger->error('[LampsCryptoGate6]', [$e->getMessage()]);
            return false;
        }
    }

    /**
     * @throws AsyncPaymentProcessException
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {

        try {
            $paymentData = $this->getCryptoGatePaymentData($transaction, $salesChannelContext);
            if(!$paymentData) {
                throw new AsyncPaymentProcessException(
                    $transaction->getOrderTransaction()->getId(),
                    'An error occurred during the communication with external payment gateway' . PHP_EOL
                );
            }

            $redirectUrl = $this->createPaymentUrl($paymentData, $salesChannelContext->getContext()->getVersionId());
            if(!$redirectUrl){
                throw new AsyncPaymentProcessException(
                    $transaction->getOrderTransaction()->getId(),
                    'An error occurred during the communication with external payment gateway' . PHP_EOL
                );
            }
            if(!empty($this->currency)) $redirectUrl.='/'.$this->currency;

        } catch (\Exception $e) {
            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        // Redirect to external gateway
        return new RedirectResponse($redirectUrl);
    }

    /**
     * @throws CustomerCanceledAsyncPaymentException
     */
    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {

        $paymentData = $this->getCryptoGatePaymentData($transaction, $salesChannelContext);
        $paymentToken = $this->createPaymentToken($paymentData);
        $paymentResponse = [
            'transactionId' => $request->query->get('uuid'),
            'status' => $request->query->get('status'),
            'token' => $request->query->get('token'),
        ];


        $context = $salesChannelContext->getContext();
        if($this->isValidToken($paymentResponse['token'], $paymentToken)) {
            if($this->validatePayment($paymentResponse)) {
                if($transaction->getOrderTransaction()->getStateMachineState()->getTechnicalName()=="open") {
                    $this->transactionStateHandler->paid($transaction->getOrderTransaction()->getId(), $context);
                }
                return;
            }
        }
    }

    public function isValidToken($response_token, $token)
    {
        return hash_equals($token, $response_token);
    }


}
