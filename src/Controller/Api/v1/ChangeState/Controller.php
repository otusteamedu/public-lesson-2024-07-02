<?php

namespace App\Controller\Api\v1\ChangeState;

use App\Controller\Api\v1\ChangeState\Input\ChangeStateRequest;
use App\Entity\OrderStateEnum;
use App\Service\OrderService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    #[Route(path: '/api/v1/change-state', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] ChangeStateRequest $request): Response
    {
        $order = $this->orderService->getOrder($request->id);
        if ($order === null) {
            return new JsonResponse(['message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $state = OrderStateEnum::tryFrom($request->state);
        if ($state === null) {
            return new JsonResponse(['message' => 'Invalid state'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->orderService->changeState($order, $state)) {
            return new JsonResponse(['message' => 'Cannot change state'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['id' => $order->getId(), 'state' => $order->getState()]);
    }
}
