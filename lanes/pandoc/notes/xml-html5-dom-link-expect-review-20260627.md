# XML/HTML5 DOM Link Expect Review

Date: 2026-06-27
Task: `plib-l2r9b`

## Scope

- Added bounded `link rel="expect"` review metadata to `XmlHtmlDom::summarizeHtmlFragment()`.
- `rel="expect"` is now classified as an internal-resource link kind, participates in render-blocking candidacy, and reports same-document fragment target provenance.
- The review records missing render-blocking tokens, non-fragment hrefs, invalid fragments, missing targets, and resolved target element summaries without executing browser parser/rendering behavior.

## Source Truth

- WHATWG HTML defines `expect` as a `link`-only internal resource link that can block rendering until the indicated element is connected and fully parsed: <https://html.spec.whatwg.org/multipage/links.html#link-type-expect>
- This PHP slice is metadata-only. It does not fetch linked resources, run browser rendering, execute scripts, invoke Pandoc, or use external validators.

## Counters

- `phpPass`: `464 -> 465`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `2306 -> 2307`
- `xmlHtmlDomLinkExpectReviewCases`: `1`
- `mappedXmlHtmlDomLinkExpectReviewCases`: `1`
- `xmlHtmlDomLinkExpectReviewAssertions`: `52`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomLinkExpectReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomLinkExpectReviewTest.php`
  - `1 test files, 52 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomLinkExpectReviewTest.php lanes/pandoc/tests/XmlHtmlDomLinkFetchPolicyReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `3 test files, 6356 assertions, 0 failures`
