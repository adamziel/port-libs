# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260606T152747Z`
Base: `3d6e6a3622decb12b82b423840061172715fe0f2`
Date: 2026-06-06 UTC

## Scope

- Added bounded native ZIP DOS external-attribute handling for package-backed
  DOCX/EPUB/ODT import preflight.
- `ZipPackageEntry` now exposes low-byte DOS read-only, hidden, system,
  volume-label, directory, and archive attributes from central-directory
  external attributes.
- `ZipPackage::dosAttributePreflight()` now reports per-entry DOS attributes
  and counts entries that require explicit media review.
- `ZipPackage::assertNoHiddenSystemOrVolumeLabelEntries()` and
  `strictImportPreflight()` now reject hidden, system, and volume-label entries
  before WordPress media handoff while allowing ordinary read-only/archive and
  directory metadata.
- Updated the WordPress ZIP package preflight example to exercise the accepted
  clean path and a rejected hidden/system/volume-label package.

## Source Truth And Non-Overlap

This stays within the shared ZIP package primitive used by Pandoc-style DOCX,
EPUB, and ODT package readers/writers. It does not repeat accepted ZIP64
extra-field rejection, split archive rejection, encrypted/AES metadata
rejection, local-header slack/prefix checks, central-directory digital
signature preflight, Unicode name collision preflight, extra-field mismatch
preflight, executable Unix permission preflight, creator-host preflight, or
deflate trailing-byte validation.

No Pandoc, Cabal solver/build/test command, Haskell runner, `ZipArchive`,
`zip`/`unzip`, Word, LibreOffice, external archive tool, external office tool,
online service, live provider test, or live-service provider test was executed.

## Verification

Baseline before adding the focused assertion:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 830 assertions, 0 failures
```

Red-first after adding the focused test and before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 830 assertions, 1 failures
Call to undefined method PortLibs\Pandoc\ZipPackage::dosAttributePreflight()
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 862 assertions, 0 failures

php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Syntax and diff hygiene:

```text
php -l lanes/pandoc/src/ZipPackage.php
php -l lanes/pandoc/src/ZipPackageEntry.php
php -l lanes/pandoc/tests/ZipPackageTest.php
php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php
git diff --check -- lanes/pandoc
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`ZipPackageEntry`, in-process ZIP fixture builders, CRC/DEFLATE handling, strict
import preflight, and the existing WordPress ZIP package preflight example.
Full ZIP64 large-archive support, spanning archives, encrypted payload
extraction, external archive tooling, Word/LibreOffice/Pandoc conversion, and
Haskell/Cabal runner parity remain out of scope for this bounded package-core
slice.

## Next Task

Continue ZIP/OPC package closure with default reader strict-policy wiring,
central-directory signature trust decisions, additional compression-method
guardrails, or reader-specific DOCX/EPUB/ODT package policy handoffs.
