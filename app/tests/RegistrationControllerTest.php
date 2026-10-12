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
            'registration_form[email]' => 'jean@example.com',
            'registration_form[plainPassword][first]' => 'Password1!',
            'registration_form[plainPassword][second]' => 'Password1!',
        ]);

        self::assertResponseRedirects('/login');
        self::assertCount(1, $this->userRepository->findAll());
    }
}
