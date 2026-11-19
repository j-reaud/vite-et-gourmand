<?php

namespace App\Service;

use App\Document\AccountRegistration;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;

final class AccountKpiService
{
    private const FRENCH_MONTHS = [
        1 => ['long' => 'Janvier', 'short' => 'Jan.'],
        2 => ['long' => 'Février', 'short' => 'Fév.'],
        3 => ['long' => 'Mars', 'short' => 'Mars'],
        4 => ['long' => 'Avril', 'short' => 'Avr.'],
        5 => ['long' => 'Mai', 'short' => 'Mai'],
        6 => ['long' => 'Juin', 'short' => 'Juin'],
        7 => ['long' => 'Juillet', 'short' => 'Juil.'],
        8 => ['long' => 'Août', 'short' => 'Août'],
        9 => ['long' => 'Septembre', 'short' => 'Sept.'],
        10 => ['long' => 'Octobre', 'short' => 'Oct.'],
        11 => ['long' => 'Novembre', 'short' => 'Nov.'],
        12 => ['long' => 'Décembre', 'short' => 'Déc.'],
    ];

    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{
     *     customers: array{total: int, labels: list<string>, shortLabels: list<string>, values: list<int>, maximum: int},
     *     employees: array{total: int, labels: list<string>, shortLabels: list<string>, values: list<int>, maximum: int}
     * }
     */
    public function getDashboardData(): array
    {
        $timezone = new DateTimeZone('Europe/Paris');
        $start = new DateTimeImmutable('first day of -11 months midnight', $timezone);
        $series = $this->createEmptySeries($start);
        $customerTotal = 0;

        try {
            $collection = $this->documentManager
                ->getDocumentCollection(AccountRegistration::class);
            $result = $collection->aggregate([
                [
                    '$facet' => [
                        'customerTotal' => [
                            ['$match' => [
                                'accountType' => AccountRegistration::TYPE_CUSTOMER,
                            ]],
                            ['$count' => 'total'],
                        ],
                        'customerMonthly' => $this->monthlyPipeline(
                            AccountRegistration::TYPE_CUSTOMER,
                            $start,
                        ),
                        'employeeMonthly' => $this->monthlyPipeline(
                            AccountRegistration::TYPE_EMPLOYEE,
                            $start,
                        ),
                    ],
                ],
            ])->toArray()[0] ?? null;

            if ($result !== null) {
                $customerTotal = (int) (
                    $result['customerTotal'][0]['total'] ?? 0
                );
                $series['customers'] = $this->mergeMonthlyTotals(
                    $series['customers'],
                    $result['customerMonthly'],
                );
                $series['employees'] = $this->mergeMonthlyTotals(
                    $series['employees'],
                    $result['employeeMonthly'],
                );
            }
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Account KPI analytics could not be loaded from MongoDB.',
                ['exception' => $exception],
            );
        }

        return [
            'customers' => $this->formatKpi(
                $customerTotal,
                $series['customers'],
            ),
            'employees' => $this->formatKpi(
                $this->userRepository->countActiveEmployees(),
                $series['employees'],
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function monthlyPipeline(
        string $accountType,
        DateTimeImmutable $start,
    ): array {
        return [
            ['$match' => [
                'accountType' => $accountType,
                'registeredAt' => ['$gte' => new UTCDateTime($start)],
            ]],
            ['$group' => [
                '_id' => ['$dateToString' => [
                    'format' => '%Y-%m',
                    'date' => '$registeredAt',
                    'timezone' => 'Europe/Paris',
                ]],
                'total' => ['$sum' => 1],
            ]],
        ];
    }

    /**
     * @return array{
     *     customers: array{labels: list<string>, shortLabels: list<string>, valuesByMonth: array<string, int>},
     *     employees: array{labels: list<string>, shortLabels: list<string>, valuesByMonth: array<string, int>}
     * }
     */
    private function createEmptySeries(DateTimeImmutable $start): array
    {
        $labels = [];
        $shortLabels = [];
        $valuesByMonth = [];
        $month = $start;

        for ($index = 0; $index < 12; ++$index) {
            $monthNumber = (int) $month->format('n');
            $labels[] = sprintf(
                '%s %s',
                self::FRENCH_MONTHS[$monthNumber]['long'],
                $month->format('Y'),
            );
            $shortLabels[] = self::FRENCH_MONTHS[$monthNumber]['short'];
            $valuesByMonth[$month->format('Y-m')] = 0;
            $month = $month->modify('+1 month');
        }

        $empty = [
            'labels' => $labels,
            'shortLabels' => $shortLabels,
            'valuesByMonth' => $valuesByMonth,
        ];

        return ['customers' => $empty, 'employees' => $empty];
    }

    /**
     * @param array{labels: list<string>, shortLabels: list<string>, valuesByMonth: array<string, int>} $series
     * @param iterable<array-key, mixed> $results
     *
     * @return array{labels: list<string>, shortLabels: list<string>, valuesByMonth: array<string, int>}
     */
    private function mergeMonthlyTotals(array $series, iterable $results): array
    {
        foreach ($results as $result) {
            $key = (string) $result['_id'];

            if (array_key_exists($key, $series['valuesByMonth'])) {
                $series['valuesByMonth'][$key] = (int) $result['total'];
            }
        }

        return $series;
    }

    /**
     * @param array{labels: list<string>, shortLabels: list<string>, valuesByMonth: array<string, int>} $series
     *
     * @return array{total: int, labels: list<string>, shortLabels: list<string>, values: list<int>, maximum: int}
     */
    private function formatKpi(int $total, array $series): array
    {
        $values = array_values($series['valuesByMonth']);

        return [
            'total' => $total,
            'labels' => $series['labels'],
            'shortLabels' => $series['shortLabels'],
            'values' => $values,
            'maximum' => max(1, ...$values),
        ];
    }
}
