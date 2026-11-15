<?php

namespace App\Tests;

use App\Entity\CustomerOrder;
use App\Entity\Menu;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Repository\MenuRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CustomerOrderCancellationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private CustomerOrderRepository $orderRepository;
    private MenuRepository $menuRepository;
    private User $customer;
    private User $otherCustomer;
    private Menu $menu;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->orderRepository = $container->get(
            CustomerOrderRepository::class,
        );
        $this->menuRepository = $container->get(MenuRepository::class);
        $userRepository = $container->get(UserRepository::class);

        foreach ($this->orderRepository->findAll() as $order) {
            $this->entityManager->remove($order);
        }

        $this->entityManager->flush();

        foreach ($this->menuRepository->findAll() as $menu) {
            $this->entityManager->remove($menu);
        }

        foreach ($userRepository->findAll() as $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();

        $this->customer = $this->createUser(
            'cancellation-customer@example.com',
            'Camille',
            'Martin',
        );
        $this->otherCustomer = $this->createUser(
            'cancellation-other@example.com',
            'Julie',
            'Dupont',
        );

        $this->menu = (new Menu())
            ->setTitle('Menu annulation')
            ->setSlug('menu-annulation')
            ->setDescription('Menu utilisé pour tester les annulations.')
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

    // Verifies that visitors must log in before cancelling an order.
    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $order = $this->createOrder(
            $this->customer,
            CustomerOrder::STATUS_PENDING,
        );
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/profile/orders/'.$order->getId().'/cancel',
        );

        self::assertResponseRedirects('/login');
    }

    // Verifies that a pending order is cancelled without being deleted.
    public function testCustomerCanCancelPendingOrder(): void
    {
        $order = $this->createOrder(
            $this->customer,
            CustomerOrder::STATUS_PENDING,
        );
        $this->menu->setStock(9);
        $this->entityManager->flush();
        $orderId = $order->getId();
        $menuId = $this->menu->getId();

        $this->client->loginUser($this->customer);
        $crawler = $this->client->request(
            'GET',
            '/profile/orders/'.$orderId,
        );
        $token = $crawler
            ->filter('form[action$="/cancel"] input[name="_token"]')
            ->attr('value');

        $this->client->request(
            'POST',
            '/profile/orders/'.$orderId.'/cancel',
            ['_token' => $token],
        );

        self::assertResponseRedirects('/profile/orders/'.$orderId);
        $this->client->followRedirect();
        self::assertSelectorTextContains(
            '#cancelSuccessModal',
            'Votre commande a bien été annulée.',
        );

        $this->entityManager->clear();
        $cancelledOrder = $this->orderRepository->find($orderId);
        $reloadedMenu = $this->menuRepository->find($menuId);

        self::assertInstanceOf(CustomerOrder::class, $cancelledOrder);
        self::assertSame(
            CustomerOrder::STATUS_CANCELLED,
            $cancelledOrder->getStatus(),
        );
        self::assertInstanceOf(Menu::class, $reloadedMenu);
        self::assertSame(10, $reloadedMenu->getStock());
    }

    // Verifies that a confirmed order can also be cancelled.
    public function testCustomerCanCancelConfirmedOrder(): void
    {
        $order = $this->createOrder(
            $this->customer,
            CustomerOrder::STATUS_CONFIRMED,
        );
        $this->entityManager->flush();
        $orderId = $order->getId();

        $this->client->loginUser($this->customer);
        $crawler = $this->client->request(
            'GET',
            '/profile/orders/'.$orderId,
        );
        $token = $crawler
            ->filter('form[action$="/cancel"] input[name="_token"]')
            ->attr('value');

        $this->client->request(
            'POST',
            '/profile/orders/'.$orderId.'/cancel',
            ['_token' => $token],
        );

        self::assertResponseRedirects('/profile/orders/'.$orderId);

        $this->entityManager->clear();
        $cancelledOrder = $this->orderRepository->find($orderId);

        self::assertInstanceOf(CustomerOrder::class, $cancelledOrder);
        self::assertSame(
            CustomerOrder::STATUS_CANCELLED,
            $cancelledOrder->getStatus(),
        );
    }

    // Verifies that the server refuses cancellation for every later status.
    #[DataProvider('nonCancellableStatuses')]
    public function testOrdersWithOtherStatusesCannotBeCancelled(
        string $status,
    ): void
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
            '/profile/orders/'.$orderId,
        );
        $token = $crawler
            ->filter('form[action$="/cancel"] input[name="_token"]')
            ->attr('value');

        $order->setStatus($status);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/profile/orders/'.$orderId.'/cancel',
            ['_token' => $token],
        );

        self::assertResponseRedirects('/profile/orders/'.$orderId);
        $this->client->followRedirect();
        self::assertSelectorTextContains(
            '#cancelUnavailableResultModal',
            'Cette commande ne peut plus être annulée.',
        );

        $orderRepository = static::getContainer()->get(
            CustomerOrderRepository::class,
        );
        $unchangedOrder = $orderRepository->find($orderId);

        self::assertInstanceOf(CustomerOrder::class, $unchangedOrder);
        self::assertSame($status, $unchangedOrder->getStatus());
    }

    public static function nonCancellableStatuses(): iterable
    {
        yield 'preparing' => [CustomerOrder::STATUS_PREPARING];
        yield 'ready' => [CustomerOrder::STATUS_READY];
        yield 'delivered' => [CustomerOrder::STATUS_DELIVERED];
        yield 'cancelled' => [CustomerOrder::STATUS_CANCELLED];
    }

    // Verifies that a non-cancellable order displays the information modal.
    public function testUnavailableCancellationModalIsDisplayed(): void
    {
        $order = $this->createOrder(
            $this->customer,
            CustomerOrder::STATUS_PREPARING,
        );
        $this->entityManager->flush();

        $this->client->loginUser($this->customer);
        $this->client->request(
            'GET',
            '/profile/orders/'.$order->getId(),
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '#cancelUnavailableModal',
            'Cette commande ne peut plus être annulée.',
        );
        self::assertSelectorExists(
            '[data-bs-target="#cancelUnavailableModal"]',
        );
    }

    // Verifies that cancellation requires a valid CSRF token.
    public function testCancellationRequiresValidCsrfToken(): void
    {
        $order = $this->createOrder(
            $this->customer,
            CustomerOrder::STATUS_PENDING,
        );
        $this->entityManager->flush();

        $this->client->loginUser($this->customer);
        $this->client->request(
            'POST',
            '/profile/orders/'.$order->getId().'/cancel',
            ['_token' => 'invalid-token'],
        );

        self::assertResponseStatusCodeSame(403);

        $this->entityManager->refresh($order);
        self::assertSame(CustomerOrder::STATUS_PENDING, $order->getStatus());
    }

    // Verifies that a customer cannot cancel another customer's order.
    public function testCustomerCannotCancelAnotherCustomersOrder(): void
    {
        $order = $this->createOrder(
            $this->otherCustomer,
            CustomerOrder::STATUS_PENDING,
        );
        $this->entityManager->flush();

        $this->client->loginUser($this->customer);
        $this->client->request(
            'POST',
            '/profile/orders/'.$order->getId().'/cancel',
            ['_token' => 'irrelevant-token'],
        );

        self::assertResponseStatusCodeSame(404);

        $this->entityManager->refresh($order);
        self::assertSame(CustomerOrder::STATUS_PENDING, $order->getStatus());
    }

    private function createUser(
        string $email,
        string $firstName,
        string $lastName,
    ): User {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setPhone('0612345678')
            ->setAddress('24 rue Sainte-Catherine, 33000 Bordeaux')
            ->setRoles([])
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
            ->setDeliveryCharge('0.00')
            ->setTotalPrice('120.00')
            ->setDeliveryAddress(
                '24 rue Sainte-Catherine, 33000 Bordeaux',
            );

        $this->entityManager->persist($order);

        return $order;
    }
}
