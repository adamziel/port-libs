# Citation CSL Core Current Base - Display Parts

Date: 2026-06-05 UTC

Slice: `pandoc-citation-csl-core-current-base-20260605T071809Z`

Base accepted HEAD: `8bd450f4c90e33ec348f2657c7aae831ed20a4df`

## Source Truth

Bounded CSL 1.0 style behavior: bibliography rendering elements may carry
`display="block"`, `display="left-margin"`, `display="right-inline"`, or
`display="indent"`, and styles commonly pair this with
`second-field-align="flush"` for numbered or second-field bibliographies. This
slice implements the native PHP handoff contract needed by WordPress import
review packets without invoking citeproc, Pandoc, BibTeX, Biber, Haskell
runners, browser renderers, online services, or external converters.

## Implementation

- `CslStyle` now parses and validates `display` on bounded CSL rendering
  elements (`group`, `text`, `date`, `number`, `names`, and `label`) and
  exposes the value in style summaries.
- `CitationCslProcessor` now attaches rendered bibliography display parts to
  CSL definition-list items while preserving the existing flattened text entry
  for Markdown and non-display consumers.
- `WordPressBlockWriter` now renders those display parts as escaped
  `csl-left-margin`, `csl-right-inline`, `csl-indent`, and `csl-block`
  containers inside the CSL bibliography `<dd>`.
- Added `wordpress-citation-csl-display-handoff.php` to smoke-test a
  WordPress review packet with second-field CSL display output.

## Verification

Red-first check after adding expectations:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 761 assertions, 1 failures` on missing parsed `display`
metadata.

Focused green check:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 775 assertions, 0 failures`.

Additional verification commands are recorded in the final handoff:

- `php -l` for changed PHP files.
- `php lanes/pandoc/examples/wordpress-citation-csl-display-handoff.php --self-test`.
- JSON validation for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `739 -> 740`.
- `benchmarkDenominator.mapped`: `1198 -> 1199`.
- `mappedCitationCslCoreCases`: `10 -> 11`.
- Focused Citation/CSL assertions: `758 -> 775`.

## Dependency Closure

No new support component is needed. This reuses the existing native CSL style
XML parser, citation processor, Markdown reader/writer, and WordPress block
writer. Full citeproc parity, style catalogs, bibliography IDs, disambiguation,
collapse, and note-style output remain separate bounded CSL follow-ups.

## Non-Overlap

This slice does not repeat accepted CSL name substitution, date-part forms,
locator/page labels, number rendering, citation-position conditionals,
BibTeX/BibLaTeX metadata mapping, ZIP/OPC, DOCX/ODT/EPUB, archive compression,
table geometry, math/TeX, PDF-engine, legacy DOC/CFB, charset/Unicode,
syntax-highlighting, or XML/HTML5 DOM work. It only maps the bounded CSL
bibliography display/second-field output branch.
