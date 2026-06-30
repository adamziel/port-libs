# XML/HTML5 DOM Tabindex Review - 2026-06-30

This slice adds self-contained native PHP review metadata for static HTML
`tabindex` focus-order handoff. `XmlHtmlDom` now records the fragment-wide
tabindex candidate inventory, classifies positive, zero, negative, and invalid
values, reports duplicate positive-value diagnostics, suppresses effectively
disabled form controls as focus candidates, and marks the packet as
metadata-only without invoking browser focus navigation.

The focused handoff is covered by
`lanes/pandoc/tests/XmlHtmlDomTabIndexReviewTest.php`, which verifies
positive-order sorting, same-value collisions, disabled-control suppression,
zero and negative focus candidates, invalid tabindex tokens, raw HTML
serialization, and WordPress raw HTML handoff.

Metric movement:
- `lane-status.json` `phpPass`: `475 -> 476`
- Added `mappedXmlHtmlDomTabIndexReviewCases = 1`
- Added `xmlHtmlDomTabIndexReviewAssertions = 58`

Validation:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTabIndexReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTabIndexReviewTest.php`: 1 file, 58 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomTabIndexReviewTest.php`: 2 files, 6,282 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`: 69 files, 9,130 assertions, 0 failures
