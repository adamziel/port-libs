# Legacy DOC CFB Core Current-Base FFData Linkage

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T073202Z`

Base accepted HEAD: `df259aa2eedc94083122c4983a2ea922c64e663c`

## Behavior

- Added native legacy DOC form-field linkage from Plcfld form fields to CHPX `sprmCPicLocation`/`sprmCFData` metadata and the CFB `Data` stream.
- Decodes the exact bounded FFData record prefix at the referenced Data-stream offset, then exposes decoded field name/default/help/status/macro metadata through `formFieldDataReferences` and WordPress span attributes.
- Keeps raw Data-stream bytes opaque (`canExposeBytes=false`) and marks macro names with `disabled-native-review`; no macro execution or OLE/image byte extraction is attempted.
- Rejects mismatched Plcfld field type versus FFData type and CHPX form-field references whose `Data` stream is missing.

## Evidence

- `php -l lanes/pandoc/src/LegacyDocReader.php`
  - `No syntax errors detected in lanes/pandoc/src/LegacyDocReader.php`
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-form-field-data-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-form-field-data-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 2453 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-legacy-doc-form-field-data-handoff.php --self-test`
  - `legacy doc form-field-data handoff self-test ok`

## Delta

- Added 1 focused PHP PASS case.
- Added 54 focused assertions in `LegacyDocReaderTest.php`.
- Increased mapped legacy DOC/CFB core inventory from 7 to 8 cases.

## Dependency Closure

No new support component is needed. This reuses existing native CFB parsing, FIB table-stream offsets, CHPX FKP parsing, Plcfld field-table parsing, Data stream lookup, and strict FFData decoding. Broader legacy Word form semantics, macro execution, OLE object byte extraction, OCR/model work, Word/LibreOffice automation, and Pandoc/Haskell runner parity remain out of scope for this micro-slice.

## Non-Overlap

This does not repeat the accepted inline-picture CHPX Data-stream metadata slice, OLE ObjectPool reporting, standalone FFData decoding, Plcfld field-table reporting, CHPX/PAPX formatting metadata, mail-merge/Data fields, source fields, built-in information fields, list formatting, ZIP package name-collision preflight, DOCX/OpenXML package work, EPUB/ODT package work, or any external converter/office-tool behavior.
