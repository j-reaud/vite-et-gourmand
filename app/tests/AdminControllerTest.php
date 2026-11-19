<?php

namespace App\Tests;

use App\Document\AccountRegistration;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private DocumentManager $documentManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->documentManager = $container->get(DocumentManager::class);

        $this->documentManager
            ->getDocumentCollection(AccountRegistration::class)
            ->deleteMany([]);
        $this->documentManager->clear();

        foreach ($this->userRepository->findAll() as $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $this->documentManager
            ->getDocumentCollection(AccountRegistration::class)
            ->deleteMany([]);
        $this->documentManager->clear();

        parent::tearDown();
    }

    public function testAnonymousUserIsRedirectedToAdminLogin(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/admin/login');
    }

    public function testAdministratorCanAccessAdminArea(): void
    {
        $administrator = (new User())
            ->setEmail('jose.admin@vite-et-gourmand.fr')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsActive(true)
            ->setPassword('hashed-password');

        $this->entityManager->persist($administrator);
        $this->entityManager->flush();

        $this->documentManager->persist(new AccountRegistration(
            800001,
            AccountRegistration::TYPE_CUSTOMER,
            new \DateTimeImmutable(),
        ));
        $this->documentManager->persist(new AccountRegistration(
            800002,
            AccountRegistration::TYPE_EMPLOYEE,
            new \DateTimeImmutable(),
        ));
        $this->documentManager->flush();

        $this->client->loginUser($administrator, 'admin');
        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bonjour, Administrateur');
        self::assertSelectorTextContains(
            '.admin-dashboard-user',
            'jose.admin@vite-et-gourmand.fr'
        );
        self::assertSelectorTextContains(
            '.admin-dashboard-kpis',
            'Chiffre d’affaires',
        );
        self::assertSelectorCount(5, '.admin-dashboard-kpi');
        self::assertSelectorTextSame(
            '[data-bs-target="#customerKpiModal"] strong',
            '1',
        );
        self::assertSelectorTextSame(
            '[data-bs-target="#employeeKpiModal"] strong',
            '1',
        );
    }

    public function testCustomerCannotAccessAdminArea(): void
    {
        $customer = (new User())
            ->setEmail('customer@example.com')
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true)
            ->setPassword('hashed-password');

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $this->client->loginUser($customer, 'admin');
        $this->client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testNoPublicAdministratorRegistrationPageExists(): void
    {
        $this->client->request('GET', '/admin/register');

        self::assertResponseStatusCodeSame(404);
    }
}
