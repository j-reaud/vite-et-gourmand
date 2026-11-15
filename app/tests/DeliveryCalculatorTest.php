<?php

namespace App\Tests;

use App\Exception\DeliveryCalculationException;
use App\Service\IgnDeliveryCalculator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DeliveryCalculatorTest extends TestCase
{
    // Verifies that a Bordeaux delivery costs the fixed five-euro fee.
    public function testBordeauxDeliveryUsesFixedFee(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'features' => [[
                    'geometry' => [
                        'coordinates' => [-0.578803, 44.838168],
                    ],
                    'properties' => [
                        'citycode' => '33063',
                    ],
                ]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $calculator = new IgnDeliveryCalculator(
            $httpClient,
            -0.57918,
            44.83779,
        );

        $quote = $calculator->calculate(
            '1 Place Pey Berland, 33000 Bordeaux',
        );

        self::assertSame(0.0, $quote->getDistanceKm());
        self::assertSame(500, $quote->getDeliveryFeeInCents());
        self::assertSame('0.00', $quote->getFormattedDistance());
        self::assertSame('5.00', $quote->getFormattedDeliveryFee());
    }

    // Verifies the base fee plus 59 cents per kilometre outside Bordeaux.
    public function testOutsideBordeauxUsesRoadDistance(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'features' => [[
                    'geometry' => [
                        'coordinates' => [-0.2437, 44.9153],
                    ],
                    'properties' => [
                        'citycode' => '33243',
                    ],
                ]],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'distance' => 35.4,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $calculator = new IgnDeliveryCalculator(
            $httpClient,
            -0.57918,
            44.83779,
        );

        $quote = $calculator->calculate(
            'Place Abel Surchamp, 33500 Libourne',
        );

        self::assertSame(35.4, $quote->getDistanceKm());
        self::assertSame(2589, $quote->getDeliveryFeeInCents());
        self::assertSame('35.40', $quote->getFormattedDistance());
        self::assertSame('25.89', $quote->getFormattedDeliveryFee());
    }

    // Verifies that an address not found by IGN is rejected.
    public function testUnknownAddressIsRejected(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'features' => [],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $calculator = new IgnDeliveryCalculator(
            $httpClient,
            -0.57918,
            44.83779,
        );

        $this->expectException(DeliveryCalculationException::class);
        $this->expectExceptionMessage(
            'L’adresse de livraison n’a pas pu être localisée.',
        );

        $calculator->calculate('Adresse inexistante');
    }

    // Verifies that an IGN service failure becomes a controlled error.
    public function testUnavailableIgnServiceIsHandled(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
        ]);

        $calculator = new IgnDeliveryCalculator(
            $httpClient,
            -0.57918,
            44.83779,
        );

        $this->expectException(DeliveryCalculationException::class);
        $this->expectExceptionMessage(
            'Le service de vérification des adresses est indisponible.',
        );

        $calculator->calculate(
            '1 Place Pey Berland, 33000 Bordeaux',
        );
    }
}
