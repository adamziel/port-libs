# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T110828Z`

Accepted base: `0147d7cd16fbde22482892e48538f86512fde76c`

## Behavior

- Added bounded native CSL locale `style-options` parsing for
  `punctuation-in-quote`.
- `CslStyle` now preserves the parsed `punctuationInQuote` locale option in
  its style summary and validates the option as `true` or `false`.
- `CitationCslProcessor` now moves adjoining comma and period punctuation
  inside quote-wrapped CSL text at suffix and delimiter boundaries when the
  locale option is enabled.
- Added a WordPress handoff smoke that imports Markdown citations, renders a
  CSL bibliography, and verifies localized punctuation placement in the
  definition-list bibliography output.

## Source Truth

- CSL 1.0.2 defines `punctuation-in-quote` as a locale/style option for
  comma/period placement around quotes:
  https://docs.citationstyles.org/en/v1.0.2/specification.html
- This slice is intentionally bounded native PHP support. It does not implement
  full citeproc locale inheritance, note-style near-note behavior, external
  bibliography managers, BibTeX/Biber, Pandoc, Cabal, Haskell runners, Word,
  LibreOffice, TeX/PDF engines, browser renderers, or online services.

## Evidence

- Baseline before this slice:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1033 assertions, 0 failures`.
- Red/feedback after adding the focused expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1040 assertions, 1 failures`; the renderer still
    left the expected quote-punctuation boundary unhandled in one Markdown
    snippet assertion.
- After implementation:
  - `php -l lanes/pandoc/src/CslStyle.php`: no syntax errors.
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`: no syntax
    errors.
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-punctuation-handoff.php`:
    no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
    `1 test files, 1044 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-citation-csl-punctuation-handoff.php --self-test`:
    `wordpress-citation-csl-punctuation-handoff self-test passed`.
  - JSON validation for `lanes/pandoc/lane-status.json` and
    `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: both decoded with
    `JSON_THROW_ON_ERROR`.
  - `git diff --check -- lanes/pandoc`: clean.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `855` -> `856`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `1313` -> `1314`.
- `mappedCitationCslCoreCases`: `10` -> `11`.
- Focused citation test movement: `50` -> `51` PASS cases and
  `1033` -> `1044` assertions.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, BibTeX/BibLaTeX parsing, crossref/xdata/set/related/
translation/legal/date-range/title/publication-detail/role metadata, bracketed
citation cluster parsing, missing citation preservation, CSL locale term
loading, bibliography layout affixes, sort keys, name rendering options,
explicit `date-part` rendering, `cs:date form` rendering, direct text/group/
names rendering, text-case transforms, macro references, choose conditionals,
locator/page label rendering, number rendering, citation position conditionals,
quotes/strip-periods without `punctuation-in-quote`, `cs:name-part`
formatting, subsequent-author substitution, year-suffix/collapse behavior,
DOCX/ODT/EPUB package parsing, table geometry, ZIP/OPC package primitives,
doctemplate, YAML, archive compression, math/TeX, legacy DOC/CFB, charset
helpers, PDF handoff planning, or upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter`. Full upstream Pandoc/citeproc runner parity remains
gated on hydrating the pinned Pandoc checkout and Cabal package/project
metadata; no external runner or bibliography tool was executed.
