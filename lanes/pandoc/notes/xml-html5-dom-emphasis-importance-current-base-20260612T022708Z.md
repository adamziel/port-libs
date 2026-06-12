# XML/HTML5 DOM emphasis and importance semantics current base 20260612T022708Z

Bead: plib-gtd54

Base before slice: 0dfe5caf66

## Scope

This slice adds bounded HTML text-level semantic summary coverage for `em`,
`strong`, `b`, and `i` elements in `XmlHtmlDom::summarizeHtmlFragment()`.
Those nodes already parsed and serialized as generic elements; they now carry
the same `textSemantic`, `semanticTag`, and `semanticText` reviewer handoff
fields used by the existing abbreviation, definition, code, keyboard, sample,
ruby-adjacent, and bidi text-level semantic paths.

Semantic labels:

- `em`: `stress-emphasis`
- `strong`: `strong-importance`
- `b`: `bring-attention`
- `i`: `idiomatic-offset`

The behavior is additive and does not alter fragment parsing, deterministic
serialization, raw HTML block handoff, active-content policy, or sanitization.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 test file, 1218 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 69086 assertions, 0 failures

No Pandoc, office suite, TeX/PDF engine, browser engine, Node tooling,
external validator, online service, or live provider test was used.

## Non-overlap

This does not repeat the accepted global attributes, inert/custom elements,
input hints, list metadata, outline metadata, text-semantics baseline, ruby,
time/data, break elements, dialog/disclosure, quote/revision, media/link/map,
metadata, table, or foreign-content slices. It only fills the remaining common
inline semantic classification gap for emphasis, importance, and presentational
phrasing elements.
