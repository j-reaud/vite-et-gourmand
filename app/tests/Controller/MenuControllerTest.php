<?php

namespace App\Tests\Controller;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MenuControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private MenuRepository $menuRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->menuRepository = $container->get(MenuRepository::class);

        foreach ($this->menuRepository->findAll() as $menu) {
            $this->entityManager->remove($menu);
        }

        $this->entityManager->flush();

        $this->createMenu('Menu classique', 'menu-classique', '120.00', 4, 'Classique', 'Classique', 8);
        $this->createMenu('Menu végétarien', 'menu-vegetarien', '88.00', 4, 'Événement', 'Végétarien', 2);
        $this->createMenu('Menu vegan', 'menu-vegan', '190.00', 8, 'Événement', 'Vegan', 0);
    }

    public function testCatalogueIsPublicAndDisplaysPublishedMenus(): void
    {
        $crawler = $this->client->request('GET', '/menus');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Des menus inspirés par la saison');
        self::assertCount(3, $crawler->filter('.catalogue-card'));
        self::assertSelectorTextContains('.catalogue-card', 'Menu classique');
        self::assertSelectorTextContains('.catalogue-card__button--disabled', 'Indisponible');
        self::assertSelectorExists('[data-controller="catalogue-filters"]');
    }

    public function testCatalogueCanBeFilteredByDietAndNumberOfPeople(): void
    {
        $crawler = $this->client->request('GET', '/menus?diet=Végétarien&people=4');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.catalogue-card'));
        self::assertSelectorTextContains('.catalogue-card', 'Menu végétarien');
    }

    public function testCatalogueCanBeSortedByAscendingPrice(): void
    {
        $crawler = $this->client->request('GET', '/menus?sort=price_asc');

        self::assertResponseIsSuccessful();
        self::assertSame('Menu végétarien', trim($crawler->filter('.catalogue-card__title h3')->first()->text()));
    }

    public function testPublishedMenuDetailIsAccessible(): void
    {
        $this->client->request('GET', '/menus/menu-classique');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Menu classique');
        self::assertSelectorTextContains('.menu-detail__price', '120 € pour 4 personnes');
    }

    private function createMenu(
        string $title,
        string $slug,
        string $price,
        int $minimumPeople,
        string $theme,
        string $diet,
        int $stock,
    ): void {
        $menu = (new Menu())
            ->setTitle($title)
            ->setSlug($slug)
            ->setDescription('Description du menu de test.')
            ->setImagePath('images/home/menu-spring.webp')
            ->setPrice($price)
            ->setMinimumPeople($minimumPeople)
            ->setTheme($theme)
            ->setDiet($diet)
            ->setStock($stock)
            ->setIsPublished(true);

        $this->entityManager->persist($menu);
        $this->entityManager->flush();
    }
}
