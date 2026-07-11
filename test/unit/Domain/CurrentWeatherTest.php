<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Domain;

use DateTimeImmutable;
use Horde\Service\Weather\Domain\CurrentWeather;
use Horde\Service\Weather\Domain\Station;
use Horde\Service\Weather\Domain\Wind;
use Horde\Service\Weather\ValueObject\Coordinate;
use Horde\Service\Weather\ValueObject\Humidity;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\Pressure;
use Horde\Service\Weather\ValueObject\PressureTrend;
use Horde\Service\Weather\ValueObject\Speed;
use Horde\Service\Weather\ValueObject\Temperature;
use Horde\Service\Weather\ValueObject\WeatherCondition;
use Horde\Service\Weather\ValueObject\WindDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CurrentWeather::class)]
class CurrentWeatherTest extends TestCase
{
    public function testConstructWithRequiredParameters(): void
    {
        $location = Location::fromCoordinates(52.52, 13.405);
        $temperature = Temperature::fromCelsius(20.0);
        $observationTime = new DateTimeImmutable('2026-03-02 12:00:00');

        $weather = new CurrentWeather(
            location: $location,
            temperature: $temperature,
            condition: WeatherCondition::CLEAR,
            observationTime: $observationTime
        );

        $this->assertSame($location, $weather->location);
        $this->assertSame($temperature, $weather->temperature);
        $this->assertSame(WeatherCondition::CLEAR, $weather->condition);
        $this->assertSame($observationTime, $weather->observationTime);
        $this->assertNull($weather->feelsLike);
        $this->assertNull($weather->humidity);
        $this->assertNull($weather->pressure);
        $this->assertNull($weather->wind);
        $this->assertNull($weather->visibility);
        $this->assertNull($weather->cloudCover);
        $this->assertNull($weather->uvIndex);
    }

    public function testConstructWithAllParameters(): void
    {
        $location = Location::fromCoordinates(48.8566, 2.3522);
        $temperature = Temperature::fromCelsius(18.5);
        $feelsLike = Temperature::fromCelsius(17.0);
        $humidity = Humidity::fromPercentage(65);
        $pressure = Pressure::fromMillibars(1013.25);
        $wind = new Wind(Speed::fromMetersPerSecond(5.0), WindDirection::NW);
        $observationTime = new DateTimeImmutable('2026-03-02 15:30:00');

        $weather = new CurrentWeather(
            location: $location,
            temperature: $temperature,
            condition: WeatherCondition::PARTLY_CLOUDY,
            observationTime: $observationTime,
            feelsLike: $feelsLike,
            humidity: $humidity,
            pressure: $pressure,
            wind: $wind,
            visibility: 10.5,
            cloudCover: 40,
            uvIndex: 5.2,
            providerData: '{"test": "data"}'
        );

        $this->assertSame($location, $weather->location);
        $this->assertSame($temperature, $weather->temperature);
        $this->assertSame(WeatherCondition::PARTLY_CLOUDY, $weather->condition);
        $this->assertSame($observationTime, $weather->observationTime);
        $this->assertSame($feelsLike, $weather->feelsLike);
        $this->assertSame($humidity, $weather->humidity);
        $this->assertSame($pressure, $weather->pressure);
        $this->assertSame($wind, $weather->wind);
        $this->assertSame(10.5, $weather->visibility);
        $this->assertSame(40, $weather->cloudCover);
        $this->assertSame(5.2, $weather->uvIndex);
        $this->assertSame('{"test": "data"}', $weather->providerData);
    }

    public function testGetters(): void
    {
        $location = Location::fromCoordinates(40.7128, -74.0060);
        $temperature = Temperature::fromCelsius(22.0);
        $observationTime = new DateTimeImmutable('2026-03-02 10:00:00');
        $humidity = Humidity::fromPercentage(70);

        $weather = new CurrentWeather(
            location: $location,
            temperature: $temperature,
            condition: WeatherCondition::RAIN,
            observationTime: $observationTime,
            humidity: $humidity
        );

        $this->assertSame($location, $weather->getLocation());
        $this->assertSame($temperature, $weather->getTemperature());
        $this->assertSame(WeatherCondition::RAIN, $weather->getCondition());
        $this->assertSame($observationTime, $weather->getObservationTime());
        $this->assertSame($humidity, $weather->getHumidity());
        $this->assertNull($weather->getFeelsLike());
        $this->assertNull($weather->getPressure());
        $this->assertNull($weather->getWind());
        $this->assertNull($weather->getVisibility());
        $this->assertNull($weather->getCloudCover());
        $this->assertNull($weather->getUvIndex());
        $this->assertNull($weather->getProviderData());
    }

    public function testDewpointPressureTrendAndStationAreOptional(): void
    {
        $weather = new CurrentWeather(
            location: Location::fromCoordinates(0.0, 0.0),
            temperature: Temperature::fromCelsius(10.0),
            condition: WeatherCondition::CLEAR,
            observationTime: new DateTimeImmutable('2026-07-09 12:00:00 UTC'),
        );

        $this->assertNull($weather->dewpoint);
        $this->assertNull($weather->getDewpoint());
        $this->assertNull($weather->pressureTrend);
        $this->assertNull($weather->getPressureTrend());
        $this->assertNull($weather->station);
        $this->assertNull($weather->getStation());
    }

    public function testDewpointPressureTrendAndStationRoundtrip(): void
    {
        $dewpoint = Temperature::fromCelsius(14.5);
        $station = new Station(
            identifier: 'KJFK',
            name: 'John F. Kennedy Intl',
            coordinate: Coordinate::fromLatLon(40.6398, -73.7789),
        );

        $weather = new CurrentWeather(
            location: Location::fromCoordinates(40.7128, -74.0060),
            temperature: Temperature::fromCelsius(22.0),
            condition: WeatherCondition::RAIN,
            observationTime: new DateTimeImmutable('2026-07-09 12:00:00 UTC'),
            dewpoint: $dewpoint,
            pressureTrend: PressureTrend::FALLING,
            station: $station,
        );

        $this->assertSame($dewpoint, $weather->dewpoint);
        $this->assertSame($dewpoint, $weather->getDewpoint());
        $this->assertSame(PressureTrend::FALLING, $weather->pressureTrend);
        $this->assertSame(PressureTrend::FALLING, $weather->getPressureTrend());
        $this->assertSame($station, $weather->station);
        $this->assertSame($station, $weather->getStation());
    }
}
