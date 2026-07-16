# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T042026Z`
- Accepted base: `a4eb702f7ee7d99c8c98d4d754371b79ebaa9e9b`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for ordered-list
continuation:

- Tracks the current `text:list` nesting depth while reading content XML.
- Uses the current list level to select the matching ODF list-style level, so
  nested ordered lists can keep their own `text:start-value` and `style:num-format`.
- Preserves `text:continue-numbering="true"` across sibling lists by carrying
  the next start counter per list level.
- Marks continued lists with `continued=true`, exposes `listLevel` on list AST
  nodes, and reports `importReport.content.continuedListCount`.
- Updates the WordPress ODF handoff smoke so continued review checklist
  numbering survives rendered WordPress blocks.

Source truth: upstream Pandoc
`Text.Pandoc.Readers.ODT.ContentReader` at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` tracks list level and previous
start counters, and reads `text:continue-numbering` when constructing ODT
lists. This PHP slice ports that bounded list-continuation contract without
calling Pandoc or office tooling.

This is bounded to OpenDocument content/styles XML mapping. It does not invoke
Pandoc, LibreOffice, Word, zip/unzip, browser renderers, external template
engines, TeX/PDF engines, Haskell runners, or online services.

## Evidence

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 353 assertions, 0 failures`
- Red-first after adding the continued-list expectation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 358 assertions, 1 failures`
  - Expected failure: `listLevel` / continuation metadata was absent.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 380 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `614 -> 615`.
- `benchmarkDenominator.mapped`: `1088 -> 1089`.
- Focused `OdfReaderTest.php`: `14 -> 15` cases, `353 -> 380` assertions.
- ODF manifest subcounters now reflect the current focused ODF file:
  `15` mapped cases / `380` assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` components. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ODT-local continued-list parsing is not blocked by that runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/manifest/media/table/list
restart/annotation/text-box/image presence, footnote/endnote,
bookmark-reference, reference-mark/reference-ref, sequence, tracked-change,
encrypted-manifest, MathML object, linked/protected section, page-layout/
master-page, and image-dimension clusters. It adds only bounded OpenDocument
`text:continue-numbering` and nested list-level start handoff.

Remaining ODT follow-up stays separate: forms, charts, richer style cascades,
embedded-object preview policy beyond MathML, table continuation semantics,
export-side ODT writing, inherited parent list-style fallback for style-less
nested lists, and full Pandoc ODT reader parity.
