# ODF Reader Manifest Version and View Package Provenance

Bead: `plib-t0es3`
Base: `4803abcaee`
Date: 2026-06-12 UTC

This slice carries parsed ODF manifest entry `manifest:version` and
`manifest:preferred-view-mode` values into the full `OdfReader` package
provenance surface. The manifest file-entry order review list now exposes
`version` and `preferredViewMode`, and declared ZIP package inventory entries
now expose `manifestVersion` and `manifestPreferredViewMode`.

The focused fixture keeps the existing reordered-manifest package review path
and verifies root, content, styles, and media-side provenance without changing
package acceptance or byte exposure policy.

Verification:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 test file, 4090 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 68928 assertions, 0 failures

No Pandoc, office suites, TeX/browser engines, unzip/zip, Jupyter, Node
tooling, external validators, online services, live provider tests, or
live-service provider tests were run.
