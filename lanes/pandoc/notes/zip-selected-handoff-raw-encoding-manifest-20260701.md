# ZIP Selected Handoff Raw Encoding Manifest

Hook: `plib-ebgsc`, Pandoc shared ZIP/OPC package core blocker slice.

## Scope

`ZipPackage::entryHandoffPreflight()` already exposed per-entry raw ZIP name and
comment provenance for selected reader handoff entries. This slice carries the
same bounded byte-encoding markers into the stable `selectedHandoffManifest` so
DOCX, EPUB, ODF/ODT, and OPC importers can audit legacy CP437 and Info-ZIP
Unicode path/comment records from the manifest identity.

The manifest now includes request counts for raw-name provenance, legacy name
encoding, Unicode path extras, decoded-name differences, package comments,
raw-comment provenance, legacy comment encoding, Unicode comment extras, and
decoded-comment differences. Each manifest entry also records raw name/comment
hex bytes, encoding labels, match booleans, and provenance flags without reading
external targets, shelling out to zip/unzip, or exposing package payload bytes.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- Post-rebase focused gate:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `2 test files, 10006 assertions, 0 failures`

## Limits

This does not add ZIP64 support, encrypted payload support, external validators,
filesystem extraction, office-suite validation, or broader archive tooling. It
only extends metadata-only selected-entry handoff manifests for already parsed
bounded ZIP packages.
