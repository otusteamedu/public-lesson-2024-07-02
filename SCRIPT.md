# Реализация бизнес-логики: workflows и state machines

## Готовим проект

1. Запускаем контейнеры командой `docker-compose up -d`
2. Входим в контейнер командой `docker exec -it php sh`. Дальнейшие команды будем выполнять из контейнера
3. Устанавливаем зависимости командой `composer install`
4. Выполняем миграции командой `php bin/console doctrine:migrations:migrate`

## Проверяем работоспособность приложения

1. Добавляем в БД запись со статусом `new`
2. Выполняем запрос `http://localhost:7777/api/v1/change-state` с payload `{"id": ID, "state": "accepted"}`, где `ID` –
   идентификатор созданной записи. Видим ответ с корректным статусом.
3. Выполняем запрос с другим идентификатором, видим код ответа 404.
4. Выполняем запрос с payload `{"id": ID, "state": "some"}`, видим код ответа 400.
5. Выполняем запрос с payload `{"id": ID, "state": "finished"}`, видим код ответа 422.

## Переносим бизнес-логику в пакет Symfony Workflow

1. Устанавливаем пакет `symfony/workflow:6.4.*`
2. Добавляем файл `packages/config/workflow.yaml`
    ```yaml
    framework:
      workflows:
        order_process:
          type: 'state_machine'
          marking_store:
            type: 'method'
            property: 'state'
          supports:
            - App\Entity\Order
          initial_marking: new
          places:
            - new
            - accepted
            - paid
            - sent
            - finished
          transitions:
            accepted:
              from: new
              to: accepted
            paid:
              from: accepted
              to: paid
            sent:
              from: paid
              to: sent
            finished:
              from: sent
              to: finished
    ```
3. Исправляем класс `App\Service\OrderService`
    ```php
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
    ```
