# XML/HTML5 DOM Media Preload Review

Slice: `plib-aw4wa`

This slice extends the native PHP `XmlHtmlDom` media summary for audio/video
preload provenance. `<audio>` and `<video>` summaries now expose the raw
`preload` token, effective preload state, validity, missing/empty/invalid
auto-default reason, and invalid-token issue records before raw HTML and
WordPress handoff.

The behavior stays metadata-only: it does not fetch media resources, invoke a
browser loader, or claim playback/layout parity.

Accounting:

- `phpPass`: `467 -> 468`
- `mapped`: `2308 -> 2309`
- Added `mappedXmlHtmlDomMediaPreloadReviewCases = 1`
- Added `xmlHtmlDomMediaPreloadReviewAssertions = 39`

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomMediaPreloadReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomMediaPreloadReviewTest.php`
  - 1 test file, 39 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`
  - 43 test files, 7,863 assertions, 0 failures
