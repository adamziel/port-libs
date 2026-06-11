# XML/HTML5 DOM Dialog/Popover Interaction State

Slice: `pandoc-xml-html5-dom-dialog-popover-current-base-20260611T2202Z`
Base: `origin/main` at `895143aff`

## Summary

`XmlHtmlDom::summarizeHtmlFragment()` now exposes reviewer metadata for HTML
dialog and popover interaction state:

- `dialog` open/closed state and normalized dialog text.
- `popover` raw and normalized state for auto, manual, hint, and invalid values.
- `inert`, `autofocus`, and `accesskey` global interaction attributes.
- `popovertarget` and normalized `popovertargetaction` invoker metadata.

The slice stays native PHP and does not invoke Pandoc, Cabal/Haskell runners,
browser renderers, external validators, online services, live provider tests, or
live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 test file, 839 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66585 assertions, 0 failures.

## Metric Movement

- `lane-status.json` `phpPass`: `3131 -> 3132`
- `lane-status.json` `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3216 -> 3217`
- Added `mappedXmlHtmlDomDialogPopoverCases: 1`
- Added `xmlHtmlDomDialogPopoverAssertions: 33`
