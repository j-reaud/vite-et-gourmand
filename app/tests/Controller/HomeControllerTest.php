<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomepageDisplaysRequiredContent(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.site-header');
        self::assertSelectorExists('nav[aria-label="Navigation principale"]');
        self::assertSelectorTextContains('h1', 'Des moments gourmands');
        self::assertSelectorExists('.home-hero__visual img');
        self::assertSelectorExists('.home-hero a[href="#menus"]');
        self::assertSelectorExists('.home-hero a[href="#contact"]');
        self::assertSelectorExists('#menus');
        self::assertSelectorExists('#histoire');
        self::assertSelectorExists('#contact');
        self::assertCount(3, $crawler->filter('.menu-preview-card'));
        self::assertCount(4, $crawler->filter('.occasion-card'));
        self::assertCount(4, $crawler->filter('.process-grid > li'));
        self::assertCount(2, $crawler->filter('.testimonial-grid blockquote'));
        self::assertSelectorExists('.site-footer');
        self::assertSelectorTextContains('.site-footer', 'Lundi–Dimanche');
        self::assertSelectorTextContains('.site-footer', 'Conditions générales de vente');
    }
}
