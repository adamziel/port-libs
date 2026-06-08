# DOCX/OpenXML glossary-local relationships

Slice: `pandoc-docx-openxml-core-current-base-20260608T195004Z`
Base: `e33874b6a59046b0ea8a8d0d93a0e5bb2e4b1b0b`

## Behavior

- `DocxReader` now reports the local relationship part for `word/glossary/document.xml` when present.
- The glossary metadata/import report includes `relationshipsPart`, `relationshipCount`, and a compact relationship target summary from `word/glossary/_rels/document.xml.rels`.
- Each glossary `docPart` item now keeps parsed `blocks` alongside the existing `blockCount` and plain `text`, so reusable-building-block hyperlinks and images remain inspectable by downstream review code.
- The WordPress DOCX handoff example exercises a glossary-local hyperlink plus internal glossary image and verifies the parsed AST node metadata.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 2863 assertions, 0 failures`.
- Red-first: the same focused test command failed with `1 test files, 2865 assertions, 1 failures` because `relationshipsPart` was absent.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 2908 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` passed with `docx body handoff self-test ok`.

## Dependency Closure

No new native support component is needed. This reuses the existing OPC relationship graph, package path/content-type resolution, and bounded DOCX body parsing helpers. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted DOCX body/properties/styles/numbering/media/settings/altChunk/embedded-object/SDT/tracked-formatting/deleted-OMML/paragraph-border work. It closes the separate glossary-local relationship follow-up for reusable building blocks.

## Next

Good non-overlapping DOCX/OpenXML follow-ups are richer theme font inheritance, SEQ/caption numbering heuristics, or DrawingML shape geometry metadata.
