<?php
namespace App\Tests;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Ensure we have a clean database
        $container = static::getContainer();

        /** @var EntityManager $em */
        $em = $container->get('doctrine')->getManager();
        $this->userRepository = $container->get(UserRepository::class);

        foreach ($this->userRepository->findAll() as $user) {
            $em->remove($user);
        }

        $em->flush();
    }

    public function testRegister(): void
    {
        $this->client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Créer un compte');

        $this->client->submitForm('Créer mon compte', [
            'registration_form[firstName]' => 'Jean',
            'registration_form[lastName]' => 'Dupont',
            'registration_form[phone]' => '0612345678',
            'registration_form[address]' => '10 rue de Bordeaux',
            'registration_form[email]' => 'juan@example.com',
            'registration_form[plainPassword][first]' => 'Password1!',
            'registration_form[plainPassword][second]' => 'Password1!',
        ]);

        self::assertResponseRedirects('/login');
        self::assertEmailCount(1);

        $email = self::getMailerMessage();

        self::assertEmailAddressContains($email, 'To', 'juan@example.com');
        self::assertEmailSubjectContains($email, 'Bienvenue chez Vite & Gourmand');
        self::assertEmailHtmlBodyContains($email, 'Votre compte a bien été créé.');
        self::assertEmailHtmlBodyNotContains($email, 'Password1!');

        self::assertCount(1, $this->userRepository->findAll());
    }

    public function testRegistrationRejectsWhitespaceAsSpecialCharacter(): void
    {
        $this->client->request('GET', '/register');

        $this->client->submitForm('Créer mon compte', [
            'registration_form[firstName]' => 'Juan',
            'registration_form[lastName]' => 'Rod',
            'registration_form[phone]' => '0612345678',
            'registration_form[address]' => '10 rue de Bordeaux',
            'registration_form[email]' => 'whitespace@example.com',
            'registration_form[plainPassword][first]' => 'Password1 ',
            'registration_form[plainPassword][second]' => 'Password1 ',
        ]);

        self::assertSelectorTextContains(
             '.invalid-feedback',
            'Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et un caractère spécial.'
        );

        // check the DB contains no users at that point in the test.
        self::assertCount(0, $this->userRepository->findAll());
    }
}
