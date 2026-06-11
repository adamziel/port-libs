# XML/HTML5 DOM time value metadata

Slice: plib-l26oa, Pandoc XML/HTML5 DOM core blocker, 2026-06-11T174553Z.

Base: current main 0b4dca730.

XmlHtmlDom now preserves HTML `time` element value provenance in native PHP reviewer summaries. The summary records the raw `datetime` attribute when present, text fallback values when it is absent, the value source, normalized date/month/week/time/local-datetime/global-datetime classifications, validity flags, and the visible time text while leaving deterministic HTML serialization unchanged.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed: 1 test file, 657 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 64649 assertions, 0 failures.

No Pandoc, browser renderer, online sanitizer, external validator, online service, live provider, or live-service provider test was invoked.
