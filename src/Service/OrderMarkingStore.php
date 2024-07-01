<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

class OrderMarkingStore implements MarkingStoreInterface
{
    private const PAID_PLACE = 'paid';
    private const COLLECTED_PLACE = 'collected';

    public function getMarking(object $subject): Marking
    {
        /** @var Order $subject */
        $marking = new Marking();
        $marking->mark($subject->getState());
        if ($subject->isCollected()) {
            $marking->mark(self::COLLECTED_PLACE);
        }
        if ($subject->isPaid()) {
            $marking->mark(self::PAID_PLACE);
        }

        return $marking;
    }

    public function setMarking(object $subject, Marking $marking, array $context = [])
    {
        /** @var Order $subject */
        $subject->setIsPaid($marking->has(self::PAID_PLACE));
        $marking->unmark(self::PAID_PLACE);
        $subject->setIsCollected($marking->has(self::COLLECTED_PLACE));
        $marking->unmark(self::COLLECTED_PLACE);
        $subject->setState(array_keys($marking->getPlaces())[0]);
    }
}
