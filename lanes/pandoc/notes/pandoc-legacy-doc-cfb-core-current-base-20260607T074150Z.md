# pandoc-legacy-doc-cfb-core-current-base-20260607T074150Z

## Scope

Lane: pandoc
Slice: pandoc-legacy-doc-cfb-core-current-base-20260607T074150Z
Base: 3d2d3e6ef4226dffa58dcb186275876022069cff

Implemented one bounded legacy DOC/CFB support-library cluster: CFB container
sector-boundary preflight. `CompoundFileBinary` now rejects files whose byte
length leaves trailing partial sector bytes after the header sector, before
FAT/DIFAT traversal or `WordDocument` stream lookup. The focused fixture covers
both version 3 512-byte-sector and version 4 4096-byte-sector containers.

Source truth:

- MS-CFB sector numbers and types: https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/9d33df18-7aee-4065-9121-4eabe41c29d4
- MS-CFB compound file size limits: https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/d4c8084a-e583-473f-b2f4-4af13b3101d9

## Evidence

Baseline red focused run:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: failed the new `rejects CFB files with trailing partial sectors before
stream lookup` case with `Expected exception RuntimeException was not thrown`.
The run reported 1 test files, 944 assertions, 1 failures.

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: 1 test files, 946 assertions, 0 failures.

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: `legacy doc handoff self-test ok`.

## Mapping Delta

- `phpPass`: 1469 -> 1470
- `benchmarkDenominator.mapped`: 1886 -> 1887
- `legacyDocCfbCoreCases`: 7 -> 8
- `mappedLegacyDocCfbCoreCases`: 7 -> 8
- `legacyDocCfbCoreAssertions`: 64 -> 68
- Focused assertions: +4 in `LegacyDocReaderTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`CompoundFileBinary` parsing, existing legacy DOC CFB fixtures,
`LegacyDocReader` focused tests, and the WordPress legacy DOC handoff example.

No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external office
tool, online service, live provider test, or live-service provider test was
executed.

## Non-Overlap

This avoids the accepted legacy DOC/CFB clusters for CLSID/state-bit provenance,
FibRgLw97 subdocument CP boundaries, PlcfldEdn field-table metadata, surplus
DIFAT preflight, directory start-sector preflight, MiniFAT cutoff preflight,
DOP metadata, associated strings, OLE property sets, embedded object metadata,
and field/bookmark/note/comment PLC handoffs. The patch is limited to CFB
sector-boundary validation before stream exposure.

## Follow-Up

Keep follow-up work bounded to non-overlapping native MS-CFB or MS-DOC support
surfaces such as FFData form metadata, remaining safe table-stream metadata, or
additional allocation preflight. Full upstream Pandoc runner parity remains out
of this slice because external Pandoc/Haskell/office runners were not
authorized or needed for this bounded support-library case.
