# XML/HTML5 DOM Button Command Target Review

Date: 2026-06-14
Base: `1deca4653e`
Task: `plib-jidsn`

## Scope

- Added bounded HTML button `command` and `commandfor` review metadata to `XmlHtmlDom::summarizeHtmlFragment()`.
- Preserved built-in popover and dialog command state, custom `--*` command state, target ID resolution, target kind summaries, missing/invalid target issue codes, unknown command issue codes, and submit-button classification.
- Kept the slice metadata-only: it does not execute commands, dispatch events, run JavaScript, invoke browser renderers, call Pandoc, use online sanitizers, or rely on external validators.

## Counters

- `phpPass`: `3504 -> 3505`
- `phpFail`: `0`
- `mapped`: `3423 -> 3424`
- `mappedXmlHtmlDomButtonCommandReviewCases`: `1`
- `xmlHtmlDomButtonCommandReviewAssertions`: `59`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 4054 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 82552 assertions, 0 failures`
