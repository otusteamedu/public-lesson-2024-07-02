<?php

namespace App\EventListener;

use App\Entity\Order;
use DateTime;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

class DeliveredEventListener
{
    #[AsEnterListener(workflow: 'order_process', place: 'finished')]
    public function onFinishedEnter(EnterEvent $event): void {
        /** @var Order $order */
        $order = $event->getSubject();
        $order->setDeliveredAt(new DateTime());
    }
}
