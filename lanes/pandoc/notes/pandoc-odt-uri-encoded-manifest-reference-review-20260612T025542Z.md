# ODT URI-encoded manifest reference review

Bead: `plib-hkk5t`
Date: 2026-06-12 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion

## Scope

This slice makes URI-decoded ODF manifest package references explicit in compact
ODT package review summaries. `OpenDocumentPackage` already resolved
URI-encoded manifest `full-path` values to decoded ZIP member names for package
lookup; the package review surface now also flags those entries as
`uriEncodedPackageReference`.

The flag is carried through:

- manifest entries;
- media part summaries;
- manifest review items and manifest-order rows;
- aggregate `uriEncodedPackageReferenceCount` and item list;
- declared package inventory parts as `manifestUriEncodedPackageReference`.

This is metadata/provenance only. It does not expose additional bytes, alter ODT
content parsing, change direct-format rendering parity, or shell out to Pandoc,
office suites, ZIP tools, external validators, online services, or live
providers.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 658 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69711 assertions, 0 failures`
