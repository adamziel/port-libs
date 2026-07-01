# ODF Package Path Shape Provenance

Date: 2026-06-30 UTC
Slice: plib-yn5k5
Area: `lanes/pandoc`

## Change

- Added metadata-only path-shape provenance for compact ODF/ODT package ingestion.
- `OpenDocumentPackage` now records manifest and ZIP path kind, top-level segment, directory, basename, extension, segment list, and segment counts across manifest review items, media parts, package inventory, undeclared package entries, and package identity.
- Manifest review now aggregates path kind, top-level segment, and extension counts for declared manifest entries.
- Package inventory and package identity now aggregate ZIP package path kind, top-level segment, and extension counts.
- URI-encoded manifest paths keep separate manifest path shape and decoded package path shape records.

## Accounting

- Direct-format parity remains active: this is ODF/ODT package metadata parity only, not a new format token.
- Byte exposure remains unchanged: path-shape records are derived from manifest full-path values and ZIP entry names, and private or blocked package bytes remain unavailable.
- Focused PHP behavior tests: `469 -> 470`.
- Focused failures: `0`.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`

Result: `1` focused file, `1928` assertions, `0` failures.
