# Pandoc Legacy DOC CFB Core Current Base - 2026-06-06T004322Z

## Scope

Implemented the bounded legacy DOC/CFB current-base slice for DIFAT overflow-sector FAT listings.

- `CompoundFileBinary` now requires a declared DIFAT overflow chain to terminate with `ENDOFCHAIN` after the declared sector count.
- `LegacyDocReaderTest` now covers reading a WordDocument stream when the FAT sector is listed only from a DIFAT overflow sector.
- `LegacyDocReaderTest` now rejects an unterminated DIFAT overflow chain before stream lookup.
- `wordpress-legacy-doc-handoff.php --self-test` now builds its synthetic legacy DOC package through the same DIFAT overflow-sector path and verifies the corrupt-chain guard.

## Source Truth

This ports the CFB container contract needed by Pandoc legacy Word imports: DIFAT overflow sectors extend the FAT-sector listing beyond the header DIFAT array, and the chain pointer in the last declared DIFAT sector must be `ENDOFCHAIN`. The slice stays inside native PHP CFB parsing and legacy Word text/metadata extraction.

## Verification

Baseline before edits:

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
- Result: `1 test files, 625 assertions, 0 failures`

Red-first after adding the two focused cases, before the parser fix:

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
- Result: `1 test files, 633 assertions, 1 failures`
- Failure: `rejects unterminated CFB DIFAT overflow chains before stream lookup` did not throw `RuntimeException`.

After implementation:

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
- Result: `1 test files, 633 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
- Result: `legacy doc handoff self-test ok`

## Status Delta

- `lane-status.json` `phpPass`: `1122 -> 1124`
- `UPSTREAM_TEST_MANIFEST.json` mapped checks: `1574 -> 1576`
- Legacy DOC/CFB mapped cases: `6 -> 8`
- Legacy DOC/CFB assertion inventory: `38 -> 46`

## Dependency Closure

No new support component is needed. This reuses the native `CompoundFileBinary` and `LegacyDocReader` support already owned by `pandoc-legacy-doc-cfb-core-*`.

No Pandoc, Word, LibreOffice, zip/unzip, external office tooling, online service, or live provider test was run.

## Next

Continue legacy DOC/CFB with bounded native coverage for additional WordDocument structures, especially table/form/header/footer metadata and CFB corruption preflight that can be proven without invoking external office tools.
