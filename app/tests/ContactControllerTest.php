<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContactControllerTest extends WebTestCase
{
    // Verifies that every visitor can access the contact form.
    public function testContactPageIsPubliclyAccessible(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Nous contacter');
        self::assertCount(1, $crawler->filter('form[name="contact_form"]'));
        self::assertCount(1, $crawler->filter('input[name="contact_form[_token]"]'));
    }

    // Verifies that a valid request sends a complete email to the company.
    public function testVisitorCanSendContactRequest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');

        $client->submitForm('Envoyer', [
            'contact_form[firstName]' => 'Camille',
            'contact_form[lastName]' => 'Martin',
            'contact_form[email]' => 'camille@example.com',
            'contact_form[phone]' => '06 12 34 56 78',
            'contact_form[message]' => 'Je souhaite organiser un repas pour un anniversaire.',
        ]);

        self::assertResponseRedirects('/contact');
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'bonjour@viteetgourmand.fr');
        self::assertEmailAddressContains($email, 'Reply-To', 'camille@example.com');
        self::assertEmailSubjectContains($email, 'Demande de contact de Camille Martin');
        self::assertEmailHtmlBodyContains($email, '06 12 34 56 78');
        self::assertEmailHtmlBodyContains(
            $email,
            'Je souhaite organiser un repas pour un anniversaire.',
        );

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#contactSuccessModal', 'Message envoyé');
        self::assertSelectorExists('#contactSuccessModal a[href="/"]');
    }

    // Verifies that messages longer than 240 characters are rejected server-side.
    public function testMessageCannotExceed240Characters(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');

        $client->submitForm('Envoyer', [
            'contact_form[firstName]' => 'Camille',
            'contact_form[lastName]' => 'Martin',
            'contact_form[email]' => 'camille@example.com',
            'contact_form[phone]' => '06 12 34 56 78',
            'contact_form[message]' => str_repeat('a', 241),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains(
            '.invalid-feedback',
            'Le message ne peut pas dépasser 240 caractères.',
        );
        self::assertEmailCount(0);
    }

    // Verifies that the main public calls to action lead to the contact page.
    public function testPublicContactLinksUseContactPage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(3, $crawler->filter('a[href="/contact"]')->count());
    }
}
