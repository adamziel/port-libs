# XML/HTML5 DOM Color Input Value Review

Bead: `plib-vl9if`
Date: 2026-07-01 UTC
Area: Pandoc XML/HTML5 DOM primitives
Base: `942eaccbd`

## Behavior

`XmlHtmlDom::summarizeHtmlFragment()` now preserves bounded reviewer
provenance for HTML `input[type=color]` controls:

- valid simple color values normalize to lowercase `#rrggbb`;
- missing values expose the HTML color-state default `#000000` with
  `missing-value-default` provenance;
- invalid values such as named colors or shorthand hex expose the same default
  with `invalid-color-input-value` issue metadata;
- parsed RGB components are included for the effective color value; and
- non-color inputs with color-looking text values do not receive color review
  fields.

Deterministic raw HTML serialization and WordPress raw block propagation remain
unchanged. This is additive DOM summary metadata only; it does not execute
browser constraint validation or add direct Pandoc conversion support for color
pickers.

No Pandoc binary, office suite, TeX/browser engine, unzip/zip, Jupyter, Node
tooling, external validator, online service, or live provider test was invoked.

## Direct-Format Parity Accounting

- Direct color-input conversion support: `0 -> 0`
- Mapped XML/HTML5 DOM color input review cases: `0 -> 1`
- Focused color input review assertions: `0 -> 50`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomColorInputReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomColorInputReviewTest.php`
  - `1 test files, 50 assertions, 0 failures`
