# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T062740Z`
- Accepted base: `5f8b8c0d546a115699c0adf82d3e94d711c1439a`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for inherited nested
list styles:

- Tracks the nearest explicit ODT `text:list-style` name while reading nested
  `text:list` content.
- Uses that parent list style when a child `text:list` omits
  `text:style-name`, preserving the child level's ODF numbering definition.
- Marks inherited style use as `inheritedStyleName` on the nested list AST node
  without changing explicit `styleName` metadata.
- Updates the WordPress ODF handoff smoke so a styleless nested review checklist
  renders as the inherited lower-alpha ordered list instead of a default bullet
  list.

Source truth: upstream Pandoc
`Text.Pandoc.Readers.ODT.ContentReader` at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` tracks a current list style and
falls back to it when a list has no explicit style name:
https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/ODT/ContentReader.hs

This is bounded to OpenDocument content/styles XML mapping. It does not invoke
Pandoc, LibreOffice, Word, zip/unzip, browser renderers, external template
engines, TeX/PDF engines, Haskell runners, or online services.

## Evidence

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 457 assertions, 0 failures`
- Red-first after adding the styleless nested-list expectation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 461 assertions, 1 failures`
  - Expected failure: the nested list was emitted as `bullet_list`.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 470 assertions, 0 failures`
- Full focused lane tests:
  `php tools/run-tests.php lanes/pandoc/tests`
  - `20 test files, 8044 assertions, 0 failures`
  - PASS-line count: `687`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: local focused lane PASS-line count is now `687`; this slice adds
  one ODF PASS case.
- `benchmarkDenominator.mapped`: `1165 -> 1166`.
- Focused `OdfReaderTest.php`: `19 -> 20` cases, `457 -> 470` assertions.
- ODF manifest subcounters now reflect the current focused ODF file:
  `20` mapped cases / `470` assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` components. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ODT-local inherited nested list-style parsing is not blocked by
that runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/manifest/media/table/list
restart/list continuation/annotation/text-box/image, footnote/endnote,
bookmark-reference, reference-mark/reference-ref, sequence, field,
bibliography-mark, tracked-change, encrypted-manifest, MathML object,
linked/protected section, page-layout/master-page, image-dimension, and
annotation-range clusters. It adds only bounded OpenDocument inherited
parent list-style fallback for styleless nested lists.

Remaining ODT follow-up stays separate: forms, charts, richer style cascades,
embedded-object preview policy beyond MathML, table continuation semantics,
export-side ODT writing, and full Pandoc ODT reader parity.
