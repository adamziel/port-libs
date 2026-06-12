# EPUB3 Reader Mimetype Entry Provenance

Bead: `plib-3n727`
Base: `02397c4c66`
Date: 2026-06-12 UTC

This slice keeps the full `EpubReader` package-ingestion handoff aligned with
the shared stored-first ZIP preflight already used for strict EPUB admission.
The reader now preserves the validated `mimetype` entry report instead of
discarding it after admission.

The report is exposed as top-level `mimetypeEntry`, mirrored in
`importReport['mimetypeEntry']`, and attached to the document attrs. It includes
local-header first-entry status, compression method/name, data-descriptor
status, extra-field IDs, byte counts, content match status, validity, and
diagnostics. Package acceptance and byte exposure behavior are unchanged.

Focused coverage extends the existing EPUB mimetype local-header-order fixture
to assert the report through all handoff surfaces.

Verification:

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - 1 test file, 4101 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 69055 assertions, 0 failures

No Pandoc, office suites, TeX/browser engines, unzip/zip, Jupyter, Node
tooling, external validators, online services, live provider tests, or
live-service provider tests were run.