4. Выполняем команду `php bin/console workflow:dump order_process >graph.txt`
5. Визуализируем процесс командой `dot -Tpng graph.png graph.txt` (необходимо установить [Graphviz](https://www.graphviz.org/download/))
6. Выполняем запрос `http://localhost:7777/api/v1/change-state` с payload `{"id": ID, "state": "paid"}`, видим ответ с
   корректным статусом.
7. Повторяем запрос, видим ответ 422.

## Усложняем бизнес-процесс

1. Устанавливаем пакет `symfony/security-bundle:6.4.*`
2. Исправляем класс `App\Entity\Order`
    ```php
    <?php
    
    namespace App\Entity;
    
    use Doctrine\ORM\Mapping as ORM;
    
    #[ORM\Table(name: '`order`')]
    #[ORM\Entity]
    class Order
    {
        #[ORM\Column(type: 'bigint', unique: true, nullable: false)]
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'IDENTITY')]
        private ?int $id = null;
    
        #[ORM\Column(type: 'string', nullable: false)]
        private string $state;
        
        #[ORM\Column(type: 'boolean', nullable: true)]
        private ?bool $isCollected = false;
    
        public function getId(): ?int
        {
            return $this->id;
        }
    
        public function setId(?int $id): void
        {
            $this->id = $id;
        }
    
        public function getState(): string
        {
            return $this->state;
        }
    
        public function setState(string $state): void
        {
            $this->state = $state;
        }
    
        public function isCollected(): bool
        {
            return $this->isCollected ?? false;
        }
    
        public function setIsCollected(bool $isCollected): void
        {
            $this->isCollected = $isCollected;
        }
    }
    ```
3. Выполняем команды
    ```shell
    php bin/console doctrine:migrations:diff
    php bin/console doctrine:migrations:migrate
    ```
4. Исправляем файл `config/packages/workflow.yaml`
    ```yaml
    framework:
      workflows:
        order_process:
          type: 'state_machine'
          marking_store:
            type: 'method'
            property: 'state'
          supports:
            - App\Entity\Order
          initial_marking: new
          places:
            - new
            - accepted
            - paid
            - sent
            - finished
          transitions:
            accept:
              from: new
              to: accepted
            pay:
              from: accepted
              to: paid
            send:
              guard: 'subject.isCollected()'
              from: paid
              to: sent
            deliver:
              from: sent
              to: finished
    ```
5. Исправляем класс `App\Controller\Api\v1\ChangeState\Controller`
    ```php
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
    
            if (!$this->orderService->changeState($order, $request->state)) {
                return new JsonResponse(['message' => 'Cannot change state'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
    
            return new JsonResponse(['id' => $order->getId(), 'state' => $order->getState()]);
        }
    }
    ```
6. Исправляем класс `App\Service\OrderService`
    ```php
    <?php
    
    namespace App\Service;
    
    use App\Entity\Order;
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Component\Workflow\WorkflowInterface;
    
    class OrderService
    {
        public function __construct(
            private readonly EntityManagerInterface $entityManager,
            private readonly WorkflowInterface $orderProcessStateMachine,
        ) {
        }
    
        public function changeState(Order $order, string $transition): bool
        {
            if ($this->orderProcessStateMachine->can($order, $transition)) {
                $this->orderProcessStateMachine->apply($order, $transition);
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
    ```
7. Выполняем запрос `http://localhost:7777/api/v1/change-state` с payload `{"id": ID, "state": "send"}`, видим ответ 422.
8. Проставляем в БД для заказа `is_collected = true`  ещё раз выполняем запрос, видим ответ с корректным статусом.

## Подписываемся на события

1. Исправляем класс `App\Entity\Order`
    ```php
    <?php
    
    namespace App\Entity;
    
    use DateTimeInterface;
    use Doctrine\ORM\Mapping as ORM;
    
    #[ORM\Table(name: '`order`')]
    #[ORM\Entity]
    class Order
    {
        #[ORM\Column(type: 'bigint', unique: true, nullable: false)]
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'IDENTITY')]
        private ?int $id = null;
    
        #[ORM\Column(type: 'string', nullable: false)]
        private string $state;
    
        #[ORM\Column(type: 'boolean', nullable: true)]
        private ?bool $isCollected = false;
    
        #[ORM\Column(type: 'datetime', nullable: true)]
        private DateTimeInterface $deliveredAt;
    
        public function getId(): ?int
        {
            return $this->id;
        }
    
        public function setId(?int $id): void
        {
            $this->id = $id;
        }
    
        public function getState(): string
        {
            return $this->state;
        }
    
        public function setState(string $state): void
        {
            $this->state = $state;
        }
    
        public function isCollected(): bool
        {
            return $this->isCollected ?? false;
        }
    
        public function setIsCollected(bool $isCollected): void
        {
            $this->isCollected = $isCollected;
        }
    
        public function getDeliveredAt(): DateTimeInterface
        {
            return $this->deliveredAt;
        }
    
        public function setDeliveredAt(DateTimeInterface $deliveredAt): void
        {
            $this->deliveredAt = $deliveredAt;
        }
    }
    ```
2. Выполняем команды
    ```shell
    php bin/console doctrine:migrations:diff
    php bin/console doctrine:migrations:migrate
    ```
3. Добавляем класс `App\EventListener\DeliveredEventListener`
    ```php
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
    ```
4. Выполняем запрос `http://localhost:7777/api/v1/change-state` с payload `{"id": ID, "state": "deliver"}`, видим ответ
   с верным статусом и проставленную дату доставки в БД.

## Переходим на workflow

1. Исправляем класс `App\Entity\Order`
    ```php
    <?php
    
    namespace App\Entity;
    
    use DateTimeInterface;
    use Doctrine\ORM\Mapping as ORM;
    
    #[ORM\Table(name: '`order`')]
    #[ORM\Entity]
    class Order
    {
        #[ORM\Column(type: 'bigint', unique: true, nullable: false)]
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'IDENTITY')]
        private ?int $id = null;
    
        #[ORM\Column(type: 'json', nullable: true)]
        private ?array $state;
    
        #[ORM\Column(type: 'boolean', nullable: true)]
        private ?bool $isCollected = false;
    
        #[ORM\Column(type: 'datetime', nullable: true)]
        private DateTimeInterface $deliveredAt;
    
        public function getId(): ?int
        {
            return $this->id;
        }
    
        public function setId(?int $id): void
        {
            $this->id = $id;
        }
    
        public function getState(): ?array
        {
            return $this->state;
        }
    
        public function setState(array $state): void
        {
            $this->state = $state;
        }
    
        public function isCollected(): bool
        {
            return $this->isCollected ?? false;
        }
    
        public function setIsCollected(bool $isCollected): void
        {
            $this->isCollected = $isCollected;
        }
    
        public function getDeliveredAt(): DateTimeInterface
        {
            return $this->deliveredAt;
        }
    
        public function setDeliveredAt(DateTimeInterface $deliveredAt): void
        {
            $this->deliveredAt = $deliveredAt;
        }
    }
    ```
2. Добавляем миграцию командой `php bin/console doctrine:migrations:generate` и исправляем методы `up` и `down`
    ```php
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP COLUMN state');
        $this->addSql('ALTER TABLE "order" ADD COLUMN state JSON');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP COLUMN state');
        $this->addSql('ALTER TABLE "order" ADD COLUMN state VARCHAR(255)');
    }
    ```
3. Удаляем запись в таблице `order` и выполняем команду `php bin/console doctrine:migrations:migrate`
4. Исправляем файл `config/packages/workflow.yaml`
    ```yaml
    framework:
      workflows:
        order_process:
          type: 'workflow'
          marking_store:
            type: 'method'
            property: 'state'
          supports:
            - App\Entity\Order
          initial_marking: new
          places:
            - new
            - accepted
            - paid
            - collected
            - sent
            - finished
          transitions:
            accept:
              from: new
              to: accepted
            pay_new:
              from: new
              to: [new, paid]
            pay_accepted:
              from: accepted
              to: [accepted, paid]
            collect:
              from: accepted
              to: [accepted, collected]
            send:
              from: [accepted, paid, collected]
              to: sent
            deliver:
              from: sent
              to: finished
    ```
5. Исправляем класс `App\Service\OrderService`
    ```php
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
    ```
6. Выполняем команду `php bin/console workflow:dump order_process >graph.txt`
7. Визуализируем процесс командой `dot -Tpng graph.png graph.txt`
8. Создаём новую запись в БД и проводим процесс для неё по цепочке переходов `pay_new`, `accept`, `collect`,
   `send`, видим, что это удаётся.
9. Создаём ещё одну запись в БД, проводим для неё процесс по цепочке переходов `accept`, `pay_accepted`,
   `send`, видим ошибку.

## Реализуем свой способ хранения множественного состояния

1. Исправляем класс `App\Entity\Order`
    ```php
    <?php
    
    namespace App\Entity;
    
    use DateTimeInterface;
    use Doctrine\ORM\Mapping as ORM;
    
    #[ORM\Table(name: '`order`')]
    #[ORM\Entity]
    class Order
    {
        #[ORM\Column(type: 'bigint', unique: true, nullable: false)]
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'IDENTITY')]
        private ?int $id = null;
    
        #[ORM\Column(type: 'string', nullable: false)]
        private string $state;
    
        #[ORM\Column(type: 'boolean', nullable: true)]
        private ?bool $isCollected = false;
    
        #[ORM\Column(type: 'boolean', nullable: true)]
        private ?bool $isPaid = false;
    
        #[ORM\Column(type: 'datetime', nullable: true)]
        private DateTimeInterface $deliveredAt;
    
        public function getId(): ?int
        {
            return $this->id;
        }
    
        public function setId(?int $id): void
        {
            $this->id = $id;
        }
    
        public function getState(): string
        {
            return $this->state;
        }
    
        public function setState(string $state): void
        {
            $this->state = $state;
        }
    
        public function isCollected(): bool
        {
            return $this->isCollected ?? false;
        }
    
        public function setIsCollected(bool $isCollected): void
        {
            $this->isCollected = $isCollected;
        }
    
        public function getDeliveredAt(): DateTimeInterface
        {
            return $this->deliveredAt;
        }
    
        public function setDeliveredAt(DateTimeInterface $deliveredAt): void
        {
            $this->deliveredAt = $deliveredAt;
        }
    
        public function isPaid(): bool
        {
            return $this->isPaid ?? false;
        }
    
        public function setIsPaid(bool $isPaid): void
        {
            $this->isPaid = $isPaid;
        }
    }
    ```
2. Добавляем класс `App\Service\OrderMarkingStore`
3. Добавляем миграцию командой `php bin/console doctrine:migrations:generate` и исправляем методы `up` и `down`
    ```php
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP COLUMN state');
        $this->addSql('ALTER TABLE "order" ADD COLUMN state VARCHAR(255)');
        $this->addSql('ALTER TABLE "order" ADD COLUMN is_paid BOOLEAN');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP COLUMN is_paid');
        $this->addSql('ALTER TABLE "order" DROP COLUMN state');
        $this->addSql('ALTER TABLE "order" ADD COLUMN state JSON');
    }
    ```
4. Удаляем записи в таблице `order` и выполняем команду `php bin/console doctrine:migrations:migrate`
5. Исправляем класс `App\Controller\Api\v1\ChangeState\Controller`
    ```php
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
    
            if (!$this->orderService->changeState($order, $request->state)) {
                return new JsonResponse(['message' => 'Cannot change state'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
    
            return new JsonResponse(
                [
                    'id' => $order->getId(),
                    'state' => $order->getState(),
                    'isPaid' => $order->isPaid(),
                    'isCollected' => $order->isCollected()
                ]
            );
        }
    }
    ```
6. Добавляем класс `App\Service\OrderMarkingStore`
    ```php
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
    ```
7. Исправляем файл `config/packages/workflow.yaml`
    ```yaml
    framework:
      workflows:
        order_process:
          type: 'workflow'
          marking_store:
            type: 'method'
            property: 'state'
          supports:
            - App\Entity\Order
          initial_marking: new
          places:
            - new
            - accepted
            - paid
            - collected
            - sent
            - finished
          transitions:
            accept:
              from: new
              to: accepted
            pay_new:
              from: new
              to: [new, paid]
            pay_accepted:
              from: accepted
              to: [accepted, paid]
            collect:
              from: accepted
              to: [accepted, collected]
            send:
              from: [accepted, paid, collected]
              to: sent
            deliver:
              from: sent
              to: finished
    ```
8. Выполняем команду `php bin/console workflow:dump order_process >graph.txt`
9. Визуализируем процесс командой `dot -Tpng graph.png graph.txt`
10. Создаём новую запись в БД со значением `state = "new"` и проводим процесс для неё по цепочке переходов `pay_new`,
    `accept`, `collect`, `send`, видим, что это удаётся.
11. Создаём ещё одну запись в БД со значением `state = "new"`, проводим для неё процесс по цепочке переходов
   `accept`, `pay_accepted`, `send`, видим ошибку.
