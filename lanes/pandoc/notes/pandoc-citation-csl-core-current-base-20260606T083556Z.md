# Pandoc Citation/CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260606T083556Z`

Base accepted HEAD: `3d936b88c8478a24a1c25b0972efd5d6a8b2a3d9`

## Behavior

- Added bounded native CSL exact-locale precedence for style-local `<locale>`
  elements.
- `CslStyle` now applies unqualified locales first, language fallback locales
  such as `en` next, and exact default-locale matches such as `en-US` last.
  This prevents a later generic locale from overriding an earlier exact locale
  for terms like `and`, `accessed`, and `no date`.
- Added a WordPress Citation/CSL handoff example showing exact locale terms in
  citation labels and bibliography entries without invoking external citation
  tooling.

## Source Truth

This is a bounded native PHP support-library slice for CSL locale inheritance:
exact locale matches are more specific than language fallback locales. It does
not implement full citeproc locale resolution, external locale catalog loading,
abbreviation lists, note-style output, or full Pandoc/citeproc runner parity.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, online sanitizer, online service, or
live provider test was executed.

## Evidence

- No current Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1599 assertions, 0 failures`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1608 assertions, 0 failures`.
  - Focused delta: `+1` PASS case and `+9` assertions.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-citation-csl-locale-precedence-handoff.php --self-test`
  - Result: `wordpress-citation-csl-locale-precedence-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1253 -> 1254`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`:
  `1697 -> 1698`.
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`:
  `10 -> 11`.
- Focused `CitationCslProcessorTest.php`: `1599 -> 1608` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `CslStyle`,
`CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, and
lane-local manifest/status machinery.

Full upstream Pandoc runner parity remains gated on hydrating the pinned
Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
package/project files and Haskell Tasty executables for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, date-part/date-form rendering, text/name forms, macros,
variable/type/position/is-numeric/is-uncertain-date conditionals, approximate
date handling, locator/page labels, number rendering, punctuation-in-quote,
day-ordinal locale options, et-al handling, delimiter-precedes-last,
subsequent-author substitution, year-suffix/collapse, compact family-given
script ordering, BibTeX/BibLaTeX parsing, PDF, DOCX, ODT, YAML, doctemplate,
table-geometry, archive, ZIP/OPC, charset, syntax-highlighting, or
upstream-runner dependency audit surfaces.

Follow-up CSL work should keep broader external locale selection,
style/locale option inheritance beyond exact language fallback, localized
ordinal/gender variants, note-style output, citation-position disambiguation,
abbreviation-list lookup, bibliography disambiguation, and full citeproc/Pandoc
runner parity as separate bounded slices.
