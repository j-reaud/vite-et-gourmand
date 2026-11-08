<?php

namespace App\Tests;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EmployeeAccountControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private User $administrator;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(
            EntityManagerInterface::class
        );
        $this->userRepository = $container->get(UserRepository::class);
        $this->passwordHasher = $container->get(
            UserPasswordHasherInterface::class
        );

        foreach ($this->userRepository->findAll() as $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();

        $this->administrator = (new User())
            ->setEmail('admin@example.com')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsActive(true)
            ->setPassword('hashed-password');

        $this->entityManager->persist($this->administrator);
        $this->entityManager->flush();
    }

    public function testAdministratorCanCreateEmployeeAccount(): void
    {
        $plainPassword = 'Employee1!Secure';

        $this->client->loginUser($this->administrator, 'admin');
        $this->client->request('GET', '/admin/employees/new');

        self::assertResponseIsSuccessful();

        $this->client->submitForm('Créer le compte employé', [
            'employee_account_form[firstName]' => 'Marie',
            'employee_account_form[lastName]' => 'Martin',
            'employee_account_form[email]' =>
                'employee@example.com',
            'employee_account_form[phone]' => '0612345678',
            'employee_account_form[employeeNumber]' => 'EMP-001',
            'employee_account_form[plainPassword][first]' =>
                $plainPassword,
            'employee_account_form[plainPassword][second]' =>
                $plainPassword,
        ]);

        self::assertResponseRedirects('/admin/employees/new');
        self::assertEmailCount(1);

        $email = self::getMailerMessage();

        self::assertEmailAddressContains(
            $email,
            'To',
            'employee@example.com'
        );
        self::assertEmailSubjectContains(
            $email,
            'Votre compte employé Vite & Gourmand'
        );
        self::assertEmailHtmlBodyContains(
            $email,
            'contacter votre'
        );
        self::assertEmailHtmlBodyNotContains(
            $email,
            $plainPassword
        );

        $this->entityManager->clear();

        $employee = $this->userRepository->findOneBy([
            'email' => 'employee@example.com',
        ]);

        self::assertInstanceOf(User::class, $employee);
        self::assertSame('Marie', $employee->getFirstName());
        self::assertSame('Martin', $employee->getLastName());
        self::assertSame('0612345678', $employee->getPhone());
        self::assertSame('EMP-001', $employee->getEmployeeNumber());
        self::assertContains('ROLE_EMPLOYEE', $employee->getRoles());
        self::assertNotContains('ROLE_ADMIN', $employee->getRoles());
        self::assertTrue($employee->isActive());
        self::assertNotSame(
            $plainPassword,
            $employee->getPassword()
        );
        self::assertTrue(
            $this->passwordHasher->isPasswordValid(
                $employee,
                $plainPassword
            )
        );

        $this->client->followRedirect();

        self::assertSelectorTextContains(
            '[role="status"]',
            'Le compte employé a été créé avec succès.'
        );
    }

    public function testCustomerCannotAccessEmployeeCreationForm(): void
    {
        $customer = (new User())
            ->setEmail('customer@example.com')
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true)
            ->setPassword('hashed-password');

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $this->client->loginUser($customer, 'admin');
        $this->client->request('GET', '/admin/employees/new');

        self::assertResponseStatusCodeSame(403);
    }

    public function testEmployeeCannotAccessEmployeeCreationForm(): void
    {
        $employee = (new User())
            ->setEmail('employee@example.com')
            ->setRoles(['ROLE_EMPLOYEE'])
            ->setIsActive(true)
            ->setPassword('hashed-password');

        $this->entityManager->persist($employee);
        $this->entityManager->flush();

        $this->client->loginUser($employee, 'admin');
        $this->client->request('GET', '/admin/employees/new');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $existingEmployee = (new User())
            ->setEmail('employee@example.com')
            ->setRoles(['ROLE_EMPLOYEE'])
            ->setIsActive(true)
            ->setPassword('hashed-password');

        $this->entityManager->persist($existingEmployee);
        $this->entityManager->flush();

        $this->client->loginUser($this->administrator, 'admin');
        $this->client->request('GET', '/admin/employees/new');

        $this->client->submitForm('Créer le compte employé', [
            'employee_account_form[firstName]' => 'Marie',
            'employee_account_form[lastName]' => 'Martin',
            'employee_account_form[email]' =>
                'employee@example.com',
            'employee_account_form[phone]' => '0612345678',
            'employee_account_form[employeeNumber]' => 'EMP-002',
            'employee_account_form[plainPassword][first]' =>
                'Employee1!Secure',
            'employee_account_form[plainPassword][second]' =>
                'Employee1!Secure',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
        self::assertSelectorTextContains(
            '.invalid-feedback',
            'Un compte utilise déjà cette adresse e-mail.'
        );
        self::assertCount(2, $this->userRepository->findAll());
    }
}
