# XML/HTML5 DOM Link Resource Review Provenance

Area: Pandoc XML/HTML5 DOM primitives

This slice adds bounded native PHP reviewer provenance for HTML `<link>`
resource hints and preload metadata in `XmlHtmlDom`. Link summaries now expose
normalized rel-token counts, duplicate and invalid rel diagnostics, custom rel
tokens, resource kind and resource-hint classification, href-required state,
preload `as` normalization/validation, and issue rollups while preserving
deterministic raw HTML serialization and WordPress raw-block handoff.

The slice is intentionally limited to inert review metadata for link elements.
It does not fetch resources, resolve URLs, validate external targets, invoke
Pandoc, Cabal/Haskell runners, browser renderers, external validators, online
services, live provider tests, or live-service provider tests.

## Accounting

- `phpPass`: `3267 -> 3268`
- `phpFail`: remains `0`
- Added `mappedXmlHtmlDomLinkResourceReviewCases: 1`
- Added `xmlHtmlDomLinkResourceReviewAssertions: 38`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 1727 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php`
  - `5 test files, 4662 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 73145 assertions, 0 failures`
