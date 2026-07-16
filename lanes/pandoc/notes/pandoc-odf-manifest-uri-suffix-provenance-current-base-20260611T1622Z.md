# ODF/ODT manifest URI suffix provenance current-base slice

- Bead: `plib-j05yy`
- Scope: ODF/ODT OpenDocument package ingestion.
- Refreshed base: `289d971733b332cc57d6cc8ad9a2b28031440a72`.
- ODT manifest entries now retain package-part URI provenance: raw part reference, query, fragment, and combined suffix while still resolving bytes through the stripped safe ZIP package part.
- Media reports, package thumbnail metadata, and package provenance carry the same manifest suffix metadata for reviewer handoff.
- Duplicate manifest entries that resolve to the same stored package part remain blocked before package exposure.
- No Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests invoked.

Verification:
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 3840 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 63884 assertions, 0 failures`

Accounting:
- `phpPass`: `3068 -> 3069`
