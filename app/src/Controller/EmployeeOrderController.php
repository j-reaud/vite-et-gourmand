<?php

namespace App\Controller;

use App\Entity\CustomerOrder;
use App\Entity\OrderModification;
use App\Entity\User;
use App\Exception\DeliveryCalculationException;
use App\Form\EmployeeOrderEditFormType;
use App\Repository\CustomerOrderRepository;
use App\Service\CustomerOrderPriceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employee/orders', name: 'app_employee_order_')]
#[IsGranted('ROLE_EMPLOYEE')]
final class EmployeeOrderController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        CustomerOrderRepository $orderRepository,
    ): Response {
        return $this->render('employee_order/index.html.twig', [
            'orders' => $orderRepository->findBy(
                [],
                ['orderedAt' => 'DESC'],
            ),
        ]);
    }

    #[Route(
        '/{id}/edit',
        name: 'edit',
        requirements: ['id' => '\d+'],
        methods: ['GET', 'POST'],
    )]
    public function edit(
        CustomerOrder $order,
        Request $request,
        CustomerOrderPriceCalculator $priceCalculator,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$order->isEmployeeEditable()) {
            $this->addFlash(
                'employee_order_edit_unavailable',
                'Cette commande ne peut plus être modifiée.',
            );

            return $this->redirectToRoute('app_employee_order_index');
        }

        $employee = $this->getUser();

        if (!$employee instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $minimumPeople = $order->getMenu()?->getMinimumPeople();

        if ($minimumPeople === null || $minimumPeople < 1) {
            throw $this->createNotFoundException(
                'Le nombre minimum de personnes est invalide.',
            );
        }

        $previousData = $this->createOrderSnapshot($order);

        $form = $this->createForm(
            EmployeeOrderEditFormType::class,
            $order,
            ['minimum_people' => $minimumPeople],
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $priceCalculator->recalculate($order);
            } catch (DeliveryCalculationException $exception) {
                $form->get('deliveryAddress')->addError(
                    new FormError($exception->getMessage()),
                );
            }

            if ($form->isValid()) {
                $modification = (new OrderModification())
                    ->setModifiedBy($employee)
                    ->setContactMethod(
                        (string) $form->get('contactMethod')->getData(),
                    )
                    ->setReason(trim(
                        (string) $form->get('reason')->getData(),
                    ))
                    ->setPreviousData($previousData)
                    ->setNewData($this->createOrderSnapshot($order));

                $order->addOrderModification($modification);

                $entityManager->persist($modification);
                $entityManager->flush();

                $this->addFlash(
                    'employee_order_edit_success',
                    'La commande a bien été modifiée.',
                );

                return $this->redirectToRoute('app_employee_order_index');
            }
        }

        return $this->render(
            'employee_order/edit.html.twig',
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

    /**
     * @return array<string, int|string|null>
     */
    private function createOrderSnapshot(CustomerOrder $order): array
    {
        return [
            'deliveryAddress' => $order->getDeliveryAddress(),
            'deliveryAt' => $order->getDeliveryAt()?->format(DATE_ATOM),
            'numberOfPeople' => $order->getNumberOfPeople(),
            'menuPrice' => $order->getMenuPrice(),
            'deliveryCharge' => $order->getDeliveryCharge(),
            'deliveryDistanceKm' => $order->getDeliveryDistanceKm(),
            'totalPrice' => $order->getTotalPrice(),
        ];
    }
}
