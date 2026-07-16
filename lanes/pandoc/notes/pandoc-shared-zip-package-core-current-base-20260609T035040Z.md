# pandoc-shared-zip-package-core-current-base-20260609T035040Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T035040Z`
- Accepted base: `64291fcd23e3d1b723e600a8842760d1fbcdb417`

## Behavior

`ZipPackage::platformMetadataPreflight()` now classifies Windows Explorer
platform metadata sidecars in addition to the existing macOS sidecar checks:

- `Thumbs.db` entries are reported as `windows-thumbnail-cache-entry`.
- `desktop.ini` entries are reported as `windows-desktop-ini-entry`.
- The preflight returns Windows sidecar counts and per-entry booleans.
- Raw ZIP reads remain permissive for reviewer audit.
- `strictImportPreflight()` and `assertStrictImportable()` reject packages with
  those sidecars before Office media handoff through the existing
  `platform-metadata-entries` diagnostic.

The WordPress ZIP package preflight example now includes the Windows sidecar
review path and self-test assertions.

## Evidence

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 2341 assertions, 0 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 2392 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Status delta:

- `phpPass`: `2254 -> 2255`
- Mapped native support denominator: `2660 -> 2661`
- ZIP package core support cases: `22 -> 23`
- ZIP package core focused assertions: `161 -> 212`
- Focused assertion delta in `ZipPackageTest.php`: `+51`

## Non-Overlap

This slice does not repeat the accepted macOS `__MACOSX`, AppleDouble, or
`.DS_Store` platform metadata coverage, and it does not repeat Windows reserved
device-name or alternate-data-stream name hygiene checks. It covers Windows
Explorer sidecar files that are valid ZIP members but should not be imported as
Office package media.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
`ZipPackage` parser, strict import preflight aggregator, focused
`ZipPackageTest.php`, and WordPress ZIP package preflight example. Full upstream
Pandoc runner parity remains an upstream-runner task requiring hydrated Pandoc
sources and Haskell test executables.

## Follow-Up

Next ZIP package work should stay non-overlapping: raw local-header platform
metadata before instantiation, OPC/EPUB/ODT package-boundary semantics, or
additional archive provenance checks. Do not run Pandoc, Cabal/Haskell runners,
Word, LibreOffice, zip/unzip, external converters, online services, live
provider tests, or live-service provider tests from this lane.
