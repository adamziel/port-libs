# Pandoc Legacy DOC/CFB FAT EOF Preflight

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T124943Z`

Base accepted HEAD: `cf694c999fba2a9ae966ad0c44be82830abea0f8`

## Scope

- Added a native CFB allocation preflight in `CompoundFileBinary`: FAT entries whose sector ids are beyond the physical file sector count must remain `FREESECT`.
- Added a focused Legacy DOC reader regression fixture that mutates the unused tail of the first FAT sector and expects rejection before `WordDocument` stream lookup.
- Extended the WordPress legacy DOC handoff self-test corrupt-header loop with the same beyond-EOF FAT entry case.

## Source Truth

MS-CFB treats the FAT as the sector allocation table for the compound file. Sector ids outside the physical file are not valid stream-chain targets; unused FAT slots in the final FAT sector must therefore remain free. This slice ports that bounded preflight locally instead of attempting whole-office conversion behavior.

## Evidence

- Red-first focused test: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` failed with `1 test files, 1283 assertions, 1 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 1283 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` passed with `legacy doc handoff self-test ok`.
- PHP lint passed for changed PHP files.
- Lane JSON validation passed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2065 -> 2066`.
- `legacyDocCfbCoreCases`: `7 -> 8`.
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`.
- `legacyDocCfbCoreAssertions`: `64 -> 65`.
- `lane-status.json` `phpPass`: `1645 -> 1646`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP CFB parser, Legacy DOC fixtures, and WordPress handoff example. No Pandoc, Word, LibreOffice, zip/unzip, Cabal/Haskell runner, external office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the accepted MiniFAT cutoff, surplus DIFAT, directory start-sector, directory CLSID/state-bit/timestamp, FibRgLw97, field-table, prompt/include field, or ObjectPool/OLE legacy DOC slices. It is a distinct CFB FAT EOF allocation guard before stream lookup.

## Follow-Up

Next Legacy DOC/CFB work can target FFData form-field option decoding after bounded CHPX/FKP property support, route-slip metadata, hyperlink object metadata, or another safe CFB allocation preflight.
