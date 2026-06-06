# DOCX OpenXML Special Note Separator Slice

Session: `port-dev-pandoc-docx-openxml-20260606T010837Z`
Micro-slice: `pandoc-docx-openxml-core-current-base-20260606T010837Z`
Base accepted HEAD: `cf4b5a94f87aa553cb264969d6be0b6e867277bb`

## Behavior

Implemented bounded native PHP DOCX/OpenXML import-report handling for reserved footnote/endnote special notes:

- `w:type="separator"` and reserved id `-1`
- `w:type="continuationSeparator"` and reserved id `-2`
- `w:type="continuationNotice"` and reserved id `-3`

`DocxReader` now reports these records under `importReport.notes.specialNotes` with source type, id, normalized type, source part, marker elements, source block count, and text. They remain excluded from rendered AST note bodies, Markdown output, and WordPress footnote lists.

## Evidence

Red-first focused test:

`php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`

Result before implementation: `1 test files, 1618 assertions, 1 failures`; the new separator test failed because `importReport.notes.specialNotes` was absent.

Final focused verification:

- `php -l lanes/pandoc/src/DocxReader.php`: no syntax errors
- `php -l lanes/pandoc/tests/DocxReaderTest.php`: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`: `1 test files, 1638 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`: `docx body handoff self-test ok`

Status delta:

- `lane-status.json` `phpPass`: `1132 -> 1133`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1584 -> 1585`
- DOCX/OpenXML core cases: `32 -> 33`
- Focused DOCX separator assertions added: `27`

## Non-Overlap

This does not repeat accepted DOCX body parsing, styles/numbering, tables, comments, bookmarks, fields, section/header/footer metadata, settings, glossary, altChunk, embedded object/package handoff, tracked formatting-change, or rendered note-reference behavior. The slice only adds report-only provenance for reserved note separator/continuation records.

## Dependency Closure

No new support component is needed. The slice reuses native PHP ZIP package parts, OPC relationships, XML loading, `DocxReader` note parsing, Markdown writer, and WordPress block writer paths. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external office tool, browser renderer, online sanitizer, online service, or live provider test was executed.

## Follow-Up

Keep note separator style inheritance, multi-section note numbering restart parity, separator rendering policy variants, and full upstream golden/Pandoc comparison runs as separate bounded slices.
