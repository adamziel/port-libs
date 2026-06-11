# XML/HTML5 DOM time metadata slice

Date: 2026-06-11 UTC
Base: current main 0f7efc602
Bead: plib-jndoi

## Scope

`XmlHtmlDom::summarizeHtmlFragment()` now records reviewer-facing `<time>` metadata without changing deterministic HTML serialization. The summary exposes:

- `time`, `timeText`
- `timeDatetimeRaw`, `timeDatetime`, `timeDatetimeKind`, `timeDatetimeValid`
- date, month, week, year, clock time, duration, local datetime, global datetime, invalid datetime, and missing datetime classification

The normalizer mirrors the existing HTML5 fragment sanitizer's bounded time metadata rules so compact DOM summaries and WordPress handoff metadata agree on the core classifications.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 test file, 604 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64530 assertions, 0 failures

No Pandoc executable, browser engine, online sanitizer, external validator, office suite, zip/unzip tooling, or live service was invoked.
