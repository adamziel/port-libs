# XML/HTML DOM Time Value Provenance

Issue: plib-p1wx1

Reconciled historical plib-p1wx1 commits:

- `2d1da841c9` added data and ruby metadata. Current main already contains equivalent/superseding `dataElementSummary()` and `rubySummary()` coverage with focused tests.
- `9214e86e9a` added time value metadata. Current main already contained `timeDatetime*` provenance, but did not expose `timeValue*` reviewer aliases or timezone-qualified time-only values as `global-time`.

This slice completes the remaining HTML DOM time-value recovery surface in native PHP:

- `XmlHtmlDom::timeSummary()` now reports `timeElement`, `timeValueRaw`, `timeValue`, `timeValueKind`, and `timeValueValid` alongside the existing `timeDatetime*` fields.
- `XmlHtmlDom::timeDatetimeSummary()` now accepts timezone-qualified time-only values such as `14:05:30.125-0500` and normalizes them to `global-time`.
- `XmlHtmlDomTest.php` adds `summarizes html time value aliases for reviewer handoff` to cover attribute-backed values, text fallback, invalid values, missing values, raw HTML serialization, and WordPress raw HTML handoff.

Verification after rebase onto `origin/main` `726e0b2d44`:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` - 1 file, 5473 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 243 files, 173618 assertions, 0 failures

No Pandoc, browser, office-suite, Node, external validator, online service, or live provider tooling was invoked.
