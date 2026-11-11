<?php

namespace App\DataFixtures;

use App\Entity\Menu;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class MenuFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $menus = [
            [
                'title' => 'Déjeuner de printemps',
                'slug' => 'dejeuner-de-printemps',
                'description' => 'Asperges rôties, volaille fermière aux herbes et tarte fine aux fraises.',
                'imagePath' => 'images/home/menu-spring.webp',
                'price' => '144.00',
                'minimumPeople' => 6,
                'theme' => 'Classique',
                'diet' => 'Classique',
                'stock' => 8,
            ],
            [
                'title' => 'Table bordelaise',
                'slug' => 'table-bordelaise',
                'description' => 'Gravlax de truite, canard confit revisité et canelé au chocolat noir.',
                'imagePath' => 'images/home/menu-bordeaux.webp',
                'price' => '256.00',
                'minimumPeople' => 8,
                'theme' => 'Événement',
                'diet' => 'Classique',
                'stock' => 2,
            ],
            [
                'title' => 'Jardin gourmand',
                'slug' => 'jardin-gourmand',
                'description' => 'Légumes rôtis, céréales parfumées, herbes fraîches et dessert fruité.',
                'imagePath' => 'images/home/menu-vegetarian.webp',
                'price' => '88.00',
                'minimumPeople' => 4,
                'theme' => 'Classique',
                'diet' => 'Végétarien',
                'stock' => 6,
            ],
            [
                'title' => 'Festin de Noël',
                'slug' => 'festin-de-noel',
                'description' => 'Foie gras, suprême de volaille aux morilles et bûche chocolat-praliné.',
                'imagePath' => 'images/home/menu-bordeaux.webp',
                'price' => '390.00',
                'minimumPeople' => 10,
                'theme' => 'Noël',
                'diet' => 'Classique',
                'stock' => 0,
            ],
            [
                'title' => 'Pâques en famille',
                'slug' => 'paques-en-famille',
                'description' => 'Œuf parfait, épaule d’agneau confite et entremets citron-verveine.',
                'imagePath' => 'images/home/menu-spring.webp',
                'price' => '198.00',
                'minimumPeople' => 6,
                'theme' => 'Pâques',
                'diet' => 'Classique',
                'stock' => 4,
            ],
            [
                'title' => 'Escale végétale',
                'slug' => 'escale-vegetale',
                'description' => 'Tartare de betterave, tajine de saison et pavlova aux fruits frais.',
                'imagePath' => 'images/home/menu-vegetarian.webp',
                'price' => '162.00',
                'minimumPeople' => 6,
                'theme' => 'Événement',
                'diet' => 'Vegan',
                'stock' => 5,
            ],
        ];

        foreach ($menus as $menuData) {
            $menu = (new Menu())
                ->setTitle($menuData['title'])
                ->setSlug($menuData['slug'])
                ->setDescription($menuData['description'])
                ->setImagePath($menuData['imagePath'])
                ->setPrice($menuData['price'])
                ->setMinimumPeople($menuData['minimumPeople'])
                ->setTheme($menuData['theme'])
                ->setDiet($menuData['diet'])
                ->setStock($menuData['stock'])
                ->setIsPublished(true);

            $manager->persist($menu);
        }

        $manager->flush();
    }
}
