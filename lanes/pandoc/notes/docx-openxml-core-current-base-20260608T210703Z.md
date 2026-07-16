# DOCX/OpenXML Current-Base Repeating-Section SDT

## Behavior

Mapped one bounded DOCX/OpenXML support case on accepted base `abc313637c76f7f217fa1dc23516e40d06807602`: `DocxReader` now preserves Word 2013 `w15:repeatingSection` and `w15:repeatingSectionItem` structured document tag metadata as reviewer-visible content-control wrappers while keeping nested table and paragraph content in the AST.

The implementation follows the Open XML WordprocessingML extension contract: Microsoft documents `SdtRepeatedSection` as `w15:repeatingSection` with `sectionTitle` and `doNotAllowInsertDeleteSection` child metadata, and `SdtRepeatedSectionItem` as `w15:repeatingSectionItem`.

Source truth:

- https://learn.microsoft.com/en-us/dotnet/api/documentformat.openxml.office2013.word.sdtrepeatedsection?view=openxml-3.0.1
- https://learn.microsoft.com/en-us/dotnet/api/documentformat.openxml.office2013.word.sdtrepeatedsectionitem?view=openxml-3.0.1

## Evidence

- Focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3137 assertions, 0 failures`.
- PHP lint: `php -l lanes/pandoc/src/DocxReader.php`, `php -l lanes/pandoc/tests/DocxReaderTest.php`, and `php -l lanes/pandoc/examples/wordpress-docx-repeating-section-handoff.php` all passed.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-repeating-section-handoff.php --self-test` passed with `wordpress-docx-repeating-section-handoff self-test passed`.
- Expected lane movement: one additional DOCX/OpenXML TestRunner PASS case.

## Non-Overlap

This does not repeat recent SDT form controls, glossary/docPart controls, data/legacy fields, settings, subdocuments, embedded objects, altChunk, drawing/table/run/paragraph metadata, comments/notes, or OPC relationship preflights. The behavior is limited to Word 2013 repeating-section SDT metadata and visible nested content handoff.

## Dependency Closure

No new support component is needed. The slice reuses native `DocxReader`, `ZipPackage`, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter`. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was run.

## Follow-Up

Possible next DOCX/OpenXML work: richer content-control data-binding audits, additional drawing/textbox geometry handoff, or numbering/style inheritance edges.
