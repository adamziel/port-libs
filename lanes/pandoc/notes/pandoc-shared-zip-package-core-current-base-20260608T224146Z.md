# Pandoc ZIP Package Core: Raw Creator Host Policy

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260608T224146Z`
Accepted base: `fb68aedd3080f5c5d86cf57108d39e4c2a7b6359`
Date: `2026-06-08 UTC`

## Behavior

Added byte-level ZIP creator-host policy preflight for package handoff code:

- `ZipPackage::creatorHostSystemPolicyPreflight()` scans central-directory
  records before ZIP package instantiation.
- Unknown creator host systems are reported as blocked entries with
  `zip-unknown-creator-host-system` diagnostics.
- `ZipPackage::rawStrictImportPreflight()` now includes the creator-host
  policy summary even when other raw ZIP features, such as strong encryption,
  prevent normal package instantiation.

This keeps DOCX/EPUB/ODT package readers from silently accepting unknown ZIP
host provenance when a fixture also contains another unsupported flag.

## Evidence

- Rework notes checked:
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  had no matches for this lane.
- Baseline focused command before the test/source change:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 1958 assertions, 0 failures`.
- Red-first focused command after adding the raw creator-host assertions:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  failed with `1 test files, 1958 assertions, 1 failures` because
  `PortLibs\Pandoc\ZipPackage::creatorHostSystemPolicyPreflight()` did not
  exist.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 1977 assertions, 0 failures`.
- WordPress-relevant example smoke:
  `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed with `zip package writer preflight self-test passed`.

## Status Delta

- Added one mapped native ZIP support case inside the focused ZIP package
  core coverage.
- Focused ZIP assertions increased from `1958` to `1977`.
- `lane-status.json` moves `phpPass` from `1936` to `1937`.
- `UPSTREAM_TEST_MANIFEST.json` moves `mapped` from `2357` to `2358`.
- ZIP package core support cases move from `22` to `23`.
- ZIP package core assertion evidence moves from `161` to `180`.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
native PHP ZIP byte parser and does not call Pandoc, Word, LibreOffice,
zip/unzip, `ZipArchive`, TeX/PDF engines, Haskell binaries, or online services.

## Non-Overlap

This does not repeat the accepted ZIP central-directory signature, archive
extra record, Unicode-name collision, trailing deflate, invalid DOS timestamp,
compression-method, encryption, ZIP64, data-descriptor, or local-header
metadata slices. It specifically covers raw creator-host policy diagnostics
that remain available when unsupported entry flags block normal instantiation.

## Next

Next ZIP package work should target a distinct gap such as generated-package
creator-host policy, ZIP64 upgrade planning, or raw name-provenance handoff
behavior.
