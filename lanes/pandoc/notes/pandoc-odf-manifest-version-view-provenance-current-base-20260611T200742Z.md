# Pandoc ODF manifest version and preferred view provenance

Bead: plib-1ar84
Slice: Pandoc ODF/ODT OpenDocument package ingestion core blocker 20260611T200742Z
Base: origin/main e125100f7

## Change

- Carried parsed `manifest:version` and `manifest:preferred-view-mode` values
  into ODT manifest review items.
- Exposed the same provenance for ODT media summary packets.
- Added package inventory fields for manifest-declared ZIP parts:
  `manifestVersion` and `manifestPreferredViewMode`.
- Added focused coverage for root, `content.xml`, and media-part propagation
  without exposing package bytes beyond the existing bounded ODT policy.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 382 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 65815 assertions, 0 failures
