# ZIP Package Manifest Extra Field Rollups

Slice: `plib-jty4i`
Date: 2026-07-01

## Change

- `ZipPackage::packageManifestPreflight()` now carries central and local ZIP
  extra-field record counts, ID lists, ID usage buckets, and provenance entries
  in the deterministic package manifest.
- Each manifest entry exposes central/local extra-field record counts, ID hex
  lists, header-presence flags, and whether central/local extra-field IDs match.
- The package aggregate now reports combined extra-field entry/record counts and
  shared, central-only, and local-only ID rollups for DOCX, EPUB3, ODF/ODT, and
  raw OPC package handoff code.

## Coverage

- Extended deterministic ZIP package manifest hash fixtures so the new fields
  are part of the hashed package manifest payload.
- Added a positive package-manifest fixture with shared, central-only, and
  local-only extra-field IDs across source headers.
- Verified the package manifest remains mirrored through strict and raw strict
  import preflights.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 6,119 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 5,333 assertions, 0 failures

Direct-format parity accounting remains unchanged. This slice is limited to
bounded native PHP ZIP/OPC package metadata and does not invoke Pandoc, office
suites, TeX/browser engines, `zip`/`unzip`, Jupyter, Node tooling, live
services, or external validators.
