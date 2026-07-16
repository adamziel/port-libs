# Pandoc Doctemplates Core Current-Base Slice 2026-06-08T230654Z

## Scope

- Implemented bounded doctemplate partial breakable-space inheritance in native
  PHP `DocTemplate`.
- Directive tokens now retain whether they were read while `$~$` breakable
  spaces were active, and partial validation/rendering tokenizes included
  partials with that same starting state.
- This lets wrapped review packets reflow literal spaces that live inside
  included partial templates, while `/nowrap` partial pipes still suppress
  wrapping for that partial output.

## Source Truth

- Upstream doctemplates `Parser.hs` keeps `breakingSpaces` in parser state.
  `pReflowToggle` toggles it, `pLit` converts literal text to breakable
  document spaces while it is active, and `pPartial` parses the included
  partial after saving the current parser state without resetting
  `breakingSpaces`.
- Upstream doctemplates `Internal.hs` applies partial pipes after rendering a
  partial, so `/nowrap` can still convert inherited breakable spaces to normal
  spaces.
- Upstream doclayout `DocLayout.hs` renders `BreakingSpace` as either a space
  or line break under bounded line length.

Reference files used:

- https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Parser.hs
- https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Internal.hs
- https://raw.githubusercontent.com/jgm/doclayout/0.5.0.1/src/Text/DocLayout.hs

## Evidence

- Baseline focused command before the patch:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 1076 assertions, 0 failures`.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 1077 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- Added one focused TestRunner PASS case:
  `inherits pandoc doctemplate breakable spaces into partial resources`.
- Lane status updates:
  - `phpPass`: `1953 -> 1954`.
  - mapped denominator: `2374 -> 2375`.
  - `mappedDoctemplatePartialCases`: `4 -> 5`.
  - `doctemplatePartialAssertions`: `5 -> 6`.

## Non-Overlap

This does not repeat accepted doctemplate delimiter whitespace, braced
separator parsing, applied-partial rebinding, default template fallbacks, child
metadata keys, breakable-space wrapping, block pipe reboxing, or partial final
newline stripping. It only carries active breakable-space state into separately
tokenized partial resources and validates/rendered partials under that inherited
state.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`DocTemplate`, the existing partial resource map and validation path, and the
existing Unicode/doclayout-style wrapping helper. Pandoc, Cabal solver/build/
test commands, Haskell runners, external template engines, Word, LibreOffice,
zip/unzip, online services, live provider tests, and live-service provider
tests were not executed.

## Follow-Up

Possible non-overlapping doctemplate follow-ups: source-location parity for
nested partial toggles, remaining default-template drift, or another bounded
Parser/Internal/doclayout edge with focused PHP tests.
