<?php

namespace App\Controller;

use App\Entity\CustomerOrder;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
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

        if (!$user instanceof User || $this->isGranted('ROLE_EMPLOYEE')) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
