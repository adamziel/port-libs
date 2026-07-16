# ODF Package Path Segment Position Provenance

Bead: `plib-l8xvy`

Slice: `pandoc-odf-package-path-segment-position-provenance`

## Scope

- Added per-segment path position provenance to ODF package path shapes emitted
  by `OpenDocumentPackage`.
- Each path shape now reports `pathSegmentPositionReviews` alongside existing
  `segments`, `segmentCount`, and `directorySegmentCount` metadata.
- Segment records include the segment index, raw segment value, normalized
  position label (`only`, `first`, `middle`, or `last`), and boolean first/last/
  only flags for manifest review, package inventory, and package identity
  handoff.

## Fixture

- Extended `preflights compact ODT manifest and package path shapes without
  exposing bytes` in `OpenDocumentPackageTest.php`.
- Extended `summarizes ODT package inventory areas and path depths for package
  review` with a three-segment `Configurations2/statusbar/statusbar.xml`
  assertion to lock down the `middle` classification.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 2087 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
  - `2 test files, 2139 assertions, 0 failures`

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, zip/unzip command, online service, live provider test, or
payload-expanding external tool was invoked.
