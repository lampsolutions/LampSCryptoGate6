<?php declare(strict_types=1);

namespace Lampsolutions\LampSCryptoGate6;

use Lampsolutions\LampSCryptoGate6\Service\CryptoPayment;
use Lampsolutions\LampSCryptoGate6\Service\CryptoPaymentBch;
use Lampsolutions\LampSCryptoGate6\Service\CryptoPaymentBtc;
use Lampsolutions\LampSCryptoGate6\Service\CryptoPaymentDash;
use Lampsolutions\LampSCryptoGate6\Service\CryptoPaymentLtc;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class LampSCryptoGate6 extends Plugin {

    public function install(InstallContext $context): void {
        $this->addPaymentMethods($context->getContext());
        $this->enableAfterOrderEnabled($context->getContext());
    }

    public function uninstall(UninstallContext $context): void {
        $this->setPaymentMethodsIsActive(false, $context->getContext());
        parent::uninstall($context);
    }
    public function update(Plugin\Context\UpdateContext $context): void {
        $this->enableAfterOrderEnabled($context->getContext());
        parent::update($context);
    }

    public function activate(ActivateContext $context): void {
        $this->setPaymentMethodsIsActive(true, $context->getContext());
        $this->enableAfterOrderEnabled($context->getContext());
        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context): void {
        $this->setPaymentMethodsIsActive(false, $context->getContext());
        parent::deactivate($context);
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
        $this->checkIntegration();
    }

    private function checkIntegration() {
        try {
            /**
             * @var $cryptoPayment CryptoPayment
             */
            $cryptoPayment = $this->container->get("Lampsolutions\\LampSCryptoGate6\\Service\\CryptoPayment");

            if($cryptoPayment && $cryptoPayment->hasCredentials()) {
                return $cryptoPayment->testPayment();
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    private function enableAfterOrderEnabled(Context $context) {
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodIds = $this->getPaymentMethodIds();

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodIds) {
            return;
        }

        foreach($paymentMethodIds as $paymentMethodId) {
            $paymentMethod = [
                'id' => $paymentMethodId,
                'afterOrderEnabled' => true,
            ];

            $paymentRepository->update([$paymentMethod], $context);
        }
    }

    private function getConfig() {
        $shop = $shop = $this->container->get('shop');
        $configReader = $this->container->get('shopware.plugin.cached_config_reader');

        return $configReader->getByPluginName($this->getName(), $shop);
    }

    private function addPaymentMethods(Context $context): void
    {
        $paymentMethodsExists = $this->getPaymentMethodIds();

        if ($paymentMethodsExists) {
            return;
        }

        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);

        $paymentsVariants = [
            [
                'handlerIdentifier' => CryptoPayment::class,
                'name' => 'Kryptowährungen',
                'description' => 'Pay now securely and encrypted with Bitcoin, Bitcoin Cash, Dash or Litecoin.',
                'pluginId' => $pluginId,
                'afterOrderEnabled' => true
            ],
            [
                'handlerIdentifier' => CryptoPaymentBtc::class,
                'name' => 'Bitcoin',
                'description' => 'Pay securely and encrypted with Bitcoin now.',
                'pluginId' => $pluginId,
                'afterOrderEnabled' => true
            ],
            [
                'handlerIdentifier' => CryptoPaymentBch::class,
                'name' => 'Bitcoin Cash',
                'description' => 'Pay securely and encrypted with Bitcoin Cash now.',
                'pluginId' => $pluginId,
                'afterOrderEnabled' => true
            ],
            [
                'handlerIdentifier' => CryptoPaymentLtc::class,
                'name' => 'Litecoin',
                'description' => 'Pay securely and encrypted with Litecoin now.',
                'pluginId' => $pluginId,
                'afterOrderEnabled' => true
            ],
            [
                'handlerIdentifier' => CryptoPaymentDash::class,
                'name' => 'Dash',
                'description' => 'Pay securely and encrypted with Dash now.',
                'pluginId' => $pluginId,
                'afterOrderEnabled' => true
            ]
        ];


        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->create($paymentsVariants, $context);
    }

    private function setPaymentMethodsIsActive(bool $active, Context $context): void
    {
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodIds = $this->getPaymentMethodIds();

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodIds) {
            return;
        }

        foreach($paymentMethodIds as $paymentMethodId) {
            $paymentMethod = [
                'id' => $paymentMethodId,
                'active' => $active,
            ];

            $paymentRepository->update([$paymentMethod], $context);
        }

    }

    private function getPaymentMethodIds(): ?array
    {
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');


        $paymentCriteria = (new Criteria())->addFilter(new EqualsAnyFilter('handlerIdentifier', [
            CryptoPayment::class,
            CryptoPaymentBtc::class,
            CryptoPaymentBch::class,
            CryptoPaymentDash::class,
            CryptoPaymentLtc::class
        ]));

        $paymentIds = $paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext());


        if ($paymentIds->getTotal() === 0) {
            return null;
        }

        return $paymentIds->getIds();
    }
}
