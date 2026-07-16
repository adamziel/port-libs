# XML/HTML5 DOM Time Datetime Provenance

Date: 2026-06-11
Base: current main 8e9b86a87

## Scope

This slice extends native PHP XML/HTML5 DOM review summaries for HTML `time` elements without invoking Pandoc, browser renderers, online sanitizers, external validators, online services, live provider tests, or live-service provider tests.

## Behavior

- `XmlHtmlDom::summarizeHtmlFragment()` now marks `time` elements with explicit time review metadata.
- The summary reports visible time text, raw `datetime` attributes, whether the candidate came from the attribute, text fallback, or missing state, normalized value, kind, and validity.
- The parser covers bounded HTML time value classes: date, month, week, year, time, local datetime, global datetime, and duration.
- Invalid or missing values remain review-visible without changing deterministic HTML serialization or WordPress raw HTML handoff.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`: 1 test file, 806 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 66403 assertions, 0 failures.

## Accounting

- `phpPass`: 3127 -> 3128
- `phpFail`: 0
- Added `mappedXmlHtmlDomTimeDatetimeProvenanceCases`: 1
- Added `xmlHtmlDomTimeDatetimeProvenanceAssertions`: 39
