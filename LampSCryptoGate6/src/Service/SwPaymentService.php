<?php declare(strict_types=1);

namespace Lampsolutions\LampSCryptoGate6\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;


class SwPaymentService {
    /**
     * @var PaymentTransactionChainProcessor
     */
    private $paymentProcessor;

    /**
     * @var TokenFactoryInterfaceV2
     */
    private $tokenFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentHandlerRegistry
     */
    private $paymentHandlerRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        TokenFactoryInterfaceV2 $tokenFactory,
        LoggerInterface $logger
    ) {
        $this->tokenFactory = $tokenFactory;
        $this->logger = $logger;
    }

    public function buildCallbackPaymentToken(string $paymentToken) {
        $paymentTokenStruct = $this->parseToken($paymentToken);

        $callbackTokenStruct = new TokenStruct(
            $paymentTokenStruct->getId(),
            $paymentTokenStruct->getToken(),
            $paymentTokenStruct->getPaymentMethodId(),
            $paymentTokenStruct->getTransactionId(),
            $paymentTokenStruct->getFinishUrl(),
            time() + 60*60*24*31, // Add 1 Month of time for callback token
            $paymentTokenStruct->getErrorUrl()
        );

        return $this->tokenFactory->generateToken($callbackTokenStruct);
    }

    /**
     * @throws TokenExpiredException
     */
    private function parseToken(string $token): TokenStruct {
        $tokenStruct = $this->tokenFactory->parseToken($token);

        if ($tokenStruct->isExpired()) {
            //throw new TokenExpiredException($tokenStruct->getToken());
        }

        //$this->tokenFactory->invalidateToken($tokenStruct->getToken());

        return $tokenStruct;
    }
}
