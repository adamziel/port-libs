# ODF Manifest Preferred View Mode Provenance

Bead: `plib-8sznu`
Base: `2dda4a6ed`

This slice keeps ODF/ODT package review queues from scanning every manifest
file-entry to inspect `manifest:preferred-view-mode` declarations. The native
PHP reader now summarizes preferred-view-mode values in package provenance,
including aggregate mode lists, per-mode counts, affected file-entry rows,
manifest-order propagation, and per-part ZIP inventory fields.

Verification on 2026-06-11 UTC:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 test file, 4070 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66454 assertions, 0 failures

No Pandoc, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.
