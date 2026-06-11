# XML/HTML5 DOM dialog state

2026-06-11 current-base slice for `plib-ihqfs` on main `282d4fe1b`.

`XmlHtmlDom::summarizeHtmlFragment()` now records static HTML dialog review
metadata:

- `dialog`, `open`, and normalized `dialogText`
- descendant `dialogForms` with raw and normalized method values
- `dialogFormCount` and `dialogMethodFormCount`

The slice keeps deterministic HTML serialization unchanged while making
`method="dialog"` submit paths visible to package-review handoff code.

Focused verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed
  1 test file, 785 assertions, 0 failures
- full `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files,
  66111 assertions, 0 failures

Direct-format parity remains native PHP only. Verification does not invoke
Pandoc, browser renderers, online sanitizers, external validators, online
services, live provider tests, or live-service provider tests.
