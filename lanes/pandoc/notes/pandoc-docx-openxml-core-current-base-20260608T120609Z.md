# DOCX OpenXML Run-Effect Metadata Slice

- Micro-slice: `pandoc-docx-openxml-core-current-base-20260608T120609Z`
- Accepted base: `5f425da1740b76fd38a51b6ce59a09edd9c388d7`
- Scope: native DOCX/OpenXML `w:rPr` run-effect metadata handoff for visible DOCX body text.

## Behavior

`DocxReader` now preserves bounded WordprocessingML run effects as inert reviewer metadata spans:

- Boolean effects: `w:vanish`, `w:webHidden`, `w:specVanish`, `w:caps`, `w:outline`, `w:shadow`, `w:emboss`, and `w:imprint`.
- Valued effects: `w:em` emphasis marks and `w:effect` text effects.
- Disabled values such as `0`, `false`, `off`, and `none` remain plain and also override inherited metadata families.

The text remains visible in the AST, Markdown writer, and WordPress block writer. The slice only adds `docx-run-effect` classes and safe `data-docx-*` attributes for reviewer handoff.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 2553 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 2585 assertions, 0 failures`.
- Added one named TestRunner PASS case and 32 focused assertions.
- Updated WordPress smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`.

## Dependency Closure

No new support component is needed. This reuses the existing native DOCX package reader, DOM parsing, AST span metadata, Markdown writer attribute emission, and WordPress block writer safe `data-docx-*` pass-through. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat the accepted DOCX tracked formatting-change, run language/RTL, paragraph border, structured document tag form-control, embedded object/package, deleted OMML math revision, or ODF/OpenDocument subtotal/drop-down/hidden-field slices. It is limited to WordprocessingML run-effect metadata on existing visible run text.
