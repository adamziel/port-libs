# ODF Office Document Part Version Provenance

Date: 2026-06-11T17:53:52Z
Bead: plib-q60oy
Base: 0f7efc602

This slice adds native PHP ODF/ODT package ingestion provenance for core office XML parts:

- `content.xml`
- `styles.xml`
- `meta.xml`
- `settings.xml`

`OdfReader` now exposes `officeDocumentParts` in the document manifest metadata and import-report manifest metadata. The report records manifest declaration state, expected and observed office root names, root namespace, `office:version`, aggregate version buckets, mixed-version state, and manifest-version mismatches.

Verification:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed 1 test file, 3898 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 64545 assertions, 0 failures.

No Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
