# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260604T221246Z`

Accepted base: `3b64d900f18785e772f5dafe77ed00b17c3cd341`

## Behavior

- Added bounded native BibTeX/BibLaTeX parsing into the existing
  `CitationCslProcessor` handoff with `fromBibtex()` and `bibtexItems()`.
- Maps common entry types and field aliases into CSL JSON-like items:
  `article` to `article-journal`, `online` to `webpage`, `journaltitle` /
  `journal` / `booktitle` to `container-title`, `pages` to `page`, `doi` to
  `DOI`, `url` to `URL`, and `urldate` to `accessed`.
- Supports braced, quoted, and bare values; `@string` abbreviations; `#`
  concatenation; standard month macros; year/month/day fields; ISO-like
  `date` / `urldate`; literal date values such as `forthcoming`; page-range
  dash normalization; and `@comment` / `@preamble` skipping.
- Preserves bounded CSL name metadata for comma-form names, lowercase family
  particles, suffixes, and double-braced literal organization authors.
- Keeps malformed BibTeX diagnostics local and deterministic without invoking
  Pandoc, citeproc, BibTeX, Biber, bibliography managers, or online services.
- Added a WordPress import-review smoke example that resolves a `.bib` packet
  into WordPress citation and bibliography blocks while preserving missing
  citation keys for follow-up.

## Source Truth

- The upstream Pandoc runner remains unavailable in this isolated worktree; no
  hydrated Pandoc checkout or citeproc checkout exists under `.upstream-cache`.
- This slice uses the accepted Pandoc lane manifest, prior CSL handoff, and
  Pandoc/citeproc-shaped field conventions as bounded source truth.
- It intentionally does not attempt full citeproc parity, CSL style XML,
  locale terms, crossref inheritance, every BibLaTeX entry family, TeX accent
  decoding, note-style rendering, bibliography sorting/disambiguation, or
  upstream Haskell runner execution.

## Evidence

- Before this slice, `CitationCslProcessorTest.php` had 5 focused cases and
  107 assertions. After this slice it has 7 focused cases and 145 assertions.
- Lane status moves from 382 to 384 PHP PASS cases and from 839 to 841 mapped
  native checks.
- `php -l lanes/pandoc/src/BibtexCslParser.php`:
  no syntax errors.
- `php -l lanes/pandoc/src/CitationCslProcessor.php`:
  no syntax errors.
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`:
  no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
  1 selected test file, 145 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`:
  13 selected test files, 3,669 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`:
  `wordpress-bibtex-csl-handoff self-test passed`.
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`:
  both files valid.
- `git diff --check -- lanes/pandoc`:
  no whitespace errors.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item basics, source-access date/name
metadata, simple author-date citation rendering, bracketed citation cluster
parsing, missing citation preservation, EPUB3 package handoff, DOCX/ODT
package parsing, table geometry, ZIP/OPC package primitives, doctemplate,
YAML, archive compression, math/TeX, legacy DOC/CFB, charset helpers, PDF
handoff planning, or upstream-runner dependency audit work.

## Dependency Closure

No new external dependency is needed. This adds a small native PHP support
component, `BibtexCslParser`, and reuses the existing `CitationCslProcessor`,
Markdown reader/writer, and WordPress block writer. Remaining citation closure
is bounded follow-up work: crossref inheritance, broader BibLaTeX entry
families, TeX accent decoding, CSL style XML/locales, citation-position
disambiguation, note-style output, and full upstream runner hydration.
