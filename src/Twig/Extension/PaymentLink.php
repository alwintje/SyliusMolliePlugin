<?php

namespace SyliusMolliePlugin\Twig\Extension;

use Sylius\Component\Core\Model\PaymentInterface;
use SyliusMolliePlugin\Client\MollieApiClient;
use SyliusMolliePlugin\Entity\TemplateMollieEmailInterface;
use SyliusMolliePlugin\Resolver\PaymentlinkResolverInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PaymentLink extends AbstractExtension
{

    public function __construct(
        private readonly PaymentlinkResolverInterface $paymentlinkResolver,
        private readonly MollieApiClient $mollieApiClient,
    ) { }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getMolliePaymentLink', [$this, 'getMolliePaymentLink'])
        ];
    }

    public function getMolliePaymentLink(PaymentInterface $payment): string
    {
        $details = $payment->getDetails();

        if(!isset($details['payment_mollie_id'])){
            return $this->paymentlinkResolver->resolve($payment->getOrder(), ['methods' => $details['molliePaymentMethods']], TemplateMollieEmailInterface::PAYMENT_LINK);
        }

        $molliePayment = $this->mollieApiClient->payments->get($details['payment_mollie_id']);
        return $molliePayment?->getCheckoutUrl() ?? $this->paymentlinkResolver->resolve($payment->getOrder(), ['methods' => $details['molliePaymentMethods']], TemplateMollieEmailInterface::PAYMENT_LINK);
    }


}
