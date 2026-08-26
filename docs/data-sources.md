# Data sources

## Canonical Polish sources

The Ministry of Education publishes the school-year calendar and the winter-break groups. These announcements are the source of truth used by the library:

- [School-year calendar index](https://www.gov.pl/web/edukacja/kalendarz-roku-szkolnego)
- [2020/2021 pandemic-wide winter break](https://www.gov.pl/web/edukacja/wytyczne-polkolonie-w-szkole)
- [2021/2022 winter break](https://www.gov.pl/web/edukacja/powrot-uczniow-do-nauki-stacjonarnej-od-10-stycznia-2022-r)
- [2022/2023 winter break](https://www.gov.pl/web/edukacja/bezpieczne-ferie-zimowe-2023)
- [2023/2024 winter break](https://www.gov.pl/web/edukacja/terminy-ferii-zimowych-w-roku-szkolnym-20232024)
- [2024/2025 winter break](https://www.gov.pl/web/edukacja/terminy-ferii-zimowych-w-roku-szkolnym-20242025)
- [2025/2026 winter break](https://www.gov.pl/web/edukacja/terminy-ferii-zimowych-w-roku-szkolnym-20252026.)
- [2026/2027 winter break](https://www.gov.pl/web/edukacja/terminy-ferii-zimowych-w-roku-szkolnym-20262027)
- [2027/2028 winter break](https://www.gov.pl/web/edukacja/terminy-ferii-zimowych-w-roku-szkolnym-20272028)
- [Consolidated Regulation of 11 August 2017](https://isap.sejm.gov.pl/isap.nsf/download.xsp/WDU20230001211/O/D20231211.pdf)

The regulation defines the national rules used by `PolishSchoolHolidays`:

- Christmas break is 23–31 December, or 22–31 December when 22 December is a Monday.
- Spring break is Maundy Thursday through the Tuesday after Easter.
- Classes end on the first Friday strictly after 20 June; summer holidays start the following day and end on 31 August.

## Szkolny.eu investigation

The [Szkolny.eu Android repository](https://github.com/szkolny-eu/szkolny-android) does not ship or calculate a national school-holiday list. Its profile model stores `dateSemester1Start`, `dateSemester2Start`, and `dateYearEnd`; integrations populate those values from each electronic register where available, with approximate defaults used during profile migration/archiving. That makes the project useful for account-specific school-year boundaries, but not a canonical source for regional Polish holidays.

## Other datasets

[OpenHolidays API](https://www.openholidaysapi.org/en/) is a useful secondary cross-check and offers Polish school holidays from 2020. This library does not copy its ODbL dataset or call its service at runtime; MEN announcements remain the primary source.
