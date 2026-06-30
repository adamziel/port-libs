# ZIP package manifest directory roots

Bead: `plib-psotm`
Date: 2026-06-30 UTC
Area: Pandoc shared ZIP/OPC package primitives

## Behavior

`ZipPackage::packageManifestPreflight()` now exposes whole-package top-level
directory-root provenance for importer handoff:

- Each manifest entry includes `directoryRoot` (`/`, `_rels/`, `word/`,
  `META-INF/`, and similar roots).
- The manifest includes `directoryRootCount`, ordered `directoryRoots`, and
  `directoryRootSummaries` with entry counts, file/directory counts, byte
  totals, and entry names.
- The summary is also visible through `strictImportPreflight()` and
  `rawStrictImportPreflight()`, matching the existing package manifest handoff.

The deterministic `zip-package-manifest-v1` hash payload is intentionally
unchanged. This is additive reviewer metadata only; it does not inflate payloads
or claim format-family detection.

No Pandoc binary, office suite, TeX tool, zip/unzip, ZipArchive, browser
renderer, Jupyter, Node, external validator, online service, live provider test,
or live-service provider test was invoked.

## Accounting

- `mappedZipPackageManifestDirectoryRootCases`: `+1`
- `zipPackageManifestDirectoryRootAssertions`: `+26`

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 4910 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 4668 assertions, 0 failures`
