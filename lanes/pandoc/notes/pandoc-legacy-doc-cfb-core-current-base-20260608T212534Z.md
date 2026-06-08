# Legacy DOC/CFB Active Directory Name Slice

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T212534Z`
Base accepted HEAD: `d1134e2a181aaf4c0c02f2b0d3b93f388be55ad8`

## Source Truth

- Microsoft Compound File Binary directory entries store active entry names as UTF-16LE strings with a trailing null code unit and zero padding in the fixed 64-byte name field.
- Active stream, storage, and root names must be non-empty directory names and must not contain embedded null code units before stream lookup.
- No Pandoc, Word, LibreOffice, zip/unzip, Cabal build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Implementation

- `CompoundFileBinary::parseDirectory()` now rejects decoded active directory names that are empty after terminator removal.
- It also rejects decoded active directory names containing embedded null characters before illegal-character checks and stream exposure.
- `LegacyDocReaderTest.php` extends the malformed active directory-name fixture with empty-name and embedded-null corruptions.
- The WordPress legacy DOC handoff self-test includes the same corruptions in its malformed CFB rejection loop.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 1663 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 1665 assertions, 1 failures`; the new fixture accepted a malformed active CFB directory name before the source fix.
- Final: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 1665 assertions, 0 failures`.
- Example: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` -> `legacy doc handoff self-test ok`.
- PHP lint: `php -l lanes/pandoc/src/CompoundFileBinary.php`, `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`, and `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php` -> no syntax errors.
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` -> `json ok`.
- Whitespace: `git diff --check -- lanes/pandoc` -> exit 0 with no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1863 -> 1864`.
- Manifest mapped denominator: `2290 -> 2291`.
- `legacyDocCfbCoreCases`: `7 -> 8`.
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`.
- `legacyDocCfbCoreAssertions`: `64 -> 66`.

## Dependency Closure

No new support component is needed. This reuses `CompoundFileBinary`, `LegacyDocReader`, the focused TestRunner, and the existing WordPress legacy DOC example.

## Non-Overlap

This avoids accepted CFB MiniFAT cutoff, surplus DIFAT, directory start-sector, v4 directory-count, CLSID/state-bit provenance, red-black tree/color/sort/black-height, illegal-character, dirty-padding, header CLSID/reserved/ministream cutoff/root identity, FAT/DIFAT/MiniFAT chain, storage/stream start-sector mismatch, unreferenced FAT allocation, stream-overlap, and legacy DOC field/metadata slices. It owns only active CFB directory names that decode to empty strings or contain embedded null characters.

## Follow-Up

Choose a non-overlapping legacy DOC/CFB gap such as property-set dictionary bounds, revision-mark author linkage, master-document metadata, or another bounded CFB/DOC preflight not already covered.
