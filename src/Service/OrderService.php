<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderStateEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkflowInterface $orderProcessStateMachine,
    ) {
    }

    public function changeState(Order $order, OrderStateEnum $newState): bool
    {
        if ($this->orderProcessStateMachine->can($order, $newState->value)) {
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
