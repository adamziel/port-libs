# XML/HTML5 DOM hyperlink navigation review

Bead: `plib-4a9cj`
Area: Pandoc XML/HTML5 DOM primitives
Base: `5559ac388b`

This slice adds bounded native PHP reviewer provenance for HTML anchor and
image-map area navigation side effects in `XmlHtmlDom`. The summary now records
href kind/scheme safety, target reserved-name and opener/noopener state,
download state, normalized rel token counts, duplicate and invalid rel tokens,
referrer-policy validation, ping URL records, unsafe/non-HTTP ping buckets, and
deterministic issue rollups while preserving raw HTML serialization.

It does not fetch URLs, execute pings, run browser navigation, invoke Pandoc,
Cabal/Haskell runners, Node tooling, external validators, online services, live
provider tests, or live-service provider tests.

Accounting:

- `phpPass`: `3439 -> 3440`
- `phpFail`: remains `0`
- Added `mappedXmlHtmlDomHyperlinkNavigationReviewCases = 1`
- Added `xmlHtmlDomHyperlinkNavigationReviewAssertions = 48`

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  passed with `1 test files, 3320 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `46 test files, 80042 assertions, 0 failures`.
