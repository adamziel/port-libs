# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T103810Z`

Accepted base: `7f71cfc6116b03249ff3e806369e892ec5de9b31`

## Behavior

- Added bounded native CSL `cs:date form="text"` and `form="numeric"` parsing
  and rendering for date elements without explicit `date-part` children.
- `CslStyle` now validates date form values and preserves them in the style
  summary for citation and bibliography handoff.
- `CitationCslProcessor` now renders bounded English text forms such as
  `March 9, 2027` and numeric forms such as `3/10/2027`, including simple
  slash-delimited ranges, while existing explicit `date-part` rendering keeps
  precedence.
- The WordPress citation CSL handoff smoke now exposes localized date-form
  bibliography output for reviewer calendars without invoking Pandoc, citeproc,
  bibliography managers, or online services.

## Source Truth

- The accepted 2026-06-05 date-part lane note recorded CSL 1.0.2 date support
  and explicitly left localized `cs:date form="text|numeric"` lookup as a
  follow-up.
- This slice is intentionally bounded native PHP support. It does not implement
  full locale date catalogs, `limit-day-ordinals-to-day-1`, rich date range
  collapsing, note-style citeproc output, or full citeproc parity.

## Evidence

- Baseline before this slice:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 998 assertions, 0 failures`.
- Red-first after adding the focused date-form expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1000 assertions, 1 failures`; the style summary did
    not preserve the `form` attribute on `cs:date`.
- After implementation:
  - `php -l lanes/pandoc/src/CslStyle.php`: no syntax errors.
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`: no
    syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
    `1 test files, 1011 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`:
    `wordpress-citation-csl-handoff self-test passed`.
  - JSON validation for `lanes/pandoc/lane-status.json` and
    `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: both decoded with
    `JSON_THROW_ON_ERROR`.
  - `git diff --check -- lanes/pandoc`: clean.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `840` -> `841`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `1299` -> `1300`.
- `mappedCitationCslCoreCases`: `10` -> `11`.
- Focused citation test movement: `48` -> `49` PASS cases and
  `998` -> `1011` assertions.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, BibTeX/BibLaTeX parsing, crossref/xdata/set/related/
translation/legal/date-range/title/publication-detail/role metadata, bracketed
citation cluster parsing, missing citation preservation, CSL locale term
loading, bibliography layout affixes, sort keys, name rendering options,
explicit `date-part` rendering, direct text/group/names rendering, text-case
transforms, macro references, choose conditionals, locator/page label
rendering, number rendering, citation position conditionals, quotes/
strip-periods, `cs:name-part` formatting, subsequent-author substitution,
year-suffix/collapse behavior, DOCX/ODT/EPUB package parsing, table geometry,
ZIP/OPC package primitives, doctemplate, YAML, archive compression, math/TeX,
legacy DOC/CFB, charset helpers, PDF handoff planning, or upstream-runner
dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter`. Full upstream Pandoc/citeproc runner parity remains
gated on hydrating the pinned Pandoc checkout and Cabal package/project
metadata; no external runner or bibliography tool was executed.
