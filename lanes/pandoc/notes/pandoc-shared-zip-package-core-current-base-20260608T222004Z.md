# Pandoc Shared ZIP Package Core Current Base

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260608T222004Z`
Accepted base: `482dab075a80303a3de728b33addb2e6ee48c0a9`
Date: 2026-06-08 UTC

## Behavior

This slice adds bounded native ZIP package preflight coverage for archive
extra data records that appear between central directory entries. The previous
archive-extra preflight handled central-directory prefix records and records
between the central directory and EOCD, but an inter-entry record stopped the
central-entry scan and reduced raw strict import diagnostics to a central
directory parse failure.

`ZipPackage::archiveExtraDataRecordPreflight()` now records inter-entry archive
extra data records as unsupported package metadata, keeps scanning the
remaining central entries, and lets `rawStrictImportPreflight()` surface the
structured `archive-extra-data-records` diagnostic before package handoff.

## Evidence

- Rework notes checked: no `port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 1942 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 1958 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed with `zip package writer preflight self-test passed`.

## Status Delta

- Added one focused ZIP package PASS case:
  `preflights inter-entry zip archive extra data records before raw strict import`.
- Focused ZIP assertions rose from `1942` to `1958`.
- `lane-status.json` `phpPass` moved from `1918` to `1919`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moved from `2341` to `2342`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP ZIP EOCD,
central-directory, archive-extra-data, raw strict import, and WordPress ZIP
package preflight helpers. No Pandoc, Cabal solver/build/test command, Haskell
runner, zip/unzip, ZipArchive, Word, LibreOffice, external archive tool,
online service, live provider test, or live-service provider test was run.

## Next

A non-overlapping ZIP follow-up could cover unsupported creator-host edge
cases, stricter raw-name provenance reporting, generated-package metadata
policy, or bounded ZIP64 policy gaps not already covered by archive-extra,
descriptor, timestamp, name-hygiene, encryption, and ZIP64 EOCD slices.
