# XML/HTML5 DOM Editing Keyboard Hints

Slice: `pandoc-xml-html5-dom-editing-keyboard-hints-current-base-20260611T2234Z`
Base: `origin/main` at `71ce25fbe`

## Summary

`XmlHtmlDom::summarizeHtmlFragment()` now exposes reviewer metadata for HTML editing keyboard hint global attributes:

- `inputmode` raw tokens and normalized keyboard modes.
- `enterkeyhint` raw tokens and normalized enter-key hints.
- `autocapitalize`, `autocorrect`, and `writingsuggestions` raw values plus normalized reviewer states.
- Invalid values remain visible as raw attributes while normalized state is `null`.

The slice stays native PHP and does not invoke Pandoc, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 test file, 836 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66714 assertions, 0 failures.

## Metric Movement

- `lane-status.json` `phpPass`: `3133 -> 3134`
- `lane-status.json` `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3218 -> 3219`
- Added `mappedXmlHtmlDomEditingKeyboardHintCases: 1`
- Added `xmlHtmlDomEditingKeyboardHintAssertions: 30`
