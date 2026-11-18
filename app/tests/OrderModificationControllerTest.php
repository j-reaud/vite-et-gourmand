<?php

namespace App\Tests;

use App\Entity\CustomerOrder;
use App\Entity\Menu;
use App\Entity\OrderModification;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Repository\MenuRepository;
use App\Repository\OrderModificationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderModificationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private CustomerOrderRepository $orderRepository;
    private OrderModificationRepository $modificationRepository;
    private User $customer;
    private User $otherCustomer;
    private User $employee;
    private Menu $menu;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->orderRepository = $container->get(
            CustomerOrderRepository::class,
        );
        $this->modificationRepository = $container->get(
            OrderModificationRepository::class,
        );
        $menuRepository = $container->get(MenuRepository::class);
        $userRepository = $container->get(UserRepository::class);

        foreach ($this->orderRepository->findAll() as $order) {
            $this->entityManager->remove($order);
        }

        $this->entityManager->flush();

        foreach ($menuRepository->findAll() as $menu) {
            $this->entityManager->remove($menu);
        }

        foreach ($userRepository->findAll() as $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();

        $this->customer = $this->createUser(
            'order-edit-customer@example.com',
            [],
        );
        $this->otherCustomer = $this->createUser(
            'order-edit-other@example.com',
            [],
        );
        $this->employee = $this->createUser(
            'order-edit-employee@example.com',
            ['ROLE_EMPLOYEE'],
        );

        $this->menu = (new Menu())
            ->setTitle('Menu modification')
            ->setSlug('menu-modification')
            ->setDescription('Menu utilisé pour tester les modifications.')
            ->setImagePath('images/home/menu-spring.webp')
            ->setPrice('120.00')
            ->setMinimumPeople(4)
            ->setTheme('Classique')
            ->setDiet('Classique')
            ->setStock(10)
            ->setIsPublished(true);

        $this->entityManager->persist($this->menu);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->orderRepository->findAll() as $order) {
            $this->entityManager->remove($order);
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    // Verifies that a customer can edit their own pending order.
    public function testCustomerCanModifyOwnPendingOrder(): void
    {
        $order = $this->createOrder(
            $this->customer,
            CustomerOrder::STATUS_PENDING,
        );
        $this->entityManager->flush();
        $orderId = $order->getId();

        $this->menu->setPrice('200.00');
        $this->entityManager->flush();

        $this->client->loginUser($this->customer);
        $crawler = $this->client->request(
            'GET',
            '/profile/orders/'.$orderId.'/edit',
        );

        self::assertResponseIsSuccessful();

        $token = $crawler
            ->filter('input[name="customer_order_edit_form[_token]"]')
            ->attr('value');
        $deliveryAt = new \DateTimeImmutable('+10 days');

        $this->client->request(
            'POST',
            '/profile/orders/'.$orderId.'/edit',
            [
                'customer_order_edit_form' => [
                    'deliveryAddress' => '8 rue Sainte-Catherine, 33000 Bordeaux',
                    'deliveryAt' => $deliveryAt->format('Y-m-d\TH:i'),
                    'numberOfPeople' => 8,
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseRedirects('/profile/orders/'.$orderId);

        $this->entityManager->clear();
        $updatedOrder = $this->orderRepository->find($orderId);

        self::assertInstanceOf(CustomerOrder::class, $updatedOrder);
        self::assertSame(
            '8 rue Sainte-Catherine, 33000 Bordeaux',
            $updatedOrder->getDeliveryAddress(),
        );
        self::assertSame(8, $updatedOrder->getNumberOfPeople());
        self::assertSame('240.00', $updatedOrder->getMenuPrice());
        self::assertSame('5.00', $updatedOrder->getDeliveryCharge());
        self::assertSame('245.00', $updatedOrder->getTotalPrice());
    }

    // Verifies that a customer cannot edit another customer's order.
    public function testCustomerCannotModifyAnotherCustomersOrder(): void
    {
        $order = $this->createOrder(
            $this->otherCustomer,
            CustomerOrder::STATUS_PENDING,
        );
        $this->entityManager->flush();

        $this->client->loginUser($this->customer);
        $this->client->request(
            'GET',
            '/profile/orders/'.$order->getId().'/edit',
        );

        self::assertResponseStatusCodeSame(404);
    }

    // Verifies that customer editing is blocked after order acceptance.
    public function testCustomerCannotModifyAcceptedOrder(): void
    {
        $order = $this->createOrder(
            $this->customer,
            CustomerOrder::STATUS_CONFIRMED,
        );
        $this->entityManager->flush();

        $this->client->loginUser($this->customer);
        $this->client->request(
            'GET',
            '/profile/orders/'.$order->getId().'/edit',
        );

        self::assertResponseRedirects(
            '/profile/orders/'.$order->getId(),
        );

        $this->client->followRedirect();
        self::assertSelectorTextContains(
            '.alert-warning',
            'Cette commande ne peut plus être modifiée.',
        );
        self::assertSelectorNotExists(
            'a[href$="/edit"]',
        );
    }

    // Verifies that the guest count cannot be lower than the menu minimum.
    public function testCustomerCannotUseFewerThanMinimumPeople(): void
    {
        $order = $this->createOrder(
            $this->customer,
            CustomerOrder::STATUS_PENDING,
        );
        $this->entityManager->flush();
        $orderId = $order->getId();

        $this->client->loginUser($this->customer);
        $crawler = $this->client->request(
            'GET',
            '/profile/orders/'.$orderId.'/edit',
        );
        $token = $crawler
            ->filter('input[name="customer_order_edit_form[_token]"]')
            ->attr('value');

        $this->client->request(
            'POST',
            '/profile/orders/'.$orderId.'/edit',
            [
                'customer_order_edit_form' => [
                    'deliveryAddress' => '24 rue Sainte-Catherine, 33000 Bordeaux',
                    'deliveryAt' => (new \DateTimeImmutable('+8 days'))
                        ->format('Y-m-d\TH:i'),
                    'numberOfPeople' => 3,
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains(
            '.invalid-feedback',
            'Ce menu nécessite au minimum 4 personnes.',
        );

        $this->entityManager->clear();
        $unchangedOrder = $this->orderRepository->find($orderId);

        self::assertInstanceOf(CustomerOrder::class, $unchangedOrder);
        self::assertSame(4, $unchangedOrder->getNumberOfPeople());
    }

    // Verifies that ordinary customers cannot access employee order pages.
    public function testCustomerCannotAccessEmployeeOrderArea(): void
    {
        $this->client->loginUser($this->customer);
        $this->client->request('GET', '/employee/orders');

        self::assertResponseStatusCodeSame(403);
    }

    // Verifies that an employee can modify an order after customer contact.
    public function testEmployeeCanModifyOrderAndCreatesAuditRecord(): void
    {
        $order = $this->createOrder(
            $this->customer,
            CustomerOrder::STATUS_READY,
        );
        $this->entityManager->flush();
        $orderId = $order->getId();

        $this->client->loginUser($this->employee);
        $crawler = $this->client->request(
            'GET',
            '/employee/orders/'.$orderId.'/edit',
        );

        self::assertResponseIsSuccessful();

        $token = $crawler
            ->filter('input[name="employee_order_edit_form[_token]"]')
            ->attr('value');

        $this->client->request(
            'POST',
            '/employee/orders/'.$orderId.'/edit',
            [
                'employee_order_edit_form' => [
                    'deliveryAddress' => '15 cours Victor-Hugo, 33000 Bordeaux',
                    'deliveryAt' => (new \DateTimeImmutable('+12 days'))
                        ->format('Y-m-d\TH:i'),
                    'numberOfPeople' => 6,
                    'contactConfirmed' => '1',
                    'contactMethod' => OrderModification::CONTACT_PHONE,
                    'reason' => 'Le client a demandé une nouvelle date.',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseRedirects('/employee/orders');

        $this->entityManager->clear();
        $updatedOrder = $this->orderRepository->find($orderId);
        $modifications = $this->modificationRepository->findBy([
            'customerOrder' => $orderId,
        ]);

        self::assertInstanceOf(CustomerOrder::class, $updatedOrder);
        self::assertSame(6, $updatedOrder->getNumberOfPeople());
        self::assertCount(1, $modifications);
        self::assertSame(
            OrderModification::CONTACT_PHONE,
            $modifications[0]->getContactMethod(),
        );
        self::assertSame(
            'Le client a demandé une nouvelle date.',
            $modifications[0]->getReason(),
        );
        self::assertSame(
            4,
            $modifications[0]->getPreviousData()['numberOfPeople'],
        );
        self::assertSame(
            6,
            $modifications[0]->getNewData()['numberOfPeople'],
        );
        self::assertSame(
            $this->employee->getId(),
            $modifications[0]->getModifiedBy()?->getId(),
        );
    }

    // Verifies that employee contact information is mandatory.
    public function testEmployeeCannotModifyWithoutContactDetails(): void
    {
        $order = $this->createOrder(
            $this->customer,
            CustomerOrder::STATUS_CONFIRMED,
        );
        $this->entityManager->flush();
        $orderId = $order->getId();

        $this->client->loginUser($this->employee);
        $crawler = $this->client->request(
            'GET',
            '/employee/orders/'.$orderId.'/edit',
        );
        $token = $crawler
            ->filter('input[name="employee_order_edit_form[_token]"]')
            ->attr('value');

        $this->client->request(
            'POST',
            '/employee/orders/'.$orderId.'/edit',
            [
                'employee_order_edit_form' => [
                    'deliveryAddress' => '24 rue Sainte-Catherine, 33000 Bordeaux',
                    'deliveryAt' => (new \DateTimeImmutable('+8 days'))
                        ->format('Y-m-d\TH:i'),
                    'numberOfPeople' => 5,
                    'contactMethod' => '',
                    'reason' => '',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains(
            '.invalid-feedback',
            'Vous devez confirmer le contact client.',
        );
        self::assertSelectorTextContains(
            'body',
            'Veuillez choisir un moyen de contact.',
        );
        self::assertSelectorTextContains(
            'body',
            'Veuillez saisir le motif de la modification.',
        );

        $this->entityManager->clear();
        $unchangedOrder = $this->orderRepository->find($orderId);

        self::assertInstanceOf(CustomerOrder::class, $unchangedOrder);
        self::assertSame(4, $unchangedOrder->getNumberOfPeople());
        self::assertCount(
            0,
            $this->modificationRepository->findBy([
                'customerOrder' => $orderId,
            ]),
        );
    }

    // Verifies that employees cannot modify an order after dispatch.
    public function testEmployeeCannotModifyLockedOrder(): void
    {
        $order = $this->createOrder(
            $this->customer,
            CustomerOrder::STATUS_OUT_FOR_DELIVERY,
        );
        $this->entityManager->flush();

        $this->client->loginUser($this->employee);
        $this->client->request(
            'GET',
            '/employee/orders/'.$order->getId().'/edit',
        );

        self::assertResponseRedirects('/employee/orders');
        $this->client->followRedirect();
        self::assertSelectorTextContains(
            '.alert-warning',
            'Cette commande ne peut plus être modifiée.',
        );
    }

    private function createUser(string $email, array $roles): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setPhone('0612345678')
            ->setAddress('24 rue Sainte-Catherine, 33000 Bordeaux')
            ->setRoles($roles)
            ->setIsActive(true)
            ->setPassword('hashed-password');

        $this->entityManager->persist($user);

        return $user;
    }

    private function createOrder(
        User $customer,
        string $status,
    ): CustomerOrder {
        $order = (new CustomerOrder())
            ->setCustomer($customer)
            ->setMenu($this->menu)
            ->setMenuName((string) $this->menu->getTitle())
            ->setOrderedAt(new \DateTimeImmutable('-1 day'))
            ->setDeliveryAt(new \DateTimeImmutable('+7 days'))
            ->setNumberOfPeople(4)
            ->setStatus($status)
            ->setMenuPrice('120.00')
            ->setDiscountAmount('0.00')
            ->setDeliveryDistanceKm('0.00')
            ->setDeliveryCharge('5.00')
            ->setTotalPrice('125.00')
            ->setDeliveryAddress(
                '24 rue Sainte-Catherine, 33000 Bordeaux',
            );

        $this->entityManager->persist($order);

        return $order;
    }
}
