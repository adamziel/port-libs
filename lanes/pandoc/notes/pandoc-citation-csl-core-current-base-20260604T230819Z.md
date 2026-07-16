# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260604T230819Z`

Accepted base: `fbe3fc8556507be78718a50156c3db0ac6373d94`

## Behavior

- Added `CslStyle`, a bounded native PHP parser for CSL 1.0 style XML and
  locale XML term data using `DOMDocument` with `LIBXML_NONET`.
- `CitationCslProcessor::withCslStyle()` now applies citation `layout`
  prefix/suffix/delimiter settings plus localized `and`, `et-al`, and
  `no date` terms to the existing author-date citation handoff.
- Preserves accepted default citation rendering when no CSL style XML is
  supplied.
- Keeps missing citation keys visible in Markdown and WordPress output after
  style layout is applied.
- Rejects non-style roots, missing citation layouts, invalid XML, and locale
  XML outside the CSL namespace before processing.
- Updated the WordPress citation handoff smoke to prove style-localized
  no-date and et-al rendering without invoking citeproc.

## Source Truth

- Upstream Pandoc runner parity is still unavailable in this isolated
  worktree; no hydrated Pandoc/citeproc checkout or Cabal project is present.
- Source truth for this bounded slice is the CSL 1.0.2 specification at
  `https://docs.citationstyles.org/en/v1.0.2/specification.html`: CSL is
  style/locale XML, independent styles contain `citation` and optional
  `bibliography` elements, `citation` requires a `layout`, `layout` can carry
  citation delimiters/affixes, and locale files/style locales provide terms
  such as `and`, `et-al`, and `no date`.
- This does not attempt CSL macro evaluation, full rendering-element
  evaluation, sort keys, disambiguation, note-style citeproc behavior, external
  style catalogs, locale date formats, or full citeproc parity.

## Evidence

- Red-first check after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `Call to undefined method
  PortLibs\Pandoc\CitationCslProcessor::withCslStyle()`.
- `php -l lanes/pandoc/src/CslStyle.php && php -l lanes/pandoc/src/CitationCslProcessor.php && php -l lanes/pandoc/tests/CitationCslProcessorTest.php && php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
  1 selected test file, 162 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`:
  13 selected test files, 3,715 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`:
  `wordpress-citation-csl-handoff self-test passed`.
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`:
  both files valid.
- `git diff --check -- lanes/pandoc`:
  no whitespace errors.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, BibTeX/BibLaTeX parsing, bracketed citation cluster
parsing, missing citation preservation, EPUB3 package handoff, DOCX/ODT
package parsing, table geometry, ZIP/OPC package primitives, doctemplate,
YAML, archive compression, math/TeX, legacy DOC/CFB, charset helpers, PDF
handoff planning, or upstream-runner dependency audit work.

## Dependency Closure

No external dependency is needed. This adds one small native PHP support
component, `CslStyle`, and reuses the existing `CitationCslProcessor`,
Markdown reader/writer, and WordPress block writer. Remaining citation
closure is bounded follow-up work: crossref inheritance, broader BibLaTeX
entry families, TeX accent decoding, full CSL macro/text/date/name rendering,
external style catalogs, citation-position disambiguation, note-style output,
and full upstream runner hydration.
