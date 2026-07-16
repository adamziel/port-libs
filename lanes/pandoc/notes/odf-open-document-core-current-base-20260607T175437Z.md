# ODF/OpenDocument Note Citation Inline Marker Slice

Micro-slice: `pandoc-odf-open-document-core-current-base-20260607T175437Z`
Base accepted HEAD: `7b9b1411390f43a39859513807812af16800e961`

## Behavior

Mapped one bounded ODF/OpenDocument ContentReader-style note-citation normalization case. `text:note-citation` now reuses the existing ODF inline normalization path, so generated spaces from `text:s`, tab stops from `text:tab`, and hard line breaks from `text:line-break` survive in the note citation metadata instead of being collapsed by DOM `textContent`.

This is distinct from the already accepted ODF `text:tab` paragraph normalization, heading auto/source-id anchors, conditional/hidden text fields, and notes-configuration metadata slices. WordPress and Markdown footnote numbering remain automatic; the source ODT citation is preserved as review metadata on the AST note node.

## Evidence

- Baseline focused command: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result before new case: `1 test files, 1555 assertions, 0 failures`
- Red-first command after adding the focused case and before the implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Expected `F  7 b\ncontinued`; actual `F7bcontinued`
  - Result: `1 test files, 1558 assertions, 1 failures`
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 1563 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-odf-note-citation-handoff.php --self-test`
  - Result: `odf note citation handoff self-test ok`

## Dependency Closure

No new support component is needed. This slice reuses native `ZipPackage`, `OdfReader` inline parsing, `MarkdownWriter`, and `WordPressBlockWriter` behavior. Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external converters, online services, live provider tests, and live-service provider tests were not executed.

## Next Task

For ODF/OpenDocument follow-up, choose a non-overlapping native ContentReader gap such as additional note/table/index metadata, style-derived list or paragraph behavior not already mapped, or object/frame metadata not covered by the current image/chart/OLE slices.
