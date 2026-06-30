# XML/HTML5 DOM Button Commandfor Review

Slice: `xml-html5-dom-button-commandfor-review-20260630`

## What Changed

- `XmlHtmlDom` now records all matching `commandfor` target elements for HTML
  `button` command review packets instead of only the first target.
- Duplicate target ids are reported as
  `duplicate-button-commandfor-target-element`, and `commandInvokesTarget`
  stays false when duplicate, missing, invalid, non-popover, or non-dialog
  targets require reviewer resolution.
- Popover, dialog, and custom command summaries preserve target element
  metadata for WordPress raw HTML handoff without invoking browser command
  dispatch, popover/dialog APIs, form submission, resource fetching, Pandoc,
  or external validators.

## Accounting

- `lane-status.json` `phpPass`: `471 -> 472`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2318 -> 2319`
- Added `mappedXmlHtmlDomButtonCommandForReviewCases: 1`
- Added `xmlHtmlDomButtonCommandForReviewAssertions: 63`

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomButtonCommandForReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomButtonCommandForReviewTest.php`
  - 1 test file, 63 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - 66 test files, 11,883 assertions, 0 failures
