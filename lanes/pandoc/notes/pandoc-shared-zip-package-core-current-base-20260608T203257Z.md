# ZIP Package Core Current-Base Empty-Package Strict Import

Slice: `pandoc-shared-zip-package-core-current-base-20260608T203257Z`
Base accepted HEAD: `bb37a42dff2002404bb134df44da31542c787c36`
Date: 2026-06-08 UTC

## Source Truth And Non-Overlap

- Used the existing lane ZIP package primitives and manifest as source truth; no hydrated Pandoc upstream checkout was present under `/home/claude/port-libs/.upstream-cache/pandoc`.
- Avoided the accepted ZIP slices for central-directory signature provenance, trailing-deflate payload integrity, Unicode-name collisions, invalid DOS timestamps, version-needed policy, ZIP64, split archives, encryption, comments, extra fields, path hygiene, and local-header integrity.
- This slice covers one new package-core gap: an empty ZIP archive can remain parseable as ZIP bytes, but strict DOCX/ODT/EPUB-style document import must reject it as contentless before handoff.

## Implementation

- Added `ZipPackage::contentPresencePreflight()` with `entryCount`, `hasEntries`, bounded-reader support, and `empty-package` issues.
- Wired content-presence into `strictImportPreflight()` diagnostics and return data.
- `rawStrictImportPreflight()` now carries the strict `empty-package` diagnostic when an empty package can instantiate but cannot satisfy strict document import.
- Updated the WordPress ZIP package preflight example to assert and print empty-package strict/raw import rejection.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` failed red-first after the test was added, as expected, because `ZipPackage::contentPresencePreflight()` was missing: `1 test files, 1759 assertions, 1 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed: `1 test files, 1779 assertions, 0 failures`.
- Example: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` passed: `zip package writer preflight self-test passed`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case and 20 focused assertions for ZIP package strict import behavior.
- Updated `lanes/pandoc/lane-status.json` `phpPass` from `1816` to `1817`.
- Updated `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped count from `2240` to `2241`.

## Dependency Closure

No new support component is needed. This reuses native `ZipPackage` EOCD/central-directory parsing, strict import aggregation, raw strict import preflight, focused PHP tests, and the lane-local WordPress ZIP preflight example. Pandoc, Cabal/Haskell runners, Word, LibreOffice, `zip`/`unzip`, `ZipArchive`, external archive tools, online services, live provider tests, and live-service provider tests were not executed.
