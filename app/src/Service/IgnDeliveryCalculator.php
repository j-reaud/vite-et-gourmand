<?php

namespace App\Service;

use App\Exception\DeliveryCalculationException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class IgnDeliveryCalculator implements DeliveryCalculatorInterface
{
    private const BORDEAUX_CITY_CODE = '33063';
    private const BASE_DELIVERY_FEE_IN_CENTS = 500;
    private const PRICE_PER_KILOMETER_IN_CENTS = 59;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly float $companyLongitude,
        private readonly float $companyLatitude,
    ) {
    }

    public function calculate(string $deliveryAddress): DeliveryQuote
    {
        $destination = $this->geocode($deliveryAddress);

        if ($destination['cityCode'] === self::BORDEAUX_CITY_CODE) {
            return new DeliveryQuote(
                distanceKm: 0.0,
                deliveryFeeInCents: self::BASE_DELIVERY_FEE_IN_CENTS,
            );
        }

        $distanceKm = $this->calculateRoadDistance(
            $destination['longitude'],
            $destination['latitude'],
        );

        $deliveryFeeInCents = self::BASE_DELIVERY_FEE_IN_CENTS
            + (int) round(
                $distanceKm * self::PRICE_PER_KILOMETER_IN_CENTS,
            );

        return new DeliveryQuote(
            distanceKm: $distanceKm,
            deliveryFeeInCents: $deliveryFeeInCents,
        );
    }

    /**
     * @return array{
     *     longitude: float,
     *     latitude: float,
     *     cityCode: string
     * }
     */
    private function geocode(string $address): array
    {
        try {
            $data = $this->httpClient->request(
                'GET',
                'https://data.geopf.fr/geocodage/search',
                [
                    'query' => [
                        'q' => $address,
                        'index' => 'address',
                        'limit' => 1,
                    ],
                    'timeout' => 8,
                ],
            )->toArray();
        } catch (
            TransportExceptionInterface
            | HttpExceptionInterface
            | DecodingExceptionInterface $exception
        ) {
            throw new DeliveryCalculationException(
                'Le service de vérification des adresses est indisponible.',
                previous: $exception,
            );
        }

        $feature = $data['features'][0] ?? null;
        $coordinates = $feature['geometry']['coordinates'] ?? null;
        $cityCode = $feature['properties']['citycode'] ?? null;

        if (
            !is_array($coordinates)
            || count($coordinates) < 2
            || !is_numeric($coordinates[0])
            || !is_numeric($coordinates[1])
            || !is_string($cityCode)
        ) {
            throw new DeliveryCalculationException(
                'L’adresse de livraison n’a pas pu être localisée.',
            );
        }

        return [
            'longitude' => (float) $coordinates[0],
            'latitude' => (float) $coordinates[1],
            'cityCode' => $cityCode,
        ];
    }

    private function calculateRoadDistance(
        float $destinationLongitude,
        float $destinationLatitude,
    ): float {
        try {
            $data = $this->httpClient->request(
                'GET',
                'https://data.geopf.fr/navigation/itineraire',
                [
                    'query' => [
                        'resource' => 'bdtopo-osrm',
                        'start' => $this->formatCoordinates(
                            $this->companyLongitude,
                            $this->companyLatitude,
                        ),
                        'end' => $this->formatCoordinates(
                            $destinationLongitude,
                            $destinationLatitude,
                        ),
                        'profile' => 'car',
                        'optimization' => 'fastest',
                        'distanceUnit' => 'kilometer',
                        'getSteps' => 'false',
                        'getBbox' => 'false',
                    ],
                    'timeout' => 8,
                ],
            )->toArray();
        } catch (
            TransportExceptionInterface
            | HttpExceptionInterface
            | DecodingExceptionInterface $exception
        ) {
            throw new DeliveryCalculationException(
                'Le calcul de la distance de livraison est indisponible.',
                previous: $exception,
            );
        }

        $distance = $data['distance'] ?? null;

        if (!is_numeric($distance) || (float) $distance < 0) {
            throw new DeliveryCalculationException(
                'La distance de livraison n’a pas pu être calculée.',
            );
        }

        return (float) $distance;
    }

    private function formatCoordinates(
        float $longitude,
        float $latitude,
    ): string {
        return sprintf('%.6F,%.6F', $longitude, $latitude);
    }
}
