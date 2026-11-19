<?php

namespace App\Command;

use App\Document\AccountRegistration;
use App\Repository\UserRepository;
use App\Service\AccountRegistrationRecorder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill-account-analytics',
    description: 'Imports existing customer and employee accounts into MongoDB.',
)]
final class BackfillAccountAnalyticsCommand
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AccountRegistrationRecorder $registrationRecorder,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $synchronized = 0;
        $failed = 0;

        foreach ($this->userRepository->findAll() as $user) {
            $roles = $user->getRoles();

            if (in_array('ROLE_ADMIN', $roles, true)) {
                continue;
            }

            $accountType = in_array('ROLE_EMPLOYEE', $roles, true)
                ? AccountRegistration::TYPE_EMPLOYEE
                : AccountRegistration::TYPE_CUSTOMER;

            if ($this->registrationRecorder->record($user, $accountType)) {
                ++$synchronized;
            } else {
                ++$failed;
            }
        }

        if ($failed > 0) {
            $io->warning(sprintf(
                '%d account(s) could not be synchronized. Check the logs.',
                $failed,
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            '%d customer and employee account(s) synchronized with MongoDB.',
            $synchronized,
        ));

        return Command::SUCCESS;
    }
}
