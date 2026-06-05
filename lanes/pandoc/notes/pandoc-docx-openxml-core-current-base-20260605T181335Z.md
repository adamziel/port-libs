# DOCX Proof And Permission Ranges

Slice: `pandoc-docx-openxml-core-current-base-20260605T181335Z`
Base accepted HEAD: `9ead64905fb753cca25bfab3c1ec066d02d22a57`

## Behavior

- Added bounded DOCX/OpenXML inline preservation for same-paragraph `w:proofErr` ranges.
- `w:type="spellStart"` / `w:type="spellEnd"` now emits `.docx-proof-error .docx-proof-spelling` reviewer spans with `data-docx-proof-error`, `data-docx-proof-start`, and `data-docx-proof-end`.
- `w:type="gramStart"` / `w:type="gramEnd"` now emits `.docx-proof-error .docx-proof-grammar` reviewer spans with the same provenance attributes.
- Added bounded same-paragraph `w:permStart` / `w:permEnd` preservation for editing permission ranges.
- Group permissions expose `.docx-permission-range .docx-permission-group` plus `data-docx-permission-id` and `data-docx-permission-group`.
- User permissions expose `.docx-permission-range .docx-permission-user` plus `data-docx-permission-id` and `data-docx-permission-user`.
- Markdown and WordPress block output preserve these spans around nested run formatting, including bold permission text.

## Source Truth And Non-Overlap

This maps bounded WordprocessingML proofing and editing-permission range markers into the existing Pandoc-like AST and WordPress handoff path.

This does not overlap accepted package loading, content types, relationships, styles, numbering, tables, media, VML/DrawingML images, chart/diagram placeholders, embedded objects, footnotes, endnotes, comments, comment ranges, note markers, bookmarks, bookmark column ranges, field-code hyperlinks, tracked revisions, moves, content controls, smart tags, custom XML, OMML math, altChunk import, settings, section properties, run language/RTL, paragraph bidi/layout, page/column/rendered page breaks, or document variables.

## Evidence

- Baseline before this slice: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 1382 assertions, 0 failures`.
- Red-first after adding the focused expectations: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` failed with `1 test files, 1385 assertions, 1 failures` because the new proof-error/permission range expectations were not preserved.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 1420 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` passed with `docx body handoff self-test ok`.
- Syntax checks passed:
  - `php -l lanes/pandoc/src/DocxReader.php`
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

Status delta:

- `lane-status.json` `phpPass`: `1031` -> `1032`.
- Manifest mapped checks: `1483` -> `1484`.
- DOCX/OpenXML core cases: `31` -> `32`.
- DOCX/OpenXML core assertions: `313` -> `351`.
- Focused DocxReader coverage: `+1` PASS case / `+38` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`, OPC/XML package helpers, `DocxReader`, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, browser renderer, online sanitizer, online service, or live provider test was executed.

Full upstream runner parity remains gated on a hydrated Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`, `pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`, and `pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present before any non-mutating Cabal solver/build plan.

## Follow-Up

- Cross-paragraph proof-error and permission ranges.
- Nested or overlapping proof/permission range repair.
- Richer edit-permission policy semantics beyond group/user metadata.
- Glossary/theme/drawing text and fuller upstream Haskell runner parity.

Root harness: not run - isolated micro-slice.
