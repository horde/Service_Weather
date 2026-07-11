<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Domain;

use DateTimeImmutable;
use Horde\Service\Weather\Domain\ForecastPeriod;
use Horde\Service\Weather\Domain\Wind;
use Horde\Service\Weather\ValueObject\Humidity;
use Horde\Service\Weather\ValueObject\Pressure;
use Horde\Service\Weather\ValueObject\Speed;
use Horde\Service\Weather\ValueObject\Temperature;
use Horde\Service\Weather\ValueObject\WeatherCondition;
use Horde\Service\Weather\ValueObject\WindDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ForecastPeriod::class)]
class ForecastPeriodTest extends TestCase
{
    public function testConstructWithRequiredParameters(): void
    {
        $date = new DateTimeImmutable('2026-03-03');
        $temperature = Temperature::fromCelsius(15.0);

        $period = new ForecastPeriod(
            date: $date,
            temperature: $temperature,
            condition: WeatherCondition::CLOUDY
        );

        $this->assertSame($date, $period->date);
        $this->assertSame($temperature, $period->temperature);
        $this->assertSame(WeatherCondition::CLOUDY, $period->condition);
        $this->assertNull($period->highTemperature);
        $this->assertNull($period->lowTemperature);
        $this->assertNull($period->humidity);
        $this->assertNull($period->pressure);
        $this->assertNull($period->wind);
        $this->assertNull($period->precipitationProbability);
        $this->assertNull($period->precipitationAmount);
        $this->assertNull($period->cloudCover);
    }

    public function testConstructWithAllParameters(): void
    {
        $date = new DateTimeImmutable('2026-03-04');
        $temperature = Temperature::fromCelsius(18.0);
        $high = Temperature::fromCelsius(22.0);
        $low = Temperature::fromCelsius(14.0);
        $humidity = Humidity::fromPercentage(60);
        $pressure = Pressure::fromMillibars(1015.0);
        $wind = new Wind(Speed::fromMetersPerSecond(8.0), WindDirection::SE);

        $period = new ForecastPeriod(
            date: $date,
            temperature: $temperature,
            condition: WeatherCondition::RAIN,
            highTemperature: $high,
            lowTemperature: $low,
            humidity: $humidity,
            pressure: $pressure,
            wind: $wind,
            precipitationProbability: 0.75,
            precipitationAmount: 12.5,
            cloudCover: 85
        );

        $this->assertSame($date, $period->date);
        $this->assertSame($temperature, $period->temperature);
        $this->assertSame(WeatherCondition::RAIN, $period->condition);
        $this->assertSame($high, $period->highTemperature);
        $this->assertSame($low, $period->lowTemperature);
        $this->assertSame($humidity, $period->humidity);
        $this->assertSame($pressure, $period->pressure);
        $this->assertSame($wind, $period->wind);
        $this->assertSame(0.75, $period->precipitationProbability);
        $this->assertSame(12.5, $period->precipitationAmount);
        $this->assertSame(85, $period->cloudCover);
    }

    public function testGetters(): void
    {
        $date = new DateTimeImmutable('2026-03-05');
        $temperature = Temperature::fromCelsius(20.0);
        $high = Temperature::fromCelsius(25.0);
        $low = Temperature::fromCelsius(15.0);

        $period = new ForecastPeriod(
            date: $date,
            temperature: $temperature,
            condition: WeatherCondition::CLEAR,
            highTemperature: $high,
            lowTemperature: $low,
            precipitationProbability: 0.1,
            precipitationAmount: 0.5
        );

        $this->assertSame($date, $period->getDate());
        $this->assertSame($temperature, $period->getTemperature());
        $this->assertSame(WeatherCondition::CLEAR, $period->getCondition());
        $this->assertSame($high, $period->getHighTemperature());
        $this->assertSame($low, $period->getLowTemperature());
        $this->assertSame(0.1, $period->getPrecipitationProbability());
        $this->assertSame(0.5, $period->getPrecipitationAmount());
        $this->assertNull($period->getHumidity());
        $this->assertNull($period->getPressure());
        $this->assertNull($period->getWind());
        $this->assertNull($period->getCloudCover());
    }

    public function testUvIndexAndSnowfallAreOptional(): void
    {
        $period = new ForecastPeriod(
            date: new DateTimeImmutable('2026-07-09'),
            temperature: Temperature::fromCelsius(20),
            condition: WeatherCondition::CLEAR,
        );

        $this->assertNull($period->uvIndex);
        $this->assertNull($period->getUvIndex());
        $this->assertNull($period->snowfallAmount);
        $this->assertNull($period->getSnowfallAmount());
    }

    public function testUvIndexAndSnowfallRoundtrip(): void
    {
        $period = new ForecastPeriod(
            date: new DateTimeImmutable('2026-07-09'),
            temperature: Temperature::fromCelsius(20),
            condition: WeatherCondition::CLEAR,
            uvIndex: 6.5,
            snowfallAmount: 2.5,
        );

        $this->assertSame(6.5, $period->uvIndex);
        $this->assertSame(6.5, $period->getUvIndex());
        $this->assertSame(2.5, $period->snowfallAmount);
        $this->assertSame(2.5, $period->getSnowfallAmount());
    }
}
