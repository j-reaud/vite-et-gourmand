<?php

namespace App\Tests;

use App\Entity\CustomerOrder;
use App\Entity\Menu;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Repository\MenuRepository;
use App\Repository\UserRepository;
use App\Tests\Double\FakeDeliveryCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class OrderPlacementControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private CustomerOrderRepository $orderRepository;
    private MenuRepository $menuRepository;
    private User $customer;
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
            'customer-order@example.com',
            [],
        );

        $this->menu = (new Menu())
            ->setTitle('Menu de commande')
            ->setSlug('menu-de-commande')
            ->setDescription('Description du menu.')
            ->setImagePath('images/home/menu-spring.webp')
            ->setPrice('120.00')
            ->setMinimumPeople(4)
            ->setTheme('Classique')
            ->setDiet('Classique')
            ->setStock(10)
            ->setIsPublished(true)
            ->setSpecialConditions(
                'Conserver au frais jusqu’au moment du service.',
            );

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

    // Verifies that visitors must log in before placing an order.
    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $this->client->request(
            'POST',
            '/profile/orders/new/menu-de-commande',
        );

        self::assertResponseRedirects('/login');
    }

    // Verifies that an order cannot be created without a valid CSRF token.
    public function testOrderRequiresValidCsrfToken(): void
    {
        $this->client->loginUser($this->customer);

        $this->client->request(
            'POST',
            '/profile/orders/new/menu-de-commande',
            [
                'order_placement_form' => [
                    'deliveryAddress' => '10 rue de la Paix, 33000 Bordeaux',
                    'deliveryAt' => (new \DateTimeImmutable('+3 days'))
                        ->format('Y-m-d\TH:i'),
                    'numberOfPeople' => 4,
                    '_token' => 'invalid-token',
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->orderRepository->findAll());
    }

    // Verifies that an authenticated customer can access the order modal.
    public function testAuthenticatedCustomerCanOpenOrderModal(): void
    {
        $this->client->loginUser($this->customer);
        $this->client->request('GET', '/menus/menu-de-commande');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#orderPlacementModal');
        self::assertSelectorExists(
            'form[action="/profile/orders/new/menu-de-commande"]',
        );
        self::assertSelectorTextContains(
            '[data-bs-target="#orderPlacementModal"]',
            'Commander',
        );
    }

    // Verifies that the recap receives the server-calculated Bordeaux fee.
    public function testDeliveryQuoteIncludesBordeauxFee(): void
    {
        $this->client->loginUser($this->customer);
        $crawler = $this->client->request(
            'GET',
            '/menus/menu-de-commande',
        );

        $modal = $crawler->filter('#orderPlacementModal');

        $quoteUrl = $modal->attr(
            'data-order-placement-quote-url-value',
        );
        $quoteToken = $modal->attr(
            'data-order-placement-quote-token-value',
        );

        $this->client->request(
            'POST',
            $quoteUrl,
            [
                '_token' => $quoteToken,
                'deliveryAddress' => '1 Place Pey Berland, 33000 Bordeaux',
                'numberOfPeople' => 6,
            ],
        );

        self::assertResponseIsSuccessful();

        $response = $this->client->getResponse();
        $data = json_decode(
            (string) $response->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame('0.00', $data['distanceKm']);
        self::assertSame(
            FakeDeliveryCalculator::RECOGNIZED_ADDRESS,
            $data['recognizedAddress'],
        );
        self::assertSame('180.00', $data['menuPrice']);
        self::assertSame('0.00', $data['discountAmount']);
        self::assertSame('5.00', $data['deliveryCharge']);
        self::assertSame('185.00', $data['totalPrice']);
    }

    // Verifies that five guests above the minimum trigger a 10% discount.
    public function testDeliveryQuoteAppliesAutomaticDiscountAtThreshold(): void
    {
        $this->client->loginUser($this->customer);
        $crawler = $this->client->request(
            'GET',
            '/menus/menu-de-commande',
        );

        $modal = $crawler->filter('#orderPlacementModal');

        $this->client->request(
            'POST',
            (string) $modal->attr(
                'data-order-placement-quote-url-value',
            ),
            [
                '_token' => $modal->attr(
                    'data-order-placement-quote-token-value',
                ),
                'deliveryAddress' => '1 Place Pey Berland, 33000 Bordeaux',
                'numberOfPeople' => 9,
            ],
        );

        self::assertResponseIsSuccessful();

        $data = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame('270.00', $data['menuPrice']);
        self::assertSame('27.00', $data['discountAmount']);
        self::assertSame('5.00', $data['deliveryCharge']);
        self::assertSame('248.00', $data['totalPrice']);
    }

    // Verifies that the automatic discount is persisted on the order.
    public function testOrderPersistsAutomaticDiscountAtThreshold(): void
    {
        $this->client->loginUser($this->customer);
        $crawler = $this->client->request(
            'GET',
            '/menus/menu-de-commande',
        );

        $csrfToken = $crawler
            ->filter('input[name="order_placement_form[_token]"]')
            ->attr('value');

        $this->client->request(
            'POST',
            '/profile/orders/new/menu-de-commande',
            [
                'order_placement_form' => [
                    'deliveryAddress' => '10 rue de la Paix, 33000 Bordeaux',
                    'deliveryAt' => (new \DateTimeImmutable('+3 days'))
                        ->format('Y-m-d\TH:i'),
                    'numberOfPeople' => 9,
                    '_token' => $csrfToken,
                ],
            ],
        );

        self::assertResponseRedirects('/menus/menu-de-commande');

        $order = $this->orderRepository->findOneBy([
            'customer' => $this->customer,
        ]);

        self::assertInstanceOf(CustomerOrder::class, $order);
        self::assertSame('270.00', $order->getMenuPrice());
        self::assertSame('27.00', $order->getDiscountAmount());
        self::assertSame('5.00', $order->getDeliveryCharge());
        self::assertSame('248.00', $order->getTotalPrice());
    }

    // Verifies order creation, server-side pricing, and stock reduction.
    public function testCustomerCanPlaceOrderWithServerCalculatedPrice(): void
    {
        $this->client->loginUser($this->customer);
        $crawler = $this->client->request(
            'GET',
            '/menus/menu-de-commande',
        );

        $csrfToken = $crawler
            ->filter('input[name="order_placement_form[_token]"]')
            ->attr('value');

        $deliveryAt = new \DateTimeImmutable('+3 days');

        $this->client->request(
            'POST',
            '/profile/orders/new/menu-de-commande',
            [
                'order_placement_form' => [
                    'deliveryAddress' => '10 rue de la Paix, 33000 Bordeaux',
                    'deliveryAt' => $deliveryAt->format('Y-m-d\TH:i'),
                    'numberOfPeople' => 6,
                    'discountAmount' => '9999.00',
                    '_token' => $csrfToken,
                ],
            ],
        );

        self::assertResponseRedirects('/menus/menu-de-commande');
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $email);

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '#orderSuccessModal',
            'Votre commande a bien été transmise',
        );
        self::assertSelectorExists(
            '#orderSuccessModal a[href="/profile/orders"]',
        );

        $order = $this->orderRepository->findOneBy([
            'customer' => $this->customer,
        ]);

        self::assertInstanceOf(CustomerOrder::class, $order);
        self::assertSame(CustomerOrder::STATUS_PENDING, $order->getStatus());
        self::assertSame(6, $order->getNumberOfPeople());
        self::assertSame('180.00', $order->getMenuPrice());
        self::assertSame('0.00', $order->getDiscountAmount());
        self::assertSame('0.00', $order->getDeliveryDistanceKm());
        self::assertSame('5.00', $order->getDeliveryCharge());
        self::assertSame('185.00', $order->getTotalPrice());
        // The address typed by the customer is kept, not the IGN label.
        self::assertSame(
            '10 rue de la Paix, 33000 Bordeaux',
            $order->getDeliveryAddress(),
        );

        self::assertEmailAddressContains(
            $email,
            'To',
            'customer-order@example.com',
        );
        self::assertEmailSubjectContains(
            $email,
            'Confirmation de votre commande '.$order->getReference(),
        );
        self::assertEmailHtmlBodyContains(
            $email,
            (string) $order->getReference(),
        );
        self::assertEmailHtmlBodyContains(
            $email,
            $order->getOrderedAt()?->format('d/m/Y à H:i') ?? '',
        );
        self::assertEmailHtmlBodyContains($email, 'Camille Martin');
        self::assertEmailHtmlBodyContains($email, '0612345678');
        self::assertEmailHtmlBodyContains($email, 'Menu de commande');
        self::assertEmailHtmlBodyContains($email, '1 menu');
        self::assertEmailHtmlBodyContains($email, '180,00 €');
        self::assertEmailHtmlBodyContains($email, '5,00 €');
        self::assertEmailHtmlBodyContains($email, '185,00 €');
        self::assertEmailHtmlBodyContains(
            $email,
            '10 rue de la Paix, 33000 Bordeaux',
        );
        self::assertEmailHtmlBodyContains(
            $email,
            $deliveryAt->format('d/m/Y'),
        );
        self::assertEmailHtmlBodyContains(
            $email,
            $deliveryAt->format('H:i'),
        );
        self::assertEmailHtmlBodyContains(
            $email,
            'Conserver au frais jusqu’au moment du service.',
        );

        $emailCrawler = new Crawler((string) $email->getHtmlBody());

        self::assertSame(
            '6',
            trim($emailCrawler->filterXPath(
                '//td[contains(normalize-space(.), "Nombre de convives")]'
                .'/following-sibling::td[1]',
            )->text()),
        );
        self::assertSame(
            '0,00 €',
            trim($emailCrawler->filterXPath(
                '//td[contains(normalize-space(.), "Réduction")]'
                .'/following-sibling::td[1]',
            )->text()),
        );

        $menu = $this->menuRepository->findOneBy([
            'slug' => 'menu-de-commande',
        ]);

        self::assertInstanceOf(Menu::class, $menu);
        self::assertSame(9, $menu->getStock());
    }

    // Verifies that the menu's minimum number of people is enforced.
    public function testOrderCannotBePlacedBelowMinimumPeople(): void
    {
        $this->client->loginUser($this->customer);
        $crawler = $this->client->request(
            'GET',
            '/menus/menu-de-commande',
        );

        $csrfToken = $crawler
            ->filter('input[name="order_placement_form[_token]"]')
            ->attr('value');

        $this->client->request(
            'POST',
            '/profile/orders/new/menu-de-commande',
            [
                'order_placement_form' => [
                    'deliveryAddress' => '10 rue de la Paix, 33000 Bordeaux',
                    'deliveryAt' => (new \DateTimeImmutable('+3 days'))
                        ->format('Y-m-d\TH:i'),
                    'numberOfPeople' => 3,
                    '_token' => $csrfToken,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains(
            '.invalid-feedback',
            'Ce menu nécessite au minimum 4 personnes.',
        );
        self::assertSelectorExists(
            '#orderPlacementModal[data-order-placement-open-recap-value="true"]',
        );
        self::assertCount(0, $this->orderRepository->findAll());
    }

    // Verifies that a delivery date in the past is rejected by the server.
    public function testOrderCannotUsePastDeliveryDate(): void
    {
        $this->client->loginUser($this->customer);
        $crawler = $this->client->request(
            'GET',
            '/menus/menu-de-commande',
        );

        $csrfToken = $crawler
            ->filter('input[name="order_placement_form[_token]"]')
            ->attr('value');

        $this->client->request(
            'POST',
            '/profile/orders/new/menu-de-commande',
            [
                'order_placement_form' => [
                    'deliveryAddress' => '10 rue de la Paix, 33000 Bordeaux',
                    'deliveryAt' => (new \DateTimeImmutable('-1 day'))
                        ->format('Y-m-d\TH:i'),
                    'numberOfPeople' => 4,
                    '_token' => $csrfToken,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains(
            '.invalid-feedback',
            'La livraison doit être postérieure à la commande.',
        );
        self::assertCount(0, $this->orderRepository->findAll());
    }

    // Verifies that administrators and employees cannot place customer orders.
    public function testStaffMembersCannotPlaceCustomerOrders(): void
    {
        $employee = $this->createUser(
            'employee-order@example.com',
            ['ROLE_EMPLOYEE'],
        );
        $administrator = $this->createUser(
            'admin-order@example.com',
            ['ROLE_ADMIN'],
        );
        $this->entityManager->flush();

        foreach ([$employee, $administrator] as $staffMember) {
            $this->client->loginUser($staffMember);
            $this->client->request(
                'POST',
                '/profile/orders/new/menu-de-commande',
            );

            self::assertResponseStatusCodeSame(403);
        }

        self::assertCount(0, $this->orderRepository->findAll());
    }

    // Verifies that a customer can place another order for the same menu.
    public function testCustomerCanPlaceSecondOrderForSameMenu(): void
    {
        $this->client->loginUser($this->customer);

        $crawler = $this->client->request(
            'GET',
            '/menus/menu-de-commande',
        );
        $firstToken = $crawler
            ->filter('input[name="order_placement_form[_token]"]')
            ->attr('value');

        $this->client->request(
            'POST',
            '/profile/orders/new/menu-de-commande',
            [
                'order_placement_form' => [
                    'deliveryAddress' => '10 rue de la Paix, 33000 Bordeaux',
                    'deliveryAt' => (new \DateTimeImmutable('+3 days'))
                        ->format('Y-m-d\TH:i'),
                    'numberOfPeople' => 4,
                    '_token' => $firstToken,
                ],
            ],
        );

        self::assertResponseRedirects('/menus/menu-de-commande');

        $crawler = $this->client->followRedirect();
        $secondToken = $crawler
            ->filter('input[name="order_placement_form[_token]"]')
            ->attr('value');

        $this->client->request(
            'POST',
            '/profile/orders/new/menu-de-commande',
            [
                'order_placement_form' => [
                    'deliveryAddress' => '20 rue du Port, 33000 Bordeaux',
                    'deliveryAt' => (new \DateTimeImmutable('+5 days'))
                        ->format('Y-m-d\TH:i'),
                    'numberOfPeople' => 5,
                    '_token' => $secondToken,
                ],
            ],
        );

        self::assertResponseRedirects('/menus/menu-de-commande');
        self::assertCount(2, $this->orderRepository->findAll());

        $menu = $this->menuRepository->findOneBy([
            'slug' => 'menu-de-commande',
        ]);

        self::assertInstanceOf(Menu::class, $menu);
        self::assertSame(8, $menu->getStock());
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
}
