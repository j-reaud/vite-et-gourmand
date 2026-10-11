<?php
namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoginControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository(User::class);

        // Remove any existing users from the test database
        foreach ($userRepository->findAll() as $user) {
            $em->remove($user);
        }

        $em->flush();

        // Create a User fixture
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get('security.user_password_hasher');

        $user = (new User())
            ->setEmail('email@example.com')
            ->setFirstName('Test')
            ->setLastName('User')
            ->setPhone('1234567890')
            ->setAddress('123 Test St'); 
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));

        $em->persist($user);
        $em->flush();
    }

    public function testLogin(): void
    {
        // Denied - Can't login with invalid email address.
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Se connecter', [
            '_username' => 'doesNotExist@example.com',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Ensure we do not reveal if the user exists or not.
        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');

        // Denied - Can't login with invalid password.
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Se connecter', [
            '_username' => 'email@example.com',
            '_password' => 'bad-password',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Ensure we do not reveal the user exists but the password is wrong.
        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');

        // Success - Login with valid credentials is allowed.
        $this->client->submitForm('Se connecter', [
            '_username' => 'email@example.com',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();

        self::assertSelectorNotExists('.alert-danger');
    }



    public function testLogout(): void
    {
        // Find the test user in the database using their eamil address. 
        $user = static::getContainer() 
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'email@example.com']); 

        // Check that the test user was found in the database. 
        self::assertNotNull($user); 

        // Log in the test user 
        $this->client->loginUser($user); 
        // open the profile page. 
        $this->client->request('GET', '/profile');
        // check that it loads successfully. 
        self::assertResponseIsSuccessful();

        // send a GET request to the /logout. 
        $this->client->request('GET', '/logout');
        // check the repsponse to the /logout request. 
        self::assertResponseRedirects('/login');

        $this->client->followRedirect();
        // check that the page loaded after the redirect and returns a 200 status code. 
        self::assertResponseIsSuccessful();
        // check that the page contains the text "Se connecter" in an h1 tag.
        self::assertSelectorTextContains('h1', 'Se connecter');


    }
}
