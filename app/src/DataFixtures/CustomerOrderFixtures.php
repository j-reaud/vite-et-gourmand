<?php

namespace App\DataFixtures;

use App\Entity\CustomerOrder;
use App\Entity\Menu;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CustomerOrderFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function getDependencies(): array
    {
        return [
            MenuFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $customer = $manager->getRepository(User::class)->findOneBy([
            'email' => 'client.commandes@vite-et-gourmand.fr',
        ]);

        if (!$customer instanceof User) {
            $customer = (new User())
                ->setEmail('client.commandes@vite-et-gourmand.fr')
                ->setFirstName('Camille')
                ->setLastName('Martin')
                ->setPhone('06 12 34 56 78')
                ->setAddress('24 rue Sainte-Catherine, 33000 Bordeaux')
                ->setRoles([])
                ->setIsActive(true);

            $customer->setPassword(
                $this->passwordHasher->hashPassword($customer, 'CommandeTest1!')
            );

            $manager->persist($customer);
        }

        $existingOrder = $manager
            ->getRepository(CustomerOrder::class)
            ->findOneBy(['customer' => $customer]);

        if ($existingOrder instanceof CustomerOrder) {
            return;
        }

        $orders = [
            [
                'menuSlug' => 'dejeuner-de-printemps',
                'orderedAt' => new \DateTimeImmutable('-35 days'),
                'deliveryAt' => new \DateTimeImmutable('-30 days'),
                'people' => 6,
                'statuses' => [
                    CustomerOrder::STATUS_CONFIRMED,
                    CustomerOrder::STATUS_PREPARING,
                    CustomerOrder::STATUS_READY,
                    CustomerOrder::STATUS_OUT_FOR_DELIVERY,
                    CustomerOrder::STATUS_DELIVERED,
                ],
                'menuPrice' => '144.00',
                'discount' => '10.00',
                'deliveryCharge' => '15.00',
                'totalPrice' => '149.00',
            ],
            [
                'menuSlug' => 'table-bordelaise',
                'orderedAt' => new \DateTimeImmutable('-8 days'),
                'deliveryAt' => new \DateTimeImmutable('+2 days'),
                'people' => 8,
                'statuses' => [
                    CustomerOrder::STATUS_CONFIRMED,
                    CustomerOrder::STATUS_PREPARING,
                ],
                'menuPrice' => '256.00',
                'discount' => null,
                'deliveryCharge' => '20.00',
                'totalPrice' => '276.00',
            ],
            [
                'menuSlug' => 'jardin-gourmand',
                'orderedAt' => new \DateTimeImmutable('-2 days'),
                'deliveryAt' => new \DateTimeImmutable('+7 days'),
                'people' => 4,
                'statuses' => [
                    CustomerOrder::STATUS_CONFIRMED,
                ],
                'menuPrice' => '88.00',
                'discount' => null,
                'deliveryCharge' => '12.00',
                'totalPrice' => '100.00',
            ],
        ];

        foreach ($orders as $orderData) {
            $menu = $manager->getRepository(Menu::class)->findOneBy([
                'slug' => $orderData['menuSlug'],
            ]);

            if (!$menu instanceof Menu) {
                throw new \RuntimeException(
                    sprintf('Menu "%s" not found.', $orderData['menuSlug'])
                );
            }

            $order = (new CustomerOrder())
                ->setCustomer($customer)
                ->setMenu($menu)
                ->setMenuName((string) $menu->getTitle())
                ->setOrderedAt($orderData['orderedAt'])
                ->setDeliveryAt($orderData['deliveryAt'])
                ->setNumberOfPeople($orderData['people'])
                ->setMenuPrice($orderData['menuPrice'])
                ->setDiscountAmount($orderData['discount'])
                ->setDeliveryCharge($orderData['deliveryCharge'])
                ->setTotalPrice($orderData['totalPrice'])
                ->setDeliveryAddress(
                    '24 rue Sainte-Catherine, 33000 Bordeaux'
                );

            foreach ($orderData['statuses'] as $status) {
                $order->setStatus($status);
            }

            $manager->persist($order);
        }

        $manager->flush();
    }
}
