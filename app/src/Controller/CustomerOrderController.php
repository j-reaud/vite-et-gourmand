<?php

namespace App\Controller;

use App\Entity\CustomerOrder;
use App\Entity\Menu;
use App\Entity\User;
use App\Exception\DeliveryCalculationException;
use App\Form\CustomerOrderEditFormType;
use App\Form\OrderPlacementFormType;
use App\Repository\CustomerOrderRepository;
use App\Service\CustomerOrderPriceCalculator;
use App\Service\OrderConfirmationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
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

    #[Route(
        '/delivery-quote/{slug}',
        name: 'delivery_quote',
        methods: ['POST'],
    )]
    public function deliveryQuote(
        Menu $menu,
        Request $request,
        CustomerOrderPriceCalculator $priceCalculator,
    ): JsonResponse {
        $this->getAuthenticatedCustomer();

        if (
            !$this->isCsrfTokenValid(
                'delivery_quote_'.$menu->getId(),
                $request->request->getString('_token'),
            )
        ) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $deliveryAddress = trim(
            $request->request->getString('deliveryAddress'),
        );
        $numberOfPeople = $request->request->getInt('numberOfPeople');
        $minimumPeople = $menu->getMinimumPeople();

        if (
            $deliveryAddress === ''
            || $minimumPeople === null
            || $numberOfPeople < $minimumPeople
            || !$menu->isPublished()
            || $menu->getStock() < 1
        ) {
            return $this->json(
                ['error' => 'Les informations de livraison sont invalides.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $priceQuote = $priceCalculator->quote(
                menu: $menu,
                numberOfPeople: $numberOfPeople,
                deliveryAddress: $deliveryAddress,
            );
        } catch (DeliveryCalculationException $exception) {
            return $this->json(
                ['error' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->json([
            'distanceKm' => $priceQuote->getFormattedDistance(),
            'recognizedAddress' => $priceQuote->getRecognizedAddress(),
            'menuPrice' => $priceQuote->getFormattedMenuPrice(),
            'deliveryCharge' => $priceQuote->getFormattedDeliveryFee(),
            'totalPrice' => $priceQuote->getFormattedTotal(),
        ]);
    }

    #[Route('/new/{slug}', name: 'place', methods: ['POST'])]
    public function place(
        Menu $menu,
        Request $request,
        EntityManagerInterface $entityManager,
        CustomerOrderPriceCalculator $priceCalculator,
        OrderConfirmationMailer $orderConfirmationMailer,
        LoggerInterface $logger,
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

        try {
            $priceCalculator->recalculate($order);
        } catch (DeliveryCalculationException $exception) {
            $form->get('deliveryAddress')->addError(
                new FormError($exception->getMessage()),
            );

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

        $order
            ->setDiscountAmount('0.00')
            ->setStatus(CustomerOrder::STATUS_PENDING);

        $menu->setStock($menu->getStock() - 1);

        $entityManager->persist($order);
        $entityManager->flush();

        $emailSent = false;

        try {
            $orderConfirmationMailer->send($order);
            $emailSent = true;
        } catch (TransportExceptionInterface $exception) {
            $logger->error(
                'The order confirmation email could not be sent.',
                [
                    'order_reference' => $order->getReference(),
                    'customer_id' => $customer->getId(),
                    'exception' => $exception,
                ],
            );

            $this->addFlash(
                'order_email_warning',
                'Votre commande est enregistrée, mais l’e-mail de '
                .'confirmation n’a pas pu être envoyé.',
            );
        }

        $this->addFlash(
            'order_success',
            'Votre commande a bien été transmise à nos équipes. '
            .($emailSent
                ? 'Un e-mail récapitulatif vous a été envoyé.'
                : ''),
        );

        return $this->redirectToRoute('app_menu_show', [
            'slug' => $menu->getSlug(),
        ]);
    }

    #[Route(
        '/{id}/edit',
        name: 'edit',
        requirements: ['id' => '\d+'],
        methods: ['GET', 'POST'],
    )]
    public function edit(
        int $id,
        Request $request,
        CustomerOrderRepository $orderRepository,
        CustomerOrderPriceCalculator $priceCalculator,
        EntityManagerInterface $entityManager,
    ): Response {
        $customer = $this->getAuthenticatedCustomer();
        $order = $orderRepository->findOneForCustomer($id, $customer);

        if ($order === null) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        if (!$order->isCustomerEditable()) {
            $this->addFlash(
                'order_edit_unavailable',
                'Cette commande ne peut plus être modifiée.',
            );

            return $this->redirectToRoute('app_customer_order_show', [
                'id' => $order->getId(),
            ]);
        }

        $minimumPeople = $order->getMenu()?->getMinimumPeople();

        if ($minimumPeople === null || $minimumPeople < 1) {
            throw $this->createNotFoundException(
                'Le nombre minimum de personnes est invalide.',
            );
        }

        $agreedMenuPrice = $order->getMenuPrice();
        $agreedNumberOfPeople = $order->getNumberOfPeople();

        $form = $this->createForm(
            CustomerOrderEditFormType::class,
            $order,
            ['minimum_people' => $minimumPeople],
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $priceCalculator->recalculate(
                    $order,
                    $agreedMenuPrice,
                    $agreedNumberOfPeople,
                );
            } catch (DeliveryCalculationException $exception) {
                $form->get('deliveryAddress')->addError(
                    new FormError($exception->getMessage()),
                );
            }

            if ($form->isValid()) {
                $entityManager->flush();

                $this->addFlash(
                    'order_edit_success',
                    'Votre commande a bien été modifiée.',
                );

                return $this->redirectToRoute('app_customer_order_show', [
                    'id' => $order->getId(),
                ]);
            }
        }

        return $this->render(
            'customer_order/edit.html.twig',
            [
                'order' => $order,
                'editForm' => $form->createView(),
            ],
            new Response(
                status: $form->isSubmitted()
                    ? Response::HTTP_UNPROCESSABLE_ENTITY
                    : Response::HTTP_OK,
            ),
        );
    }

    #[Route(
        '/{id}/cancel',
        name: 'cancel',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function cancel(
        int $id,
        Request $request,
        CustomerOrderRepository $orderRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $customer = $this->getAuthenticatedCustomer();
        $order = $orderRepository->findOneForCustomer($id, $customer);

        if ($order === null) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        if (
            !$this->isCsrfTokenValid(
                'cancel_order_'.$order->getId(),
                $request->request->getString('_token'),
            )
        ) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$order->isCancellable()) {
            $this->addFlash(
                'order_cancel_unavailable',
                'Cette commande ne peut plus être annulée. '
                .'Contactez-nous pour obtenir plus d’informations.',
            );

            return $this->redirectToRoute('app_customer_order_show', [
                'id' => $order->getId(),
            ]);
        }

        $order->setStatus(CustomerOrder::STATUS_CANCELLED);

        $menu = $order->getMenu();

        if ($menu !== null) {
            $menu->setStock($menu->getStock() + 1);
        }

        $entityManager->flush();

        $this->addFlash(
            'order_cancel_success',
            'Votre commande a bien été annulée.',
        );

        return $this->redirectToRoute('app_customer_order_show', [
            'id' => $order->getId(),
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
