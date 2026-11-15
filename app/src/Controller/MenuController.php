<?php

namespace App\Controller;

use App\Entity\CustomerOrder;
use App\Entity\Menu;
use App\Entity\User;
use App\Form\OrderPlacementFormType;
use App\Repository\MenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MenuController extends AbstractController
{
    #[Route('/menus', name: 'app_menu', methods: ['GET'])]
    public function index(Request $request, MenuRepository $menuRepository): Response
    {
        $sort = $request->query->getString('sort', 'recommended');
        if (!in_array($sort, ['recommended', 'price_asc', 'price_desc'], true)) {
            $sort = 'recommended';
        }

        $filters = [
            'minPrice' => $this->positiveFloatOrNull($request->query->get('minPrice')),
            'maxPrice' => $this->positiveFloatOrNull($request->query->get('maxPrice')),
            'theme' => $this->nonEmptyStringOrNull($request->query->get('theme')),
            'diet' => $this->nonEmptyStringOrNull($request->query->get('diet')),
            'people' => $this->positiveIntegerOrNull($request->query->get('people')),
            'sort' => $sort,
        ];

        return $this->render('menu/index.html.twig', [
            'menus' => $menuRepository->findPublishedByFilters($filters),
            'themes' => $menuRepository->findPublishedThemes(),
            'diets' => $menuRepository->findPublishedDiets(),
            'filters' => $filters,
        ]);
    }

    #[Route('/menus/{slug}', name: 'app_menu_show', methods: ['GET'])]
    public function show(Menu $menu): Response
    {
        if (!$menu->isPublished()) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        $orderForm = null;
        $minimumPeople = $menu->getMinimumPeople();

        if (
            $user instanceof User
            && !$this->isGranted('ROLE_EMPLOYEE')
            && !$this->isGranted('ROLE_ADMIN')
            && $menu->getStock() > 0
            && $minimumPeople !== null
        ) {
            $order = (new CustomerOrder())
                ->setDeliveryAddress((string) $user->getAddress())
                ->setNumberOfPeople($minimumPeople)
                ->setDiscountAmount('0.00');

            $orderForm = $this->createForm(
                OrderPlacementFormType::class,
                $order,
                [
                    'customer' => $user,
                    'minimum_people' => $minimumPeople,
                    'action' => $this->generateUrl(
                        'app_customer_order_place',
                        ['slug' => $menu->getSlug()],
                    ),
                    'method' => 'POST',
                ],
            );
        }

        return $this->render('menu/show.html.twig', [
            'menu' => $menu,
            'orderForm' => $orderForm?->createView(),
            'openOrderModal' => false,
        ]);
    }

    private function positiveFloatOrNull(mixed $value): ?float
    {
        if (!is_scalar($value) || '' === trim((string) $value)) {
            return null;
        }

        $number = filter_var($value, FILTER_VALIDATE_FLOAT);

        return false !== $number && $number >= 0 ? $number : null;
    }

    private function positiveIntegerOrNull(mixed $value): ?int
    {
        if (!is_scalar($value) || '' === trim((string) $value)) {
            return null;
        }

        $number = filter_var($value, FILTER_VALIDATE_INT);

        return false !== $number && $number > 0 ? $number : null;
    }

    private function nonEmptyStringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }
}
