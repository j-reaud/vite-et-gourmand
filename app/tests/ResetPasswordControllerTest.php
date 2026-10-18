<?php
namespace App\Tests;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetPasswordControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Ensure we have a clean database
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        $this->userRepository = $container->get(UserRepository::class);

        foreach ($this->userRepository->findAll() as $user) {
            $this->em->remove($user);
        }

        $this->em->flush();
    }

    public function testResetPasswordController(): void
    {
        // Create a test user
        $user = (new User())
            ->setEmail('me@example.com')
            ->setPassword('a-test-password-that-will-be-changed-later')
            ->setFirstName('Juan')
            ->setLastName('Rod')
            ->setPhone('0648653474')
            ->setAddress('1 rue de Paris')
        ;
        $this->em->persist($user);
        $this->em->flush();

        // Test Request reset password page
        $this->client->request('GET', '/reset-password');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Mot de passe oublié');

        // Submit the reset password form and test email message is queued / sent
        $this->client->submitForm('Envoyer le lien', [
            'reset_password_request_form[email]' => 'me@example.com',
        ]);

        // Ensure the reset password email was sent
        // Use either assertQueuedEmailCount() || assertEmailCount() depending on your mailer setup
        // self::assertQueuedEmailCount(1);
        self::assertEmailCount(1);

        self::assertCount(1, $messages = $this->getMailerMessages());

        self::assertEmailAddressContains($messages[0], 'from', 'no-reply@vite-et-gourmand.fr');
        self::assertEmailAddressContains($messages[0], 'to', 'me@example.com');
        self::assertEmailHtmlBodyContains($messages[0], 'Réinitialisez votre mot de passe');

        self::assertResponseRedirects('/reset-password/check-email');

        // Test check email landing page shows correct "expires at" time
        $crawler = $this->client->followRedirect();

        self::assertPageTitleContains('E-mail envoyé');
        self::assertStringContainsString('Ce lien expirera dans', $crawler->html());

        // Test the link sent in the email is valid
        /** @var array<TemplatedEmail> $messages */
        $email = $messages[0]->getHtmlBody();
        self::assertIsString($email);
        preg_match('#(/reset-password/reset/[a-zA-Z0-9]+)#', $email, $resetLink);

        $this->client->request('GET', $resetLink[1]);

        self::assertResponseRedirects('/reset-password/reset');

        $this->client->followRedirect();

        // A whitespace character must not count as a special character.
        $this->client->submitForm('Enregistrer le mot de passe', [
            'change_password_form[plainPassword][first]' => 'MotDePasse1 ',
            'change_password_form[plainPassword][second]' => 'MotDePasse1 ',
        ]);

        self::assertSelectorTextContains(
            '.invalid-feedback',
            'Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et un caractère spécial.'
        );

        // Test we can set a new password
        $this->client->submitForm('Enregistrer le mot de passe', [
            'change_password_form[plainPassword][first]' => 'NouveauMotDePasse1!',
            'change_password_form[plainPassword][second]' => 'NouveauMotDePasse1!',
        ]);

        self::assertResponseRedirects('/login');

        $user = $this->userRepository->findOneBy(['email' => 'me@example.com']);

        self::assertInstanceOf(User::class, $user);

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($passwordHasher->isPasswordValid($user, 'NouveauMotDePasse1!'));
    }
}
