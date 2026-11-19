<?php

namespace App\Service;

use App\Document\AccountRegistration;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\UTCDateTime;

final class AccountKpiService
{
    private const FRENCH_MONTHS = [
        1 => 'Janvier',
        2 => 'Février',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Août',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre',
    ];

    public function __construct(
        private readonly DocumentManager $documentManager,
    ) {
    }

    /**
     * @return array{
     *     customers: array{total: int, labels: list<string>, values: list<int>, maximum: int},
     *     employees: array{total: int, labels: list<string>, values: list<int>, maximum: int}
     * }
     */
    public function getDashboardData(): array
    {
        return [
            'customers' => $this->getKpi(AccountRegistration::TYPE_CUSTOMER),
            'employees' => $this->getKpi(AccountRegistration::TYPE_EMPLOYEE),
        ];
    }

    /**
     * @return array{total: int, labels: list<string>, values: list<int>, maximum: int}
     */
    private function getKpi(string $accountType): array
    {
        $collection = $this->documentManager
            ->getDocumentCollection(AccountRegistration::class);
        $start = new DateTimeImmutable(
            'first day of -11 months midnight',
            new DateTimeZone('UTC'),
        );
        $monthlyTotals = [];

        $results = $collection->aggregate([
            [
                '$match' => [
                    'accountType' => $accountType,
                    'registeredAt' => ['$gte' => new UTCDateTime($start)],
                ],
            ],
            [
                '$group' => [
                    '_id' => [
                        '$dateToString' => [
                            'format' => '%Y-%m',
                            'date' => '$registeredAt',
                        ],
                    ],
                    'total' => ['$sum' => 1],
                ],
            ],
        ]);

        foreach ($results as $result) {
            $monthlyTotals[(string) $result['_id']] = (int) $result['total'];
        }

        $labels = [];
        $values = [];
        $month = $start;

        for ($index = 0; $index < 12; ++$index) {
            $key = $month->format('Y-m');
            $monthNumber = (int) $month->format('n');
            $labels[] = sprintf(
                '%s %s',
                self::FRENCH_MONTHS[$monthNumber],
                $month->format('Y'),
            );
            $values[] = $monthlyTotals[$key] ?? 0;
            $month = $month->modify('+1 month');
        }

        return [
            'total' => $collection->countDocuments([
                'accountType' => $accountType,
            ]),
            'labels' => $labels,
            'values' => $values,
            'maximum' => max(1, ...$values),
        ];
    }
}
