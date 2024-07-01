<?php

namespace App\Controller\Api\v1\ChangeState\Input;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Validator\Constraints as Assert;

#[Exclude]
class ChangeStateRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly int $id,
        #[Assert\NotBlank]
        public readonly string $state
    ) {
    }
}
