# XML/HTML5 DOM search and address landmarks

Bead: `plib-3q54j`
Base: current main `43da9d16f6`

## Scope

`XmlHtmlDom::summarizeHtmlFragment()` now preserves reviewer metadata for
HTML `search` and `address` elements instead of leaving them as generic DOM
elements only.

Search regions report landmark/search text, descendant form metadata, normalized
form methods, and searchable control summaries with labels, names, types, and
values. Address blocks report contact text plus contact link rollups, including
`rel` tokens, all hrefs, and mailto hrefs.

Direct-format parity accounting is not affected; this is XML/HTML5 DOM review
metadata coverage only.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 1313 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69980 assertions, 0 failures`

No Pandoc binary, browser renderers, online sanitizers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.
