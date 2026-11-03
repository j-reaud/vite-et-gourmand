<?php

namespace App\Tests;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->userRepository = $container->get(UserRepository::class);

        foreach ($this->userRepository->findAll() as $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();
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

        $this->client->loginUser($administrator, 'admin');
        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Espace administrateur');
        self::assertSelectorTextContains(
            '.admin-card',
            'jose.admin@vite-et-gourmand.fr'
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
