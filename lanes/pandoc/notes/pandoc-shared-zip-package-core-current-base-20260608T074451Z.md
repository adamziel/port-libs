# pandoc-shared-zip-package-core-current-base-20260608T074451Z

## Behavior

`ZipPackage::fromParts()` now rejects generated ZIP extra fields that repeat an
extra-field id before package bytes are emitted. This covers caller-supplied
duplicate custom fields and collisions between generated `modifiedAt` metadata
and caller-supplied `0x5455` extended timestamp fields.

The WordPress ZIP package preflight example keeps reader-side malformed
duplicate extra-field coverage by using a raw lane-local ZIP byte fixture for
the duplicate import package, while generated duplicate metadata now fails at
writer time.

## Evidence

- No `port-pandoc` rework note existed for this slice.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 1315 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 1321 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed with `zip package writer preflight self-test passed`.
- PHP lint passed for the changed PHP files.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1565 -> 1566`
- `benchmarkDenominator.mapped`: `1986 -> 1987`
- `zipPackageCoreSupportCases`: `22 -> 23`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`
- `zipPackageCoreAssertions`: `161 -> 167`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`ZipPackage` writer, `ZipPackageEntry` extra-field parser, and the existing
WordPress ZIP package preflight example. No Pandoc, Cabal/Haskell runner,
zip/unzip, ZipArchive, Word, LibreOffice, external archive tool, online service,
live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat existing ZIP reader checks for data descriptors,
central-directory signatures, Unicode name collisions, invalid DOS timestamps,
trailing deflate bytes, NTFS timestamps, ZIP64 rejection, or imported duplicate
extra-field preflight. The new behavior is writer-side generated extra-field
deduplication before bytes are emitted.

## Follow-Up

Useful follow-up ZIP/OPC work remains bounded to native PHP package behavior,
such as ZIP64 local/central compatibility, format-specific generated
extra-field policies, OPC content-type/relationship edge cases, or Office media
preflight diagnostics.
