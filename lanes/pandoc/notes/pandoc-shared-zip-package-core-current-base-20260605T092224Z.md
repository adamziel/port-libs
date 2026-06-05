# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T092224Z`
Base accepted HEAD: `8018c2edef5162f55a780010aec2655e6598b40f`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded Unix permission preflight for ZIP package entries.
- `ZipPackageEntry` now exposes `unixPermissionBits()` and
  `isUnixExecutableFile()` from central-directory external attributes when the
  entry was made by the Unix host system.
- `ZipPackage::permissionPreflight()` now reports entry count, Unix-mode entry
  count, executable-file count, per-entry mode/permission metadata, and the
  executable-entry subset.
- `ZipPackage::assertNoExecutableFiles()` rejects executable file entries with
  explicit names for import review while leaving ordinary package inventory
  parsing intact.
- Directory execute bits are not treated as executable-file payloads, and
  non-Unix host external attributes remain metadata only.
- The WordPress ZIP package preflight smoke now proves generated import
  packages have no executable payloads and that executable media is rejected
  before attachment import.

## Source Truth

- ZIP central directory entries carry a host-system byte in `version made by`
  and host-specific external file attributes. This lane reuses the existing
  native `ZipPackageEntry::unixMode()` interpretation of Unix entries: high
  16 bits of external attributes hold POSIX file type and permission bits.
- Pandoc ZIP/OPC package readers need archive metadata preflight before DOCX,
  EPUB, ODT, and WordPress media handoff. This slice ports that bounded format
  contract only; it does not implement shell extraction or external archive
  tooling.

No Pandoc, Cabal build, Haskell runner, ZipArchive, zip/unzip, Word,
LibreOffice, office tooling, external conversion service, or online service was
used.

## Verification

Focused red-first check after adding the test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
Call to undefined method PortLibs\Pandoc\ZipPackage::permissionPreflight()
1 test files, 288 assertions, 1 failures
```

Focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 307 assertions, 0 failures
```

Focused delta over the previous ZIP package run: `288 -> 307` assertions
(`+19`) and one new focused PASS case.

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Full pandoc lane verification:

```text
php tools/run-tests.php lanes/pandoc/tests
20 test files, 9698 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '
788
```

Syntax and JSON checks:

```text
php -l lanes/pandoc/src/ZipPackage.php
No syntax errors detected in lanes/pandoc/src/ZipPackage.php

php -l lanes/pandoc/src/ZipPackageEntry.php
No syntax errors detected in lanes/pandoc/src/ZipPackageEntry.php

php -l lanes/pandoc/tests/ZipPackageTest.php
No syntax errors detected in lanes/pandoc/tests/ZipPackageTest.php

php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php
No syntax errors detected in lanes/pandoc/examples/wordpress-zip-package-preflight.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'
lanes/pandoc/lane-status.json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok

git diff --check -- lanes/pandoc
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native ZIP package
central-directory parser, entry metadata model, package writer, size preflight,
WordPress package preflight example, and focused PHP test harness. Full upstream
Pandoc runner parity remains gated on hydrating the pinned Pandoc checkout with
Cabal project/package files.

## Non-Overlap

This does not repeat recent ZIP package work for central/local extended
timestamps, NTFS timestamp conflict preflight, ZIP64 extra-field rejection,
Unix symlink rejection, drive-letter path rejection, package size preflight,
local-header mismatch guards, slack-entry rejection, hidden local-entry
rejection, or archive compression helpers. It owns only Unix executable
permission detection and explicit import-preflight rejection.

## Follow-Up

Keep AES/encrypted payload support, spanning archives, verified
central-directory signature parsing, full ZIP64 large-archive support,
non-deflate compression methods, default reader size-limit policies, and
package-reader wiring for `assertNoExecutableFiles()` as separate bounded
slices.
