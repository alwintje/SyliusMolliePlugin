<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.shop and write us
 * an email on mikolaj.krol@bitbag.pl.
 */

declare(strict_types=1);

namespace BitBag\SyliusMolliePlugin\Resolver;

use BitBag\SyliusMolliePlugin\Client\MollieApiClient;
use BitBag\SyliusMolliePlugin\EmailSender\PaymentLinkEmailSenderInterface;
use BitBag\SyliusMolliePlugin\Entity\MollieGatewayConfig;
use BitBag\SyliusMolliePlugin\Factory\MollieGatewayFactory;
use BitBag\SyliusMolliePlugin\Helper\IntToStringConverter;
use Liip\ImagineBundle\Exception\Config\Filter\NotFoundException;
use Mollie\Api\Types\PaymentMethod;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class PaymentlinkResolver implements PaymentlinkResolverInterface
{
    /** @var MollieApiClient */
    private $mollieApiClient;

    /** @var IntToStringConverter */
    private $intToStringConverter;

    /** @var RepositoryInterface */
    private $orderRepository;

    /** @var PaymentLinkEmailSenderInterface */
    private $emailSender;

    public function __construct(
        MollieApiClient $mollieApiClient,
        IntToStringConverter $intToStringConverter,
        RepositoryInterface $orderRepository,
        PaymentLinkEmailSenderInterface $emailSender
    ) {
        $this->mollieApiClient = $mollieApiClient;
        $this->intToStringConverter = $intToStringConverter;
        $this->orderRepository = $orderRepository;
        $this->emailSender = $emailSender;
    }

    public function resolve(OrderInterface $order, array $data): string
    {
        $methodsArray = [];
        $methods = $data['methods'];

        /** @var PaymentInterface $syliusPayment */
        $syliusPayment = $order->getPayments()->first();

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $syliusPayment->getMethod();

        if (MollieGatewayFactory::FACTORY_NAME !== $paymentMethod->getGatewayConfig()->getFactoryName()) {
            throw new NotFoundException('No method mollie found in order');
        }

        $modusKey = $this->getModus($paymentMethod->getGatewayConfig()->getConfig());

        /** @var MollieGatewayConfig $method */
        foreach ($methods as $method) {
            if (PaymentMethod::KLARNA_PAY_LATER === $method->getMethodId() ||
                PaymentMethod::KLARNA_SLICE_IT === $method->getMethodId()) {
                continue;
            }

            $methodsArray[] = $method->getMethodId();
        }

        $this->mollieApiClient->setApiKey($modusKey);
        $details = $syliusPayment->getDetails();

        $data = [
            'method' => $methodsArray,
            'amount' => [
                'currency' => (string) $syliusPayment->getCurrencyCode(),
                'value' => $this->intToStringConverter->convertIntToString($syliusPayment->getAmount(), 100),
            ],
            'description' => $order->getNumber(),
            'redirectUrl' => $details['backurl'],
            'webhookUrl' => str_replace('127.0.0.1:8000', 'c3bf7f80e105.ngrok.io', $details['webhookUrl']),
            'metadata' => [
                'order_id' => $order->getId(),
                'refund_token' => $details['refund_token'],
                'customer_id' => $order->getCustomer()->getId(),
            ],
        ];

        $payment = $this->mollieApiClient->payments->create($data);

        $details['payment_mollie_id'] = $payment->id;
        $details['metadata']['refund_token'] = $details['refund_token'];
        $details['payment_mollie_link'] = $payment->_links->checkout->href;

        $syliusPayment->setDetails($details);

        $this->orderRepository->add($order);

        $this->emailSender->sendConfirmationEmail($order);

        return $payment->_links->checkout->href;
    }

    private function getModus(array $config): string
    {
        if ($config['environment']) {
            return $config['api_key_live'];
        }

        return $config['api_key_test'];
    }
}