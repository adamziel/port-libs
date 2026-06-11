# Pandoc ODF/ODT Manifest Package Provenance Intent

Slice: `plib-a5ipn`
Base: `806d597b3`

## Scope

ODF/ODT package ingestion now carries manifest `file-entry` intent fields into package provenance review rows:

- Manifest file-entry order rows include `version`, `preferredViewMode`, `declaredSize`, and `declaredSizeMismatch`.
- ZIP part inventory rows include matching manifest-derived `manifestVersion`, `manifestPreferredViewMode`, `manifestDeclaredSize`, and `manifestDeclaredSizeMismatch`.
- Byte exposure policy is unchanged; the slice only preserves already-parsed manifest metadata for reviewer comparison against ZIP order and package entries.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> 1 file, 4065 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 files, 66818 assertions, 0 failures
