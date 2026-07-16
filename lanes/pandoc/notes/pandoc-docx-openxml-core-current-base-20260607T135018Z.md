# pandoc-docx-openxml-core-current-base-20260607T135018Z

Base accepted HEAD: `0f6a827583ed4cd322d9cb5476a5c5b23c62d765`

## Behavior

- Added bounded DOCX/OpenXML table-cell vertical-alignment handling for `w:tcPr/w:vAlign`.
- Maps `top`, `center`, and `bottom` into table-cell AST classes, `data-docx-cell-vertical-align`, HTML `valign` metadata, and `tableGeometry` coverage.
- Keeps unsupported values inert so malformed or unknown alignment tokens do not leak into Markdown or WordPress output.
- Updated the DOCX body WordPress handoff example so the reviewer table exercises the `center` to `middle` handoff path.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 2041 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` failed as expected with `1 test files, 2043 assertions, 1 failures` before implementation.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 2068 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` passed with `docx body handoff self-test ok`.

## Mapping

- `phpPass`: `1508 -> 1509`.
- `benchmarkDenominator.mapped`: `1927 -> 1928`.
- `docxOpenXmlCoreCases`: `33 -> 34`.
- `docxOpenXmlCoreAssertions`: `385 -> 412`.

## Non-Overlap

This slice stays inside DOCX/OpenXML table-cell metadata. It does not repeat accepted DOCX run language, tracked formatting-change, embedded object/package, deleted OMML revision, SDT form-control, or table span/merge geometry work.

## Dependency Closure

No new support component is needed. The patch reuses native `DocxReader`, table AST/table-geometry handoff, `MarkdownWriter`, `WordPressBlockWriter`, and in-memory `ZipPackage` fixtures. No Pandoc, Word, LibreOffice, zip/unzip, Cabal/Haskell runner, external office tool, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Next DOCX/OpenXML work should stay bounded to non-overlapping WordprocessingML table/body metadata such as table-cell shading, cell width/type hints, or paragraph/table style inheritance needed by WordPress review output.
