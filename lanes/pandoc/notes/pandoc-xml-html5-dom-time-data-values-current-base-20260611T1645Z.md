# Pandoc XML/HTML5 DOM time/data value summaries

This slice keeps the XML/HTML5 DOM work bounded to native PHP DOM
summaries. `XmlHtmlDom::summarizeHtmlFragment()` now exposes semantic review
metadata for:

- `<time>` values from `datetime` attributes, including date, global datetime,
  week, duration, invalid datetime classification, and text-derived time values
  when no `datetime` attribute is present.
- `<data>` machine-readable values, including raw value provenance, trimmed
  reviewer value, visible text, and malformed value classification.

The change is low-level reviewer metadata only; it does not alter sanitized
`Html5DomFragment` output and does not invoke Pandoc, browser renderers, online
sanitizers, external validators, online services, live provider tests, or
live-service provider tests.

Verification on current main `51a89684e`:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test file, 540 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 63990 assertions, 0 failures`

Accounting:

- `lane-status.json` `phpPass`: `3072` -> `3073`
- `phpFail`: `0`
