# ODT Reader Package Identity Provenance

Date: 2026-06-26 UTC
Slice: plib-l8xvy
Area: `lanes/pandoc`

## Change

- Added metadata-only ODF/ODT reader package identity provenance to `OdfReader` package ingestion.
- The identity records stable manifest/package structure signals, encoded part references, package comments, root custom manifest attributes, undeclared package parts, role counts, and byte-exposure buckets.
- Script, configuration, and undeclared private package bytes remain blocked; identity hashing never requires exposing part payload bytes.
- Manifest-order rows now retain parsed query/fragment provenance for encoded package part references.

## Accounting

- Focused PHP behavior tests: `440 -> 441`.
- Focused failures: `0`.
- Local mapped ODF/ODT cases: `89 -> 90`.
- New focused file: `lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`.
- Focused assertions added: `52`.

## Validation

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`

Result: `1` focused file, `52` assertions, `0` failures.

The broader `OdfReaderTest.php` file remains outside this slice because it currently carries the existing unrelated rendering-output failure baseline.
