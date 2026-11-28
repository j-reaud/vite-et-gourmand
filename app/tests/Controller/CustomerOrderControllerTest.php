<?php

namespace App\Tests\Controller;

use App\Entity\CustomerOrder;
use App\Entity\Menu;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Repository\MenuRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CustomerOrderControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $customer;
    private User $otherCustomer;
    private Menu $menu;
    private CustomerOrder $recentOrder;
    private CustomerOrder $olderOrder;
    private CustomerOrder $otherCustomerOrder;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);

        $orderRepository = $container->get(CustomerOrderRepository::class);
        $menuRepository = $container->get(MenuRepository::class);
        $userRepository = $container->get(UserRepository::class);

        foreach ($orderRepository->findAll() as $order) {
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
            'customer@example.com',
            'Camille',
            'Martin',
        );

        $this->otherCustomer = $this->createUser(
            'other-customer@example.com',
            'Julie',
            'Dupont',
        );

        $this->menu = (new Menu())
            ->setTitle('Menu de test')
            ->setSlug('menu-de-test')
            ->setDescription('Description du menu de test.')
            ->setImagePath('images/home/menu-spring.webp')
            ->setPrice('120.00')
            ->setMinimumPeople(4)
            ->setTheme('Classique')
            ->setDiet('Classique')
            ->setStock(10)
            ->setIsPublished(true);

        $this->entityManager->persist($this->menu);

        $this->recentOrder = $this->createOrder(
            $this->customer,
            'Menu récent',
            new \DateTimeImmutable('-2 days'),
            new \DateTimeImmutable('+7 days'),
            CustomerOrder::STATUS_CONFIRMED,
            '140.00',
        );

        $this->olderOrder = $this->createOrder(
            $this->customer,
            'Menu ancien',
            new \DateTimeImmutable('-14 months'),
            new \DateTimeImmutable('-13 months'),
            CustomerOrder::STATUS_DELIVERED,
            '180.00',
        );

        $this->otherCustomerOrder = $this->createOrder(
            $this->otherCustomer,
            'Menu confidentiel',
            new \DateTimeImmutable('-1 day'),
            new \DateTimeImmutable('+10 days'),
            CustomerOrder::STATUS_PREPARING,
            '220.00',
        );

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $orderRepository = $this->entityManager
            ->getRepository(CustomerOrder::class);

        foreach ($orderRepository->findAll() as $order) {
            $this->entityManager->remove($order);
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testAuthenticationIsRequired(): void
    {
        $this->client->request('GET', '/profile/orders');

        self::assertResponseRedirects('/login');
    }

    public function testOrdersLinkIsVisibleInCustomerAccount(): void
    {
        $this->client->loginUser($this->customer);
        $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(
            'a[href="/profile/orders"]'
        );
        self::assertSelectorTextContains(
            'a[href="/profile/orders"]',
            'Mes commandes'
        );
    }

    public function testCustomerOnlySeesOwnOrdersNewestFirst(): void
    {
        $this->client->loginUser($this->customer);
        $crawler = $this->client->request('GET', '/profile/orders');

        self::assertResponseIsSuccessful();

        $rows = $crawler->filter('.order-table tbody tr');

        self::assertCount(2, $rows);
        self::assertStringContainsString(
            'Menu récent',
            $rows->eq(0)->text()
        );
        self::assertStringContainsString(
            'Menu ancien',
            $rows->eq(1)->text()
        );
        self::assertStringNotContainsString(
            'Menu confidentiel',
            $crawler->filter('body')->text()
        );
    }

    public function testCustomerCanViewOwnOrderDetails(): void
    {
        $this->client->loginUser($this->customer);
        $this->client->request(
            'GET',
            '/profile/orders/'.$this->recentOrder->getId()
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            'h1',
            $this->recentOrder->getReference()
        );
        self::assertSelectorTextContains(
            '.order-detail',
            'customer@example.com'
        );
        self::assertSelectorTextContains(
            '.order-detail',
            '24 rue Sainte-Catherine'
        );
        self::assertSelectorTextContains(
            '.order-detail',
            'Menu récent'
        );
        self::assertSelectorTextContains(
            '.order-detail',
            '140,00 €'
        );
        self::assertSelectorTextContains(
            '.order-status',
            'Acceptée'
        );
    }

    public function testCustomerCannotViewAnotherCustomersOrder(): void
    {
        $this->client->loginUser($this->customer);
        $this->client->request(
            'GET',
            '/profile/orders/'.$this->otherCustomerOrder->getId()
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testEmptyStateIsDisplayedWhenCustomerHasNoOrders(): void
    {
        $customerWithoutOrders = $this->createUser(
            'empty-customer@example.com',
            'Jean',
            'Durand',
        );

        $this->entityManager->flush();

        $this->client->loginUser($customerWithoutOrders);
        $this->client->request('GET', '/profile/orders');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '.orders-empty',
            'Vous n’avez pas encore de commande'
        );
    }

    public function testOrdersCanBeFiltered(): void
    {
        $this->client->loginUser($this->customer);

        $crawler = $this->client->request(
            'GET',
            '/profile/orders?status=delivered'
        );

        self::assertResponseIsSuccessful();
        self::assertCount(
            1,
            $crawler->filter('.order-table tbody tr')
        );
        self::assertSelectorTextContains(
            '.order-table',
            'Menu ancien'
        );

        $crawler = $this->client->request(
            'GET',
            '/profile/orders?search=Menu%20récent'
        );

        self::assertResponseIsSuccessful();
        self::assertCount(
            1,
            $crawler->filter('.order-table tbody tr')
        );
        self::assertSelectorTextContains(
            '.order-table',
            'Menu récent'
        );

        $crawler = $this->client->request(
            'GET',
            '/profile/orders?period=3'
        );

        self::assertResponseIsSuccessful();
        self::assertCount(
            1,
            $crawler->filter('.order-table tbody tr')
        );
        self::assertSelectorTextContains(
            '.order-table',
            'Menu récent'
        );

        $crawler = $this->client->request(
            'GET',
            '/profile/orders?search='.urlencode(
                'Menu '.$this->recentOrder->getId()
            )
        );

        self::assertResponseIsSuccessful();
        self::assertCount(
            0,
            $crawler->filter('.order-table tbody tr')
        );
        self::assertSelectorTextContains(
            '.orders-empty',
            'Aucune commande ne correspond à ces filtres'
        );
    }

    // Verifies that tracking is unavailable while an order is pending.
    public function testPendingOrderDoesNotExposeTracking(): void
    {
        $pendingOrder = $this->createOrder(
            $this->customer,
            'Menu en attente',
            new \DateTimeImmutable(),
            new \DateTimeImmutable('+7 days'),
            CustomerOrder::STATUS_PENDING,
            '120.00',
        );

        $this->entityManager->flush();
        $this->client->loginUser($this->customer);
        $this->client->request(
            'GET',
            '/profile/orders/'.$pendingOrder->getId(),
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#order-tracking-tab');
        self::assertSelectorNotExists('#order-tracking-panel');
    }

    // Verifies that accepted orders show their chronological, dated history.
    public function testAcceptedOrderDisplaysTrackingHistory(): void
    {
        $this->client->loginUser($this->customer);
        $crawler = $this->client->request(
            'GET',
            '/profile/orders/'.$this->recentOrder->getId(),
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#order-tracking-tab');
        self::assertSelectorExists('#order-tracking-panel');

        $events = $crawler->filter('.order-tracking__item');

        self::assertCount(2, $events);
        self::assertStringContainsString('En attente', $events->eq(0)->text());
        self::assertStringContainsString('Acceptée', $events->eq(1)->text());
        self::assertCount(2, $events->filter('time[datetime]'));
    }

    // Verifies that all later statuses remain visible through delivery.
    public function testCompleteTrackingHistoryEndsWithDeliveredStatus(): void
    {
        $trackedOrder = $this->createOrder(
            $this->customer,
            'Menu avec suivi',
            new \DateTimeImmutable(),
            new \DateTimeImmutable('+7 days'),
            CustomerOrder::STATUS_PENDING,
            '160.00',
        );

        $trackedOrder
            ->setStatus(CustomerOrder::STATUS_CONFIRMED)
            ->setStatus(CustomerOrder::STATUS_PREPARING)
            ->setStatus(CustomerOrder::STATUS_READY)
            ->setStatus(CustomerOrder::STATUS_OUT_FOR_DELIVERY)
            ->setStatus(CustomerOrder::STATUS_DELIVERED);

        $this->entityManager->flush();
        $reference = $trackedOrder->getReference();

        $this->client->loginUser($this->customer);
        $crawler = $this->client->request(
            'GET',
            '/profile/orders/'.$trackedOrder->getId(),
        );

        self::assertResponseIsSuccessful();

        $labels = $crawler
            ->filter('.order-tracking__event strong')
            ->each(static fn ($node): string => trim($node->text()));

        self::assertSame([
            'En attente',
            'Acceptée',
            'En préparation',
            'Prête',
            'En cours de livraison',
            'Livrée',
        ], $labels);

        $crawler = $this->client->request('GET', '/profile/orders');
        $matchingRow = $crawler
            ->filter('.order-table tbody tr')
            ->reduce(
                static fn ($node): bool => str_contains(
                    $node->text(),
                    (string) $reference,
                ),
            );

        self::assertCount(1, $matchingRow);
        self::assertStringContainsString('Livrée', $matchingRow->text());
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
        string $menuName,
        \DateTimeImmutable $orderedAt,
        \DateTimeImmutable $deliveryAt,
        string $status,
        string $totalPrice,
    ): CustomerOrder {
        $order = (new CustomerOrder())
            ->setCustomer($customer)
            ->setMenu($this->menu)
            ->setMenuName($menuName)
            ->setOrderedAt($orderedAt)
            ->setDeliveryAt($deliveryAt)
            ->setNumberOfPeople(6)
            ->setStatus($status)
            ->setMenuPrice('120.00')
            ->setDiscountAmount(null)
            ->setDeliveryCharge('20.00')
            ->setTotalPrice($totalPrice)
            ->setDeliveryAddress(
                '24 rue Sainte-Catherine, 33000 Bordeaux'
            );

        $this->entityManager->persist($order);

        return $order;
    }
}
