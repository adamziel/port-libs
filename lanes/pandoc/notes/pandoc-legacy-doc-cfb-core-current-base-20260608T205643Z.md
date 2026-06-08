# Legacy DOC/CFB Unreferenced FAT Allocation Slice

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T205643Z`
Base accepted HEAD: `65a813a175ece348dfcccbd33f271783300e8c24`

## Source Truth

- Microsoft Compound File Binary FAT entries describe sector allocation for physical sectors.
- Physical sectors not owned by FAT, DIFAT, directory, MiniFAT, root mini-stream, or stream chains must remain `FREESECT`; hidden allocated sectors must not be ignored before `WordDocument` exposure.
- No Pandoc, Word, LibreOffice, zip/unzip, Cabal build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Implementation

- `CompoundFileBinary::validateSectorAllocation()` now tracks owned physical sectors while validating metadata chains, root mini-stream, and regular stream chains.
- It rejects any physical sector whose FAT entry is not `FREESECT` and is not owned by one of those chains.
- The WordPress legacy DOC handoff self-test includes a corrupt hidden allocated-sector packet in its CFB rejection loop.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 1625 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 1626 assertions, 1 failures`; the new test accepted an unreferenced allocated sector before the source fix.
- Final: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 1627 assertions, 0 failures`.
- Example: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` -> `legacy doc handoff self-test ok`.
- PHP lint: `php -l lanes/pandoc/src/CompoundFileBinary.php`, `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`, and `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php` -> no syntax errors.
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` -> `json ok`.
- Whitespace: `git diff --check -- lanes/pandoc` -> exit 0 with no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1838 -> 1839`.
- Manifest mapped denominator: `2262 -> 2263`.
- `legacyDocCfbCoreCases`: `7 -> 8`.
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`.
- `legacyDocCfbCoreAssertions`: `64 -> 66`.

## Dependency Closure

No new support component is needed. This reuses `CompoundFileBinary`, `LegacyDocReader`, the focused TestRunner, and the existing WordPress legacy DOC example.

## Non-Overlap

This avoids accepted CFB header, FAT, DIFAT, MiniFAT, directory, orphan active-entry, directory start-sector mismatch, surplus DIFAT, MiniFAT cutoff, FAT entries beyond physical file, reserved marker, stream overlap/share, and Word/DOC field/metadata slices. It owns only unreferenced physical sectors that are marked allocated in the FAT.

## Follow-Up

Choose a non-overlapping legacy DOC/CFB gap such as FFData linkage, revision-mark author linkage, deeper master-document metadata, or another bounded CFB/DOC preflight not already covered.
