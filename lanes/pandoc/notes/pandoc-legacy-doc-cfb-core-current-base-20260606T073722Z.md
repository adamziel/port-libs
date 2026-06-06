# Pandoc Legacy DOC/CFB Current-Base 2026-06-06T07:37:22Z

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T073722Z`
Base accepted HEAD: `10c8faa2bd4e18ec06eb4850c4a30e46d6ded63d`

## Behavior

- Added bounded native PHP extraction for supplemental legacy DOC field
  tables: `PlcfldHdr`, `PlcfldFtn`, and `PlcfldAtn`.
- The reader now parses those tables only after FibRgLw97 subdocument text has
  been extracted, so field CPs are validated against the correct header,
  footnote, or comment story instead of the main document text.
- `LegacyDocReader` exposes `fieldStories` metadata with story/table,
  character-count, field-character-count, and field-count summaries. Combined
  `fieldCharacters` and `fields` keep global indexes and also retain local
  per-story `storyIndex` values.
- Supplemental story field text remains metadata-only. The WordPress handoff
  smoke now includes a header DATE field and asserts that neither its
  instruction nor result renders into blocks.

Source truth: Microsoft MS-DOC FibRgFcLcb97 documents `fcPlcfFldHdr`,
`fcPlcfFldFtn`, and `fcPlcfFldAtn` as Table Stream offsets for Plcfld tables
whose CPs are relative to the Header, Footnote, and Comment documents:
https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/0c9df81f-98d0-454e-ad84-b612cd05b1a4

## Verification

Baseline before the slice:

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 751 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - `legacy doc handoff self-test ok`

After implementation:

- `php -l lanes/pandoc/src/LegacyDocReader.php`
  - `No syntax errors detected`
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `No syntax errors detected`
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 788 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - `legacy doc handoff self-test ok`

Assertion delta: +37 focused assertions. PASS delta: +1 focused PHP PASS case.
Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
Compound File Binary parser, LegacyDocReader FIB/CLX/FibRgLw97 subdocument
extraction, Plcfld metadata parser, WordPressBlockWriter, and lane-local
manifest/status machinery.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external office tool, browser renderer, online
sanitizer, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap And Follow-Up

This slice does not repeat accepted CFB directory validation, OLE property
metadata, FibRgLw97 subdocument trimming, note/comment PLC references, style,
formatting, list, bookmark, ObjectPool, macro, SttbfAssoc, or main-document
`PlcfldMom` rendering work. It owns only supplemental story field-table
metadata.

Follow-up remains bounded: header/footer text range PLCs beyond the already
extracted header story, endnote field tables (`PlcfldEdn`), textbox field
tables, and richer field instruction/result metadata for supplemental stories
can be separate slices.
