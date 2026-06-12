# Pandoc EPUB3 collection link vocabulary slice

- Bead: `plib-7fqd7`
- Scope: EPUB3 package ingestion, OPF collection link vocabulary provenance
- Base after rebase: `a2f1793134`
- Focused verification: `php -l lanes/pandoc/src/EpubPackage.php`; `php -l lanes/pandoc/tests/EpubPackageTest.php`; `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` (1 test file, 1689 assertions, 0 failures)
- Full verification: `php tools/run-tests.php lanes/pandoc/tests` (44 test files, 68183 assertions, 0 failures)

OPF collection links now receive the same inert vocabulary token provenance as package metadata links. The handoff resolves package prefix bindings for collection `rel` and `properties` tokens, preserves absolute URL-with-fragment values, and reports invalid, duplicate, and unknown-prefix diagnostics without fetching remote resources or invoking Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests.
