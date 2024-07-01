<?php

namespace App\Service;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkflowInterface $orderProcessWorkflow,
    ) {
    }

    public function changeState(Order $order, string $transition): bool
    {
        if ($this->orderProcessWorkflow->can($order, $transition)) {
            $this->orderProcessWorkflow->apply($order, $transition);
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
