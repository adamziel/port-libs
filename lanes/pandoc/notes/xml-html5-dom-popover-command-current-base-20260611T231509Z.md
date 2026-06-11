# XML/HTML5 DOM popover command slice

Slice: `plib-738bc`

Base: `4d330e2a13`

## Summary

XML/HTML5 DOM handoff now summarizes HTML popover command attributes for reviewer packets. The summary covers inert popover panels, raw and normalized popover state, popover target IDs, valid `show`/`hide`/`toggle` actions, empty-action defaulting to `toggle`, and invalid action diagnostics.

The slice keeps deterministic raw HTML serialization and WordPress raw HTML handoff intact without invoking Pandoc, browser renderers, online sanitizers, external validators, online services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed: 1 test file, 866 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 67066 assertions, 0 failures.

## Lane Status

- Added one focused `XmlHtmlDomTest` PASS case with 26 assertions.
- `phpPass` moved from 3140 to 3141.
- `phpFail` remains 0.
