# XML/HTML5 DOM Typed Input Value Review

Bead: `plib-jidsn`
Date: 2026-07-01 UTC
Area: Pandoc XML/HTML5 DOM primitives

## Behavior

`XmlHtmlDom::summarizeHtmlFragment()` now preserves bounded reviewer
provenance for typed HTML input controls:

- `input[type=number]` and `input[type=range]` expose parsed finite numeric
  `value`, `min`, `max`, and `step` review fields;
- `input[type=date|month|week|time|datetime-local]` expose type-specific
  normalized `value`, `min`, and `max` fields using the existing bounded HTML
  time-value parser;
- invalid typed values and invalid step tokens stay visible through
  deterministic issue codes; and
- non-typed text inputs with date-like values do not receive typed-input review
  fields.

Existing generic form-control constraint metadata and raw HTML serialization
remain unchanged. This is additive DOM summary metadata only; it does not run
browser constraint validation or add direct Pandoc conversion support for typed
form controls.

No Pandoc binary, office suite, TeX/browser engine, unzip/zip, Jupyter, Node
tooling, external validator, online service, or live provider test was invoked.

## Direct-Format Parity Accounting

- Direct typed-input conversion support: `0 -> 0`
- Mapped XML/HTML5 DOM typed input review cases: `0 -> 1`
- Focused typed input review assertions: `0 -> 95`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTypedInputValueReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTypedInputValueReviewTest.php`
  - `1 test files, 95 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php lanes/pandoc/tests/XmlHtml5Dom*.php lanes/pandoc/tests/Html5Dom*.php`
  - `50 test files, 11216 assertions, 0 failures`
