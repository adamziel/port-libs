# Legacy DOC/CFB Source Field Handoff - 2026-06-09

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T032222Z`

Base accepted HEAD: `50a0721b38afd3fbb00d7da806b11da7b3e09bf4`

## Source Truth

- Microsoft MS-DOC field type table (`flt`) records `FILENAME`, `TEMPLATE`, `INCLUDE`, `IMPORT`, and `FILESIZE` field codes. See: https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/28a8d2c2-6107-409d-8f6a-e345ab6d4179

## Implementation

- Added native `LegacyDocReader` field type mappings for `0x1d` `filename`, `0x1e` `template`, `0x24` `include`, `0x37` `import`, and `0x45` `filesize`.
- Preserved `FILENAME`, `TEMPLATE`, and `FILESIZE` field results as `legacy-doc-source-field` spans with metadata-only source review attributes, basename/result-kind classification, and no external file access.
- Routed `INCLUDE` and `IMPORT` aliases through the existing include-field handoff: `INCLUDE` maps to include-text metadata and `IMPORT` maps to include-picture metadata.
- Updated the WordPress legacy DOC handoff example so the end-to-end CFB/Plcfld fixture verifies the same source-location and alias behavior.

## Verification

- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: failed on the new source-location test because `FILENAME`, `TEMPLATE`, `FILESIZE`, `IMPORT`, and `INCLUDE` Plcfld type codes were reported as `unknown`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 2051 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP CFB parser, FIB/Plcfld parsing, field instruction tokenizer, AST span writer, and WordPress block writer. No Pandoc, Word, LibreOffice, zip/unzip, external converter, TeX/PDF engine, Haskell runner, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted legacy DOC work for CFB allocation/preflight, root timestamps, `SttbFnm`, `SttbfAssoc`, `SttbfRMark`, `Pms`, `DATA` mail-merge redirects, route slips, ObjectPool/media placeholders, or previously mapped include field source matching. It only adds MS-DOC source-location field codes and include aliases around displayed field results.

## Follow-Up

Remaining legacy DOC/CFB follow-up can target other unmapped field-code metadata, unsafe external-source policy hints, or table/shape handoff gaps as separate native PHP slices.
