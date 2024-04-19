<?php


declare(strict_types=1);

namespace SyliusMolliePlugin\Entity;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

interface OrderInterface extends BaseOrderInterface
{
    public function isAbandonedEmail(): bool;

    public function setAbandonedEmail(bool $abandonedEmail): void;

    public function hasRecurringContents(): bool;

    public function hasNonRecurringContents(): bool;

    public function getRecurringSequenceIndex(): ?int;

    public function setRecurringSequenceIndex(int $recurringSequenceIndex): void;

    public function getSubscription(): ?MollieSubscriptionInterface;

    public function setSubscription(?MollieSubscriptionInterface $subscription): void;

    /** @return Collection|OrderItemInterface[] */
    public function getRecurringItems(): Collection;

    /** @return Collection|OrderItemInterface[] */
    public function getNonRecurringItems(): Collection;
}
