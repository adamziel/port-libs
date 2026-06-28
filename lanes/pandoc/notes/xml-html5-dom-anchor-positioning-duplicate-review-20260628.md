# XML/HTML5 DOM Anchor Positioning Duplicate Review - 2026-06-28

This slice adds bounded native PHP review metadata for duplicate HTML anchor
positioning targets. `XmlHtmlDom` now records all same-document elements that
match an `anchor` target id, reports duplicate target counts, flags duplicate
target ids as diagnostics, and keeps the packet metadata-only without invoking
browser layout or anchor positioning engines.

The focused handoff is covered by
`lanes/pandoc/tests/XmlHtmlDomAnchorPositioningDuplicateReviewTest.php`, which
verifies duplicate and unique anchor target provenance through raw HTML
serialization and WordPress raw HTML handoff.

Metric movement:
- `lane-status.json` `phpPass`: `469 -> 470`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2316 -> 2317`
- Added `mappedXmlHtmlDomAnchorPositioningDuplicateReviewCases = 1`
- Added `xmlHtmlDomAnchorPositioningDuplicateReviewAssertions = 27`

Validation:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomAnchorPositioningDuplicateReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomAnchorPositioningDuplicateReviewTest.php`: 1 file, 27 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomAnchorPositioningDuplicateReviewTest.php`: 2 files, 6,251 assertions, 0 failures
