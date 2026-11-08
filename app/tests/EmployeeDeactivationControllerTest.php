<?php

namespace App\Tests;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EmployeeDeactivationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private User $administrator;
    private User $employee;

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
            ->setIsActive(true);
        $this->administrator->setPassword(
            $this->passwordHasher->hashPassword(
                $this->administrator,
                'Admin1!Secure'
            )
        );

        $this->employee = (new User())
            ->setFirstName('Marie')
            ->setLastName('Martin')
            ->setEmail('employee@example.com')
            ->setPhone('0612345678')
            ->setEmployeeNumber('EMP-001')
            ->setRoles(['ROLE_EMPLOYEE'])
            ->setIsActive(true);
        $this->employee->setPassword(
            $this->passwordHasher->hashPassword(
                $this->employee,
                'Employee1!Secure'
            )
        );

        $this->entityManager->persist($this->administrator);
        $this->entityManager->persist($this->employee);
        $this->entityManager->flush();
    }

    public function testAdministratorCanViewEmployeeList(): void
    {
        $this->client->loginUser($this->administrator, 'admin');
        $this->client->request('GET', '/admin/employees');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '.employee-list-details',
            'Marie'
        );
        self::assertSelectorTextContains(
            '.employee-list-details',
            'Martin'
        );
        self::assertSelectorTextContains(
            '.employee-list-details',
            'employee@example.com'
        );
        self::assertSelectorTextContains(
            '.employee-list-details',
            '0612345678'
        );
        self::assertSelectorTextContains(
            '.employee-list-details',
            'EMP-001'
        );
        self::assertSelectorTextContains(
            '.employee-status--active',
            'Actif'
        );
    }

    public function testAdministratorCanDeactivateEmployee(): void
    {
        $employeeId = $this->employee->getId();

        $this->client->loginUser($this->administrator, 'admin');
        $this->client->request('GET', '/admin/employees');
        $this->client->submitForm('Désactiver');

        self::assertResponseRedirects('/admin/employees');

        $this->entityManager->clear();

        $employee = $this->userRepository->find($employeeId);

        self::assertInstanceOf(User::class, $employee);
        self::assertFalse($employee->isActive());

        $this->client->followRedirect();

        self::assertSelectorTextContains(
            '[role="status"]',
            'a été désactivé'
        );
        self::assertSelectorTextContains(
            '.employee-status--inactive',
            'Inactif'
        );
    }

    public function testInactiveEmployeeCannotLogIn(): void
    {
        $this->employee->setIsActive(false);
        $this->entityManager->flush();

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            '_username' => 'employee@example.com',
            '_password' => 'Employee1!Secure',
        ]);

        self::assertResponseRedirects('/login');

        $this->client->followRedirect();

        self::assertSelectorTextContains(
            '.alert-danger',
            'Ce compte est désactivé.'
        );
    }

    public function testNonAdministratorCannotDeactivateEmployee(): void
    {
        $customer = (new User())
            ->setEmail('customer@example.com')
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true)
            ->setPassword('hashed-password');

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $this->client->loginUser($customer, 'admin');
        $this->client->request(
            'POST',
            '/admin/employees/'.$this->employee->getId().'/deactivate',
            ['_token' => 'invalid']
        );

        self::assertResponseStatusCodeSame(403);
        self::assertTrue($this->employee->isActive());
    }

    public function testAdministratorCanEditEmployeeInformation(): void
    {
        $employeeId = $this->employee->getId();
        $originalPassword = $this->employee->getPassword();
        $formName = 'employee_edit_'.$employeeId;

        $this->client->loginUser($this->administrator, 'admin');
        $this->client->request('GET', '/admin/employees');
        $this->client->submitForm('Enregistrer', [
            $formName.'[firstName]' => 'Jeanne',
            $formName.'[lastName]' => 'Durand',
            $formName.'[email]' => 'jeanne.durand@example.com',
            $formName.'[phone]' => '0698765432',
            $formName.'[employeeNumber]' => 'EMP-002',
        ]);

        self::assertResponseRedirects('/admin/employees');

        $this->entityManager->clear();

        $employee = $this->userRepository->find($employeeId);

        self::assertInstanceOf(User::class, $employee);
        self::assertSame('Jeanne', $employee->getFirstName());
        self::assertSame('Durand', $employee->getLastName());
        self::assertSame(
            'jeanne.durand@example.com',
            $employee->getEmail()
        );
        self::assertSame('0698765432', $employee->getPhone());
        self::assertSame('EMP-002', $employee->getEmployeeNumber());
        self::assertSame($originalPassword, $employee->getPassword());
        self::assertContains('ROLE_EMPLOYEE', $employee->getRoles());
        self::assertTrue($employee->isActive());
    }
}
