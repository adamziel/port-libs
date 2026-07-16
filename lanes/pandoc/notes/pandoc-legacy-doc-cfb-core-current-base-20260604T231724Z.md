# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260604T231724Z`

Base accepted HEAD: `fd0f5327abfd3b58715219a1c13c4c8295941253`

## Behavior Added

- Extended bounded OLE property metadata extraction in `LegacyDocReader`:
  - maps `SummaryInformation` FILETIME timestamps for last printed, created,
    and modified dates;
  - maps page, word, and character counts plus application name;
  - maps `PIDSI_DOC_SECURITY` and expands known access-control bits into
    review-friendly names;
  - reads `VT_BOOL` typed values;
  - maps `DocumentSummaryInformation` counters and booleans used by legacy DOC
    review packets, including byte/line/paragraph counts, notes/hidden/media
    counts, dirty links, shared-document, hyperlink-change, version, status,
    language, and document-version fields.
- Updated the WordPress legacy DOC handoff smoke so its in-memory CFB packet
  exposes the new OLE metadata in the import summary.

## Source Truth

- Microsoft MS-OLEPS `SummaryInformation`
  (`https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleps/f7933d28-2cc4-4b36-bc23-8861cbcd37c4`)
  defines FILETIME dates, page/word/character counts, application name, and
  document security property ids.
- Microsoft MS-OSHARED `DocumentSummaryInformation`
  (`https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-oshared/3ef02e83-afef-4b6c-9585-c109edd24e07`)
  defines the document counters and boolean review state fields used here.
- Microsoft MS-OLEPS `PropertyType`
  (`https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleps/2a4589eb-9a23-4a8b-adbd-3e368233c099`)
  defines `VT_BOOL`, `VT_LPWSTR`, `VT_FILETIME`, and the typed-property values
  reused by this native reader.

This slice is intentionally bounded to OLE property metadata. It does not
implement style/list tables, fields, footnote/endnote PLCs, embedded objects,
macro streams, CFB directory tree repair, Word automation, LibreOffice
conversion, Pandoc execution, or encryption/decryption.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 70 assertions, 0 failures`
  - PASS lines: 8
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `13 test files, 3,730 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`
- `php -l lanes/pandoc/src/LegacyDocReader.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  - Result: no syntax errors
- `php -r ... json_decode(...)`
  - Result: `lane-status.json ok`; `UPSTREAM_TEST_MANIFEST.json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no whitespace errors

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader,
legacy DOC reader, Pandoc-like AST, Markdown writer, and WordPress block writer.
It does not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice,
`zip`, `unzip`, external template engines, TeX/PDF engines, browser renderers,
roff, Typst, or online services.

## Non-Overlap

This patch does not repeat accepted PDF engine fake-runner diagnostics, EPUB3,
BibTeX/CSL, CSL, YAML, doctemplate, ZIP/OPC, archive compression, DOCX/ODT,
table geometry, math/TeX, charset/Unicode, Markdown/HTML reader/writer, CFB
sector/MiniFAT parsing, OLE string metadata, CLX piece-table extraction, Word
FIB encrypted-stream preflight, or fExtChar Unicode text-range decoding. It owns
only the bounded legacy DOC OLE date/count/security metadata cluster.

## Follow-Up

Keep CFB storage hierarchy traversal, encrypted DOC password/decryption policy,
legacy DOC style and list tables, footnote/endnote PLCs, field-code extraction,
image extraction policy, embedded-object handling, vector heading-pair/docpart
metadata, user-defined property sets, and full upstream Pandoc runner parity as
separate bounded slices.
