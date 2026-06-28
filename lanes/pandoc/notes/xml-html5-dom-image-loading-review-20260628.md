# XML/HTML5 DOM Image Loading Review - 2026-06-28

This slice adds self-contained native PHP review metadata for HTML image loading
policy attributes. `XmlHtmlDom` now carries raw `loading`, `decoding`,
`fetchpriority`, `crossorigin`, and `referrerpolicy` values in the image loading
review packet, records the present policy attributes, reports invalid-token
diagnostics, and marks the packet as review-only without fetching image
resources or invoking browser loaders.

The focused handoff is covered by
`lanes/pandoc/tests/XmlHtmlDomImageLoadingReviewTest.php`, which verifies valid,
missing, and invalid policy metadata through raw HTML serialization and
WordPress raw HTML handoff.

Metric movement:
- `lane-status.json` `phpPass`: `468 -> 469`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2315 -> 2316`
- Added `mappedXmlHtmlDomImageLoadingReviewCases = 1`
- Added `xmlHtmlDomImageLoadingReviewAssertions = 55`

Validation:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomImageLoadingReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomImageLoadingReviewTest.php`: 1 file, 55 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomImageLoadingReviewTest.php`: 2 files, 6,279 assertions, 0 failures
