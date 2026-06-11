# ODF XML Part Version Provenance Current-Base Slice

Slice: `plib-o7x0y` / `20260611T170443Z`
Base: `499fb850d`

## Scope

- Added ODT package review metadata for `office:version` on the core XML package parts: `content.xml`, `styles.xml`, `meta.xml`, and `settings.xml`.
- Exposes `documentPartVersions` under document manifest metadata and import-report manifest metadata.
- Each item preserves expected/root element names, manifest declaration, manifest media type, byte length, compressed byte length, CRC, compression method, and diagnostics for missing `office:version`, unexpected roots, missing package parts, undeclared XML parts, and manifest-version mismatches.
- This is package-ingestion provenance only; it does not invoke Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, or live provider tests.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 3862 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 64220 assertions, 0 failures`

## Parity Accounting

- `phpPass` moves `3077 -> 3078`.
- `phpFail` remains `0`.
