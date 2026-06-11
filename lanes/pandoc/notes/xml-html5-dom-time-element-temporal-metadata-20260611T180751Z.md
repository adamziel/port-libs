# XML/HTML5 DOM Time Element Temporal Metadata

Bead: `plib-p1wx1`
Base: `298ff12f3`
Date: 2026-06-11 UTC

This slice extends the XML/HTML5 DOM reviewer handoff to summarize inert HTML
`time` element temporal metadata without invoking Pandoc, browser renderers,
online sanitizers, external validators, online services, or live provider
tests.

The new summary records raw `datetime` values, text fallback values, normalized
`date`, `month`, `time`, `global-time`, `local-datetime`, and
`global-datetime` values, timezone normalization for time-only values, invalid
date diagnostics, dataset provenance, and unchanged deterministic HTML
serialization.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 test file, 674 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64746 assertions, 0 failures

Focused coverage added one `XmlHtmlDomTest` case with 29 assertions.
