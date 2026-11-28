<?php

namespace App\Tests;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateAdminCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->passwordHasher = $container->get(
            UserPasswordHasherInterface::class
        );

        $existingAdministrator = $this->userRepository->findOneBy([
            'email' => 'jose.admin@vite-et-gourmand.fr',
        ]);

        if ($existingAdministrator instanceof User) {
            $this->entityManager->remove($existingAdministrator);
            $this->entityManager->flush();
        }
    }

    public function testCommandCreatesSecureAdministratorAccount(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:create-admin');
        $commandTester = new CommandTester($command);

        $plainPassword = 'SecureAdmin1!';

        $commandTester->setInputs([
            'jose.admin@vite-et-gourmand.fr',
            $plainPassword,
            $plainPassword,
        ]);

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $this->entityManager->clear();

        $administrator = $this->userRepository->findOneBy([
            'email' => 'jose.admin@vite-et-gourmand.fr',
        ]);

        self::assertInstanceOf(User::class, $administrator);
        self::assertContains('ROLE_ADMIN', $administrator->getRoles());
        self::assertTrue($administrator->isActive());
        self::assertNotSame(
            $plainPassword,
            $administrator->getPassword()
        );
        self::assertTrue(
            $this->passwordHasher->isPasswordValid(
                $administrator,
                $plainPassword
            )
        );
    }
}
