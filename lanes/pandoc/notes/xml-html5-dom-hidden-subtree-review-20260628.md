# XML/HTML5 DOM Hidden Subtree Review

## Slice

- Added `XmlHtmlDom` review metadata for effective hidden subtrees.
- Elements with `hidden`, `hidden="hidden"`, `hidden="until-found"`, or invalid hidden tokens now expose `html-hidden-state-review` fields.
- Descendants inside hidden subtrees now carry `html-hidden-subtree-review` provenance with source element name/id and `until-found` state.
- Raw HTML serialization and WordPress raw HTML handoff remain unchanged; no browser rendering, layout, or reveal behavior is invoked.

## Accounting

- `lane-status.json` `phpPass`: `467 -> 468`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2308 -> 2309`
- New manifest keys:
  - `xmlHtmlDomHiddenSubtreeReviewCases`
  - `mappedXmlHtmlDomHiddenSubtreeReviewCases`
  - `xmlHtmlDomHiddenSubtreeReviewAssertions`

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomHiddenSubtreeReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomHiddenSubtreeReviewTest.php`
  - `1 test files, 36 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomHiddenSubtreeReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `2 test files, 6260 assertions, 0 failures`
