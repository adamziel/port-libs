# Pandoc XML/HTML noscript fallback review

Bead: `plib-yu5xv`
Date: 2026-06-12 UTC
Area: XML/HTML5 DOM core blocker

## Behavior

`XmlHtmlDom` now records `noscript` fallback source as inert reviewer metadata.
The summary preserves escaped fallback source length and SHA-256 provenance,
tracks whether the fallback looks like markup or active content, and performs a
bounded inert fragment review of the fallback source.

For parsed fallback fragments, reviewers can inspect top-level element names,
text hashes, link hrefs, image sources, form actions, active descendant names,
and embedded descendant names. Unsafe or unparseable fallback source is reported
as diagnostics rather than being repaired silently. Serialized raw HTML keeps the
fallback escaped, and WordPress raw HTML handoff receives the deterministic
serialized fragment.

No Pandoc binary, browser renderer, online sanitizer, external validator, online
service, live provider test, or live-service provider test was invoked.

## Accounting

- `phpPass`: `3272 -> 3273`
- `phpFail`: `0`
- `mappedXmlHtmlDomNoscriptFallbackReviewCases`: `+1`
- `xmlHtmlDomNoscriptFallbackReviewAssertions`: `+36`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 1763 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `5 test files, 4698 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 73301 assertions, 0 failures`
