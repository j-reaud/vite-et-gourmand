<?php

namespace App\Controller;

use App\Entity\CustomerOrder;
use App\Entity\Menu;
use App\Entity\User;
use App\Form\OrderPlacementFormType;
use App\Repository\CustomerOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile/orders', name: 'app_customer_order_')]
#[IsGranted('ROLE_USER')]
final class CustomerOrderController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        CustomerOrderRepository $orderRepository,
    ): Response {
        $customer = $this->getAuthenticatedCustomer();

        $search = trim($request->query->getString('search'));
        $status = $request->query->getString('status');
        $periodInput = $request->query->getString('period');
        $periodValue = ctype_digit($periodInput) ? (int) $periodInput : 0;

        $status = in_array($status, CustomerOrder::STATUSES, true)
            ? $status
            : null;

        $period = in_array($periodValue, [3, 6, 12], true)
            ? $periodValue
            : null;

        $allOrders = $orderRepository->findForCustomer($customer);
        $orders = $orderRepository->findForCustomer(
            $customer,
            $search !== '' ? $search : null,
            $status,
            $period,
        );

        $now = new \DateTimeImmutable();
        $upcomingOrder = null;
        $totalOrdered = 0.0;

        foreach ($allOrders as $order) {
            $totalOrdered += (float) $order->getTotalPrice();

            if (
                $order->getDeliveryAt() === null
                || $order->getDeliveryAt() <= $now
                || in_array(
                    $order->getStatus(),
                    [
                        CustomerOrder::STATUS_DELIVERED,
                        CustomerOrder::STATUS_CANCELLED,
                    ],
                    true,
                )
            ) {
                continue;
            }

            if (
                $upcomingOrder === null
                || $order->getDeliveryAt() < $upcomingOrder->getDeliveryAt()
            ) {
                $upcomingOrder = $order;
            }
        }

        return $this->render('customer_order/index.html.twig', [
            'orders' => $orders,
            'totalOrders' => count($allOrders),
            'upcomingOrder' => $upcomingOrder,
            'totalOrdered' => $totalOrdered,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'period' => $period,
            ],
        ]);
    }

    #[Route('/new/{slug}', name: 'place', methods: ['POST'])]
    public function place(
        Menu $menu,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $customer = $this->getAuthenticatedCustomer();

        if (!$menu->isPublished() || $menu->getStock() < 1) {
            throw $this->createNotFoundException('Ce menu est indisponible.');
        }

        $minimumPeople = $menu->getMinimumPeople();

        if ($minimumPeople === null || $minimumPeople < 1) {
            throw $this->createNotFoundException(
                'Le nombre minimum de personnes est invalide.',
            );
        }

        $order = (new CustomerOrder())
            ->setCustomer($customer)
            ->setMenu($menu)
            ->setMenuName((string) $menu->getTitle())
            ->setOrderedAt(new \DateTimeImmutable())
            ->setNumberOfPeople($minimumPeople)
            ->setStatus(CustomerOrder::STATUS_PENDING)
            ->setMenuPrice((string) $menu->getPrice())
            ->setDiscountAmount('0.00')
            ->setDeliveryCharge('0.00')
            ->setTotalPrice((string) $menu->getPrice())
            ->setDeliveryAddress((string) $customer->getAddress());

        $form = $this->createForm(
            OrderPlacementFormType::class,
            $order,
            [
                'customer' => $customer,
                'minimum_people' => $minimumPeople,
            ],
        );

        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render(
                'menu/show.html.twig',
                [
                    'menu' => $menu,
                    'orderForm' => $form->createView(),
                    'openOrderModal' => true,
                ],
                new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY),
            );
        }

        $numberOfPeople = $order->getNumberOfPeople();
        $basePriceInCents = (int) round((float) $menu->getPrice() * 100);
        $totalInCents = (int) round(
            ($basePriceInCents / $minimumPeople) * $numberOfPeople,
        );
        $calculatedPrice = number_format(
            $totalInCents / 100,
            2,
            '.',
            '',
        );

        $order
            ->setMenuPrice($calculatedPrice)
            ->setDiscountAmount('0.00')
            ->setDeliveryCharge('0.00')
            ->setTotalPrice($calculatedPrice)
            ->setStatus(CustomerOrder::STATUS_PENDING);

        $menu->setStock($menu->getStock() - 1);

        $entityManager->persist($order);
        $entityManager->flush();

        $this->addFlash(
            'order_success',
            'Votre commande a bien été transmise à nos équipes. '
            .'Une fois votre commande confirmée, vous recevrez '
            .'une confirmation dans votre espace client.',
        );

        return $this->redirectToRoute('app_menu_show', [
            'slug' => $menu->getSlug(),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        CustomerOrderRepository $orderRepository,
    ): Response {
        $customer = $this->getAuthenticatedCustomer();
        $order = $orderRepository->findOneForCustomer($id, $customer);

        if ($order === null) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        return $this->render('customer_order/show.html.twig', [
            'order' => $order,
        ]);
    }

    private function getAuthenticatedCustomer(): User
    {
        $user = $this->getUser();

        if (
            !$user instanceof User
            || $this->isGranted('ROLE_EMPLOYEE')
            || $this->isGranted('ROLE_ADMIN')
        ) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
