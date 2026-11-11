<?php

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Menu>
 */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    /**
     * @param array{
     *     minPrice: float|null,
     *     maxPrice: float|null,
     *     theme: string|null,
     *     diet: string|null,
     *     people: int|null,
     *     sort: string
     * } $filters
     *
     * @return Menu[]
     */
    public function findPublishedByFilters(array $filters): array
    {
        $queryBuilder = $this->createQueryBuilder('menu')
            ->andWhere('menu.isPublished = :published')
            ->setParameter('published', true);

        if (null !== $filters['minPrice']) {
            $queryBuilder
                ->andWhere('menu.price >= :minPrice')
                ->setParameter('minPrice', $filters['minPrice']);
        }

        if (null !== $filters['maxPrice']) {
            $queryBuilder
                ->andWhere('menu.price <= :maxPrice')
                ->setParameter('maxPrice', $filters['maxPrice']);
        }

        if (null !== $filters['theme']) {
            $queryBuilder
                ->andWhere('menu.theme = :theme')
                ->setParameter('theme', $filters['theme']);
        }

        if (null !== $filters['diet']) {
            $queryBuilder
                ->andWhere('menu.diet = :diet')
                ->setParameter('diet', $filters['diet']);
        }

        if (null !== $filters['people']) {
            $queryBuilder
                ->andWhere('menu.minimumPeople <= :people')
                ->setParameter('people', $filters['people']);
        }

        match ($filters['sort']) {
            'price_asc' => $queryBuilder->orderBy('menu.price', 'ASC'),
            'price_desc' => $queryBuilder->orderBy('menu.price', 'DESC'),
            default => $queryBuilder->orderBy('menu.id', 'ASC'),
        };

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return string[]
     */
    public function findPublishedThemes(): array
    {
        $results = $this->createQueryBuilder('menu')
            ->select('DISTINCT menu.theme AS value')
            ->andWhere('menu.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('menu.theme', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'value');
    }

    /**
     * @return string[]
     */
    public function findPublishedDiets(): array
    {
        $results = $this->createQueryBuilder('menu')
            ->select('DISTINCT menu.diet AS value')
            ->andWhere('menu.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('menu.diet', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'value');
    }
}
