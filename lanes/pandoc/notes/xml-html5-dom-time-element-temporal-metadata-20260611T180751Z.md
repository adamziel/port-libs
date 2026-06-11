# XML/HTML5 DOM Time Element Temporal Metadata

Bead: `plib-p1wx1`
Base: `4bb725eee`
Date: 2026-06-11 UTC

This slice extends the existing XML/HTML5 DOM `time` element reviewer handoff
with `timeValue*` compatibility aliases and global-time parsing for time-only
values with timezone offsets, without invoking Pandoc, browser renderers,
online sanitizers, external validators, online services, or live provider tests.

The summary keeps the current `timeDatetime*` provenance fields while also
recording raw value aliases, normalized value aliases, `global-time`
classification, timezone normalization for time-only values, invalid date
diagnostics, dataset provenance, and unchanged deterministic HTML serialization.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 test file, 835 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66499 assertions, 0 failures

Focused coverage added one `XmlHtmlDomTest` case with 29 assertions.
