# pandoc-legacy-doc-cfb-core-current-base-20260607T084829Z

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260607T084829Z`

Implemented one bounded legacy DOC/CFB support-library cluster: MS-DOC
`SttbSavedBy` save-history metadata from `FibRgFcLcb97` `fcSttbSavedBy` /
`lcbSttbSavedBy` offsets.

## Behavior

- `LegacyDocReader` now parses the selected table-stream `SttbSavedBy` STTB
  when present and exposes metadata-only save-history records with author,
  path, basename, source-table, and earliest-to-latest ordering.
- The latest save author/path/basename are surfaced in review metadata while
  the save-history strings remain out of Markdown and WordPress rendered
  document output.
- The parser fails closed on malformed save-history tables: non-extended STTB
  headers, odd string counts, more than 20 strings, nonzero `cbExtra`, trailing
  bytes, missing table streams, and truncated string data.
- The WordPress legacy DOC handoff smoke now includes ordered save-history
  provenance and checks that it stays metadata-only.

## Source Truth

- Microsoft MS-DOC `FibRgFcLcb97` identifies `fcSttbSavedBy` and
  `lcbSttbSavedBy` as the table-stream offset and byte size of `SttbSavedBy`.
- Microsoft MS-DOC `SttbSavedBy` defines the table as author/path string pairs,
  ordered earliest to latest, with `fExtend = 0xFFFF`, even `cData <= 0x0014`,
  and `cbExtra = 0x0000`.

No Word, LibreOffice, Pandoc, Cabal, Haskell runner, zip/unzip, external office
tool, online service, live provider test, or live-service provider test was run.

## Verification

Baseline:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 946 assertions, 0 failures`.

Final:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 973 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: `legacy doc handoff self-test ok`.

Lint:

- `php -l lanes/pandoc/src/LegacyDocReader.php`
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`

All reported no syntax errors.

Final lane whitespace check:

`git diff --check -- lanes/pandoc`

Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1477 -> 1478`
- `benchmarkDenominator.mapped`: `1896 -> 1897`
- `legacyDocCfbCoreCases`: `7 -> 8`
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`
- `legacyDocCfbCoreAssertions`: `64 -> 91`
- Focused assertions: `946 -> 973` in `LegacyDocReaderTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`CompoundFileBinary`, `LegacyDocReader` table-stream slicing, AST metadata
attributes, `WordPressBlockWriter`, and the existing focused lane test/example
harness.

## Non-Overlap

This avoids accepted legacy DOC/CFB clusters for DOP/DopBase properties,
`StwUser` document variables, associated strings, OLE property sets, CFB
directory provenance/preflight, MiniFAT/DIFAT safety, `PlcfldEdn`, notes,
comments, bookmarks, sections, style/list/formatting tables, embedded objects,
macro project metadata, picture placeholders, `FibRgLw97` subdocument
boundaries, CLX/PCD text extraction, and field-code rendering. The new surface
is only `SttbSavedBy` save-history metadata.

## Follow-Up

Keep follow-up work bounded to non-overlapping native MS-DOC table surfaces
such as `SttbFnm` external file references, route-slip metadata, or additional
form/OLE-control metadata. Full upstream Pandoc runner parity remains separate
because external Pandoc/Haskell/office runners were not authorized or needed
for this bounded support-library case.
