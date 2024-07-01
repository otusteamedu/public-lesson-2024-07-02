<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderStateEnum;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function changeState(Order $order, OrderStateEnum $newState): bool
    {
        $currentState = OrderStateEnum::from($order->getState());
        if (
                ($currentState === OrderStateEnum::New && $newState === OrderStateEnum::Accepted) ||
                ($currentState === OrderStateEnum::Accepted && $newState === OrderStateEnum::Paid) ||
                ($currentState === OrderStateEnum::Paid && $newState === OrderStateEnum::Sent) ||
                ($currentState === OrderStateEnum::Sent && $newState === OrderStateEnum::Finished)
            ) {
                $order->setState($newState->value);
                $this->entityManager->flush();

                return true;
        }

        return false;
    }

    public function getOrder(int $id): ?Order
    {
        /** @var Order|null $order */
        $order = $this->entityManager->getRepository(Order::class)->find($id);

        return $order;
    }
}
