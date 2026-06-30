# XML/HTML5 DOM Form Dirname Review - 2026-06-28

This slice adds self-contained native PHP review metadata for HTML form
`dirname` directionality handoff. `XmlHtmlDom` now preserves the raw dirname
submit name, validates the name token, records effective submitted direction
from self, inherited, or default `dir` state, reports invalid-name and
control-name collision diagnostics, records form-owner and disabled submission
metadata, and marks the packet as review-only without submitting forms or
invoking browser form APIs.

The focused handoff is covered by
`lanes/pandoc/tests/XmlHtmlDomFormDirnameReviewTest.php`, which verifies
inherited direction, `dir=auto` static resolution, invalid dirname tokens,
name collisions, default `ltr` direction, raw HTML serialization, and WordPress
raw HTML handoff.

Metric movement:
- `lane-status.json` `phpPass`: `469 -> 470`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2316 -> 2317`
- Added `mappedXmlHtmlDomFormDirnameReviewCases = 1`
- Added `xmlHtmlDomFormDirnameReviewAssertions = 61`

Validation:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomFormDirnameReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormDirnameReviewTest.php`: 1 file, 61 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFormDirnameReviewTest.php`: 2 files, 6,285 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`: 58 files, 8,561 assertions, 0 failures
