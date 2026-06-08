# Legacy DOC/CFB RouteSlip Metadata Slice

Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T132712Z`

Source truth:
- Microsoft MS-DOC `RouteSlip`: https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/b28f8c1f-133a-4394-a42d-bcf8d931e3f4
- Microsoft MS-DOC `RouteSlipInfo`: https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/7ad2bffe-0d91-48de-8807-ed798b3db2bd

Implementation:
- Added bounded `fcRouteSlip`/`lcbRouteSlip` table-stream parsing in `LegacyDocReader`.
- Preserves `RouteSlip` flags, protection/stage/delivery metadata, subject/message/status/title strings, and `RouteSlipInfo` recipient name plus entry-id byte provenance as metadata-only review data.
- Rejects malformed RouteSlip tables before exposing converted text: invalid recipient counts, invalid delivery/stage values, oversized/truncated strings, empty recipient names, truncated recipient records, and trailing bytes.
- Updated the WordPress legacy DOC handoff smoke so route-slip routing metadata remains out of rendered blocks.

Verification:
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 1283 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` after adding RouteSlip expectations -> `1 test files, 1284 assertions, 1 failures` because `routeSlip` metadata was absent.
- Final: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 1326 assertions, 0 failures`.
- Example: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` -> `legacy doc handoff self-test ok`.
- Required lint/diff checks are recorded in `lane-status.json` after the final verification pass.

Status delta:
- `phpPass`: `1654 -> 1655`.
- `benchmarkDenominator.mapped`: `2074 -> 2075`.
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`.
- Focused assertion delta: `+43`.

Dependency closure:
- No new support component is needed. The slice reuses the existing `CompoundFileBinary` reader, selected table-stream slicing, Windows-1252 decoding, `LegacyDocReaderTest.php` fixtures, and the WordPress legacy DOC handoff example.
- No Word, LibreOffice, Pandoc, Cabal/Haskell runner, zip/unzip, external office tool, online service, live provider test, or live-service provider test was run.

Non-overlap:
- Avoided accepted CFB FAT/DIFAT/MiniFAT/directory preflight slices, DOP/SttbfAssoc/StwUser/SttbSavedBy metadata slices, FibRgLw97/subdocument slices, and field-table/field-result slices. This patch only owns MS-DOC RouteSlip and RouteSlipInfo metadata.
