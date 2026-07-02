# XML/HTML5 DOM Media Fetch Policy Review

Slice: `plib-riz4d`

`XmlHtmlDom` now emits metadata-only media fetch policy review packets for
`<audio>` and `<video>` elements. The summary records raw and normalized
`crossorigin` state, declared `src` and child `<source>` URL candidates, unsafe
source URLs, empty source URLs, missing source `src` attributes, and compact
issue-code rollups for reviewer handoff.

The slice preserves raw HTML serialization and WordPress raw-block handoff. It
does not fetch media resources, invoke browser media loaders, probe codecs, or
claim playback/layout parity.

Accounting:

- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2325 -> 2326`
- Added `mappedXmlHtmlDomMediaFetchPolicyReviewCases = 1`
- Added `xmlHtmlDomMediaFetchPolicyReviewAssertions = 56`

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomMediaFetchPolicyReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomMediaFetchPolicyReviewTest.php`
  - 1 test file, 56 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomMediaFetchPolicyReviewTest.php lanes/pandoc/tests/XmlHtmlDomMediaPreloadReviewTest.php lanes/pandoc/tests/XmlHtmlDomMediaControlsPolicyTest.php lanes/pandoc/tests/XmlHtmlDomVideoPosterReviewTest.php lanes/pandoc/tests/XmlHtmlDomImageLoadingReviewTest.php lanes/pandoc/tests/XmlHtmlDomImageLoadingIssueReviewTest.php lanes/pandoc/tests/XmlHtmlDomResponsiveSelectionReviewTest.php lanes/pandoc/tests/XmlHtmlDomSourceSizeListReviewTest.php lanes/pandoc/tests/XmlHtmlDomSrcsetResourceReviewTest.php`
  - 9 test files, 374 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php lanes/pandoc/tests/Html5Dom*.php lanes/pandoc/tests/XmlHtml5DomTest.php`
  - 90 test files, 13089 assertions, 0 failures
