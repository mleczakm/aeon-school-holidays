# Aeon School Holidays

Polish school holiday calendars for [Aeon PHP](https://aeon-php.org/docs/calendar-holidays/), with winter breaks selected by voivodeship.

The provider is offline and deterministic. It implements `Aeon\Calendar\Holidays`, so it works directly with Aeon's business-hours and holiday-chain abstractions.

## Installation

```bash
composer require mleczakm/aeon-school-holidays
```

Until the package is published on Packagist, add its Git repository to the consuming project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/mleczakm/aeon-school-holidays.git"
        }
    ]
}
```

## Usage

```php
use Aeon\Calendar\Gregorian\Day;
use Mleczakm\AeonSchoolHolidays\PolishSchoolHolidays;
use Mleczakm\AeonSchoolHolidays\Voivodeship;

$holidays = new PolishSchoolHolidays(Voivodeship::Masovian);

$holidays->isHoliday(Day::fromString('2027-02-01')); // true
$holidays->isHoliday(Day::fromString('2027-03-01')); // false

$holiday = $holidays->holidaysAt(Day::fromString('2027-02-01'))[0];
$holiday->name('pl'); // Ferie zimowe
$holiday->name('en'); // Winter school holidays
```

ISO 3166-2 codes are also supported at configuration boundaries:

```php
$region = Voivodeship::fromIsoCode('PL-14');
$holidays = new PolishSchoolHolidays($region);
```

To combine school holidays with Polish public holidays, use Aeon's `HolidaysChain`:

```php
use Aeon\Calendar\Holidays\GoogleCalendarRegionalHolidays;
use Aeon\Calendar\Holidays\HolidaysChain;

$allDaysOff = new HolidaysChain(
    new GoogleCalendarRegionalHolidays('PL'),
    new PolishSchoolHolidays(Voivodeship::Masovian),
);
```

## Included dates

The library covers the official school breaks common to Polish public primary and secondary schools:

- winter Christmas break (`Zimowa przerwa świąteczna`),
- regional winter holidays (`Ferie zimowe`),
- spring Easter break (`Wiosenna przerwa świąteczna`),
- summer holidays (`Ferie letnie`).

Winter schedules are included for school years **2020/2021 through 2027/2028**. Unsupported school years throw Aeon's `HolidayYearException` instead of silently returning incomplete data. The exceptional nationwide winter break of 4–17 January 2021 is included.

Public holidays, weekends, exam dates, teacher-training days, and additional days chosen by an individual head teacher are outside this provider's scope. Chain a public-holiday provider and store school-specific closures separately where needed.

Aeon's interface represents holidays one day at a time. A two-week winter break therefore produces fourteen `Holiday` instances when queried with `in()`.

## Sources and maintenance

The authoritative source is the Polish Ministry of Education's [school-year calendar](https://www.gov.pl/web/edukacja/kalendarz-roku-szkolnego). National break rules come from the [Regulation of 11 August 2017 on the organisation of the school year](https://isap.sejm.gov.pl/isap.nsf/download.xsp/WDU20230001211/O/D20231211.pdf). Regional winter dates are copied from the Ministry announcements linked in [docs/data-sources.md](docs/data-sources.md).

The schedules are intentionally committed as reviewed data rather than scraped at runtime. MEN publishes winter dates no later than the end of June two years before the break; add the next school year to `OfficialWinterBreakSchedule` and its expected dates to the tests when a new announcement appears.

For an unpublished or school-specific schedule, implement `WinterBreakSchedule` and pass it as the second argument to `PolishSchoolHolidays`. This keeps missing official data explicit while allowing consuming applications to opt into their own source.

## Development

```bash
composer install
composer qa
```

## License

MIT
