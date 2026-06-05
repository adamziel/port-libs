# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T001058Z`

Accepted base: `6bec98d94dbbe199ef28ef1cbb9570c1704ad71f`

## Behavior

- Extended bounded native `CslStyle` parsing to preserve CSL `bibliography`
  layout `prefix`, `suffix`, and `delimiter` attributes.
- Captures bounded bibliography options: `hanging-indent`, `entry-spacing`,
  `line-spacing`, and `second-field-align` for downstream reviewer metadata.
- `CitationCslProcessor::renderBibliographyEntry()` now applies the CSL
  bibliography layout affixes and delimiter to generated bibliography entries.
- Bibliography source-access dates now use the localized CSL `accessed` term
  instead of hardcoded English `Accessed`.
- Updated the WordPress citation handoff smoke so reviewer bibliographies show
  localized accessed wording and layout-wrapped entries without invoking
  citeproc, Pandoc, BibTeX, Biber, Haskell runners, online services, or
  bibliography managers.

## Source Truth

- Upstream Pandoc runner parity is still unavailable in this isolated worktree;
  no hydrated Pandoc/citeproc checkout or Cabal project is present.
- Source truth for this bounded slice is the CSL 1.0.2 style model already used
  by the lane: independent styles carry `citation` and optional `bibliography`
  elements, both use `layout` elements with affixes/delimiters, `bibliography`
  carries formatting options, and locale/style terms include `accessed`.
- This does not attempt CSL macro expansion, rendering-element evaluation,
  sort/disambiguation, note-style citeproc output, locale date formatting,
  external style catalogs, or full citeproc parity.

## Evidence

- Red-first check after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 181 assertions, 1 failures`; the missing behavior
  was `bibliographyLayout` in `CitationCslProcessor::cslStyleSummary()`.
- `php -l lanes/pandoc/src/CslStyle.php && php -l lanes/pandoc/src/CitationCslProcessor.php && php -l lanes/pandoc/tests/CitationCslProcessorTest.php && php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
  1 selected test file, 199 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`:
  19 selected test files, 4,486 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`:
  `wordpress-citation-csl-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access date
storage, name particles/suffixes, BibTeX/BibLaTeX parsing, BibTeX crossref
inheritance, bracketed citation cluster parsing, citation layout localization,
missing citation preservation, DOCX/ODT/EPUB package parsing, table geometry,
ZIP/OPC package primitives, doctemplate, YAML, archive compression, math/TeX,
legacy DOC/CFB, charset helpers, PDF handoff planning, or upstream-runner
dependency audit work.

## Dependency Closure

No external dependency is needed. This reuses the existing native PHP CSL style
parser, citation processor, Markdown reader/writer, and WordPress block writer.
Remaining citation closure is bounded follow-up work: full CSL macro/text/date/
name rendering, bibliography sorting, disambiguation, citation-position logic,
note-style output, external style catalogs, locale date formats, and upstream
runner hydration.
