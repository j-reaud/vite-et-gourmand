<?php

namespace App\Tests;

use App\Document\AccountRegistration;
use App\Entity\User;
use App\Message\RecordAccountRegistration;
use App\Service\AccountKpiService;
use App\Service\AccountRegistrationDispatcher;
use App\Service\AccountRegistrationRecorder;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class AccountKpiServiceTest extends KernelTestCase
{
    private DocumentManager $documentManager;
    private EntityManagerInterface $entityManager;
    private AccountRegistrationRecorder $registrationRecorder;
    private AccountRegistrationDispatcher $registrationDispatcher;
    private AccountKpiService $accountKpiService;
    private InMemoryTransport $asyncTransport;
    /** @var list<int> */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->documentManager = $container->get(DocumentManager::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->registrationRecorder = $container->get(
            AccountRegistrationRecorder::class,
        );
        $this->registrationDispatcher = $container->get(
            AccountRegistrationDispatcher::class,
        );
        $this->accountKpiService = $container->get(AccountKpiService::class);
        $this->asyncTransport = $container->get('messenger.transport.async');
        $this->asyncTransport->reset();

        $this->clearAnalyticsCollection();
    }

    protected function tearDown(): void
    {
        $this->clearAnalyticsCollection();

        $this->entityManager->clear();

        foreach ($this->createdUserIds as $userId) {
            $user = $this->entityManager->find(User::class, $userId);

            if ($user instanceof User) {
                $this->entityManager->remove($user);
            }
        }

        $this->entityManager->flush();

        $this->documentManager->clear();
        $this->asyncTransport->reset();
        parent::tearDown();
    }

    // Verifies that HTTP account creation queues analytics for the worker.
    public function testDispatcherQueuesAnalyticsWithoutWritingMongo(): void
    {
        $user = (new User())
            ->setEmail('queued-kpi-customer@example.com')
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true)
            ->setPassword('hashed-password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $userId = $user->getId();
        self::assertNotNull($userId);
        $this->createdUserIds[] = $userId;

        $this->registrationDispatcher->dispatch(
            $user,
            AccountRegistration::TYPE_CUSTOMER,
        );

        $sent = $this->asyncTransport->getSent();
        self::assertCount(1, $sent);
        $message = $sent[0]->getMessage();
        self::assertInstanceOf(RecordAccountRegistration::class, $message);
        self::assertSame($userId, $message->userId);
        self::assertSame(
            AccountRegistration::TYPE_CUSTOMER,
            $message->accountType,
        );
        self::assertSame(
            0,
            $this->documentManager
                ->getDocumentCollection(AccountRegistration::class)
                ->countDocuments(),
        );
    }

    // Verifies that recording the same MySQL user twice creates one document.
    public function testRecorderCreatesOnlyOneRegistrationPerUser(): void
    {
        $user = (new User())
            ->setEmail('kpi-customer@example.com')
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true)
            ->setPassword('hashed-password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $userId = $user->getId();
        self::assertNotNull($userId);
        $this->createdUserIds[] = $userId;

        $this->registrationRecorder->record(
            $userId,
            AccountRegistration::TYPE_CUSTOMER,
            new \DateTimeImmutable(),
        );
        $this->registrationRecorder->record(
            $userId,
            AccountRegistration::TYPE_CUSTOMER,
            new \DateTimeImmutable(),
        );

        $registrations = $this->documentManager
            ->getRepository(AccountRegistration::class)
            ->findBy(['userId' => $userId]);

        self::assertCount(1, $registrations);
        self::assertSame(
            AccountRegistration::TYPE_CUSTOMER,
            $registrations[0]->getAccountType(),
        );
    }

    // Verifies totals and monthly series for both account types.
    public function testDashboardDataContainsTotalsAndTwelveMonths(): void
    {
        $now = new \DateTimeImmutable();
        $employee = (new User())
            ->setEmail('active-kpi-employee@example.com')
            ->setRoles(['ROLE_EMPLOYEE'])
            ->setIsActive(true)
            ->setPassword('hashed-password');
        $this->entityManager->persist($employee);
        $inactiveEmployee = (new User())
            ->setEmail('inactive-kpi-employee@example.com')
            ->setRoles(['ROLE_EMPLOYEE'])
            ->setIsActive(false)
            ->setPassword('hashed-password');
        $this->entityManager->persist($inactiveEmployee);
        $this->entityManager->flush();
        $employeeId = $employee->getId();
        $inactiveEmployeeId = $inactiveEmployee->getId();
        self::assertNotNull($employeeId);
        self::assertNotNull($inactiveEmployeeId);
        $this->createdUserIds[] = $employeeId;
        $this->createdUserIds[] = $inactiveEmployeeId;

        $registrations = [
            new AccountRegistration(
                900001,
                AccountRegistration::TYPE_CUSTOMER,
                $now,
            ),
            new AccountRegistration(
                900002,
                AccountRegistration::TYPE_CUSTOMER,
                $now->modify('-2 months'),
            ),
            new AccountRegistration(
                $employeeId,
                AccountRegistration::TYPE_EMPLOYEE,
                $now,
            ),
            new AccountRegistration(
                $inactiveEmployeeId,
                AccountRegistration::TYPE_EMPLOYEE,
                $now->modify('-1 month'),
            ),
            new AccountRegistration(
                900004,
                AccountRegistration::TYPE_CUSTOMER,
                null,
            ),
        ];

        foreach ($registrations as $registration) {
            $this->documentManager->persist($registration);
        }

        $this->documentManager->flush();

        $data = $this->accountKpiService->getDashboardData();

        self::assertSame(3, $data['customers']['total']);
        self::assertSame(1, $data['employees']['total']);
        self::assertCount(12, $data['customers']['labels']);
        self::assertCount(12, $data['customers']['values']);
        self::assertSame(2, array_sum($data['customers']['values']));
        self::assertSame(2, array_sum($data['employees']['values']));
        self::assertContains(0, $data['customers']['values']);
        self::assertSame(
            count($data['customers']['shortLabels']),
            count(array_unique($data['customers']['shortLabels'])),
        );
    }

    private function clearAnalyticsCollection(): void
    {
        $this->documentManager
            ->getDocumentCollection(AccountRegistration::class)
            ->deleteMany([]);
        $this->documentManager->clear();
    }
}
