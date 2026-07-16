# pandoc-legacy-doc-cfb-core-current-base-20260607T023059Z

## Scope

Lane: pandoc
Slice: pandoc-legacy-doc-cfb-core-current-base-20260607T023059Z
Base: 1184cf3a9da378a22c1b6fb0204c5e2b9c8cfa6e

Implemented one bounded legacy DOC/CFB support-library cluster: MS-DOC DOP document-property table extraction from FibRgFcLcb97 fcDop/lcbDop. The native reader now parses the 84-byte DopBase common structure from the selected table stream and exposes document-property policy flags, note numbering defaults, DTTM timestamps, counts, protection hash, and saved view metadata as metadata-only review data.

Source truth:

- MS-DOC Dop: https://learn.microsoft.com/en-us/openspecs/office_file_formats/MS-DOC/841b5f72-487e-4fe7-8657-ec90d5af8750
- MS-DOC DopBase: https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/f885b87a-15b3-460e-aecc-213bd17f960e

## Evidence

Baseline focused run:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: 1 test files, 868 assertions, 0 failures.

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: 1 test files, 903 assertions, 0 failures.

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: legacy doc handoff self-test ok.

## Mapping Delta

- `phpPass`: 1436 -> 1437
- `benchmarkDenominator.mapped`: 1853 -> 1854
- `legacyDocCfbCoreCases`: 7 -> 8
- `mappedLegacyDocCfbCoreCases`: 7 -> 8
- `legacyDocCfbCoreAssertions`: 64 -> 99
- Focused assertions: +35 in LegacyDocReaderTest.php.

## Dependency Closure

No new support component is needed. This slice reuses native PHP CompoundFileBinary parsing, selected table-stream slicing, LegacyDocReader metadata handoff, AstNode attributes, WordPressBlockWriter, the existing legacy DOC WordPress handoff example, and the focused lane PHP harness.

No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This avoids the accepted legacy DOC/CFB clusters for CLSID/state-bit provenance, FibRgLw97 subdocument CP boundaries, PlcfldEdn field-table metadata, surplus DIFAT preflight, directory start-sector preflight, MiniFAT cutoff preflight, associated strings, OLE property sets, and field/bookmark/note/comment PLC handoffs. The patch is limited to DOP/DopBase document settings/state metadata.

## Follow-Up

Keep follow-up work bounded to non-overlapping native MS-DOC table surfaces such as document variables, saved-by/user metadata, language/compatibility options beyond DopBase, or additional PLC handoffs. Full upstream Pandoc runner parity remains out of this slice because external Pandoc/Haskell/office runners were not authorized or needed for this bounded support-library case.
