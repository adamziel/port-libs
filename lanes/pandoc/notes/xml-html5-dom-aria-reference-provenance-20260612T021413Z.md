# Pandoc XML/HTML5 DOM ARIA Reference Provenance Slice

Bead: `plib-d0cjd`
Base: `412827d77a8070c90660124f3451d32b6ba7257b`
Scope: `lanes/pandoc`

`XmlHtmlDom` now summarizes ARIA ID-reference relationships for reviewer handoff. Covered attributes are `aria-activedescendant`, `aria-controls`, `aria-describedby`, `aria-details`, `aria-errormessage`, `aria-flowto`, `aria-labelledby`, and `aria-owns`.

The summary preserves raw tokens, deduplicated IDs, resolved target element names/text, missing references, aggregate referenced/missing ID lists, and validity while keeping existing raw `aria-*` attribute preservation and WordPress raw-block handoff intact. No Pandoc, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

Verification on current main `412827d77a`:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` - 1 file, 1240 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 69480 assertions, 0 failures

Lane status:

- Added one focused `XmlHtmlDomTest` PASS case with 22 assertions.
- `phpPass` moved from 3176 to 3177.
- `phpFail` remains 0.
