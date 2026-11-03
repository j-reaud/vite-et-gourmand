<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Creates an active administrator account.',
)]
final class CreateAdminCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $email = $io->ask(
            'Administrator email',
            null,
            function (?string $email): string {
                $email = mb_strtolower(trim((string) $email));

                $this->validate($email, [
                    new NotBlank(message: 'Please enter an email address.'),
                    new Email(message: 'Please enter a valid email address.'),
                ]);

                if ($this->userRepository->findOneBy(['email' => $email])) {
                    throw new RuntimeException(
                        'An account already uses this email address.'
                    );
                }

                return $email;
            }
        );

        $plainPassword = $io->askHidden(
            'Administrator password',
            function (?string $password): string {
                $password = (string) $password;

                $this->validate($password, [
                    new NotBlank(message: 'Please enter a password.'),
                    new Length(
                        min: 10,
                        minMessage: 'The password must contain at least {{ limit }} characters.',
                        max: 4096,
                    ),
                    new Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d\s])\S+$/',
                        message: 'The password must contain an uppercase letter, a lowercase letter, a number and a special character.',
                    ),
                ]);

                return $password;
            }
        );

        $io->askHidden(
            'Confirm administrator password',
            function (?string $confirmation) use ($plainPassword): string {
                $confirmation = (string) $confirmation;

                if (!hash_equals($plainPassword, $confirmation)) {
                    throw new RuntimeException(
                        'The passwords must match.'
                    );
                }

                return $confirmation;
            }
        );

        $administrator = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_ADMIN'])
            ->setIsActive(true);

        $administrator->setPassword(
            $this->passwordHasher->hashPassword(
                $administrator,
                $plainPassword
            )
        );

        $this->entityManager->persist($administrator);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Administrator account %s was created successfully.',
            $email
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array<object> $constraints
     */
    private function validate(string $value, array $constraints): void
    {
        $violations = $this->validator->validate($value, $constraints);

        if ($violations->count() > 0) {
            throw new RuntimeException(
                (string) $violations->get(0)->getMessage()
            );
        }
    }
}