<?php

namespace App\Tests;

use App\Document\AccountRegistration;
use App\Entity\User;
use App\Service\AccountKpiService;
use App\Service\AccountRegistrationRecorder;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AccountKpiServiceTest extends KernelTestCase
{
    private DocumentManager $documentManager;
    private EntityManagerInterface $entityManager;
    private AccountRegistrationRecorder $registrationRecorder;
    private AccountKpiService $accountKpiService;
    private ?int $createdUserId = null;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->documentManager = $container->get(DocumentManager::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->registrationRecorder = $container->get(
            AccountRegistrationRecorder::class,
        );
        $this->accountKpiService = $container->get(AccountKpiService::class);

        $this->clearAnalyticsCollection();
    }

    protected function tearDown(): void
    {
        $this->clearAnalyticsCollection();

        if ($this->createdUserId !== null) {
            $this->entityManager->clear();
            $user = $this->entityManager->find(User::class, $this->createdUserId);

            if ($user instanceof User) {
                $this->entityManager->remove($user);
                $this->entityManager->flush();
            }
        }

        $this->documentManager->clear();
        parent::tearDown();
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
        $this->createdUserId = $user->getId();

        self::assertTrue($this->registrationRecorder->record(
            $user,
            AccountRegistration::TYPE_CUSTOMER,
        ));
        self::assertTrue($this->registrationRecorder->record(
            $user,
            AccountRegistration::TYPE_CUSTOMER,
        ));

        $registrations = $this->documentManager
            ->getRepository(AccountRegistration::class)
            ->findBy(['userId' => $this->createdUserId]);

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
                900003,
                AccountRegistration::TYPE_EMPLOYEE,
                $now,
            ),
        ];

        foreach ($registrations as $registration) {
            $this->documentManager->persist($registration);
        }

        $this->documentManager->flush();

        $data = $this->accountKpiService->getDashboardData();

        self::assertSame(2, $data['customers']['total']);
        self::assertSame(1, $data['employees']['total']);
        self::assertCount(12, $data['customers']['labels']);
        self::assertCount(12, $data['customers']['values']);
        self::assertSame(2, array_sum($data['customers']['values']));
        self::assertSame(1, array_sum($data['employees']['values']));
        self::assertContains(0, $data['customers']['values']);
    }

    private function clearAnalyticsCollection(): void
    {
        $this->documentManager
            ->getDocumentCollection(AccountRegistration::class)
            ->deleteMany([]);
        $this->documentManager->clear();
    }
}
