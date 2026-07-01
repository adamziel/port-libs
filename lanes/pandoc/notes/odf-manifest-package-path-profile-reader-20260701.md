# ODF manifest package path profile reader provenance

Slice: `plib-osst3`

## Summary

- Added rich `OdfReader` package provenance for manifest package path shape via `manifestPackagePathProfile`.
- The profile groups manifest file-entry paths by package root, depth, file extension, URI-encoded part reference, suffix reference, directory/file status, and duplicate basenames.
- Package identity now carries compact derived counters/maps for manifest path roots, depths, file extensions, and duplicate basename items.

## Scope

This stays native PHP-only and metadata-only. It does not expose blocked package bytes, invoke Pandoc/LibreOffice/zip tools, or change direct-format ODT reader rendering parity.

## Validation

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
