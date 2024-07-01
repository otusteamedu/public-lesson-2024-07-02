<?php

namespace App\Entity;

enum OrderStateEnum: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case Paid = 'paid';
    case Sent = 'sent';
    case Finished = 'finished';
}
