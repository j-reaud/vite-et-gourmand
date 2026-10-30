<?php

namespace App\Tests;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfileControllerTest extends WebTestCase
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

    public function testCustomerCanUpdatePersonalInformation(): void
    {
        $user = (new User())
            ->setFirstName('Juan')
            ->setLastName('Hereaud')
            ->setEmail('juanhereaud@example.com')
            ->setPhone('0123456789')
            ->setAddress('10 place de la ville')
            ->setPassword('test-password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();

        $this->client->submitForm('Enregistrer les modifications', [
            'profile_form[firstName]' => 'Jeanne',
            'profile_form[lastName]' => 'Martin',
            'profile_form[email]' => 'jeanne@example.com',
            'profile_form[phone]' => '0698765432',
            'profile_form[address]' => '20 rue de Paris',
        ]);

        self::assertResponseRedirects('/profile');

        $this->client->followRedirect();

        self::assertSelectorTextContains(
            '[role="status"]',
            'Vos informations personnelles ont été mises à jour.'
        );

        $this->entityManager->clear();

        $updatedUser = $this->userRepository->findOneBy([
            'email' => 'jeanne@example.com',
        ]);

        self::assertInstanceOf(User::class, $updatedUser);
        self::assertSame('Jeanne', $updatedUser->getFirstName());
        self::assertSame('Martin', $updatedUser->getLastName());
        self::assertSame('0698765432', $updatedUser->getPhone());
        self::assertSame('20 rue de Paris', $updatedUser->getAddress());
    }
}
