# EPUB link media type parameter slice 2026-06-11T231510Z

Bead: `plib-xhr7e`

Current base: `86846d38d8`

## Summary

EPUB3 reader package ingestion now preserves MIME parameter provenance for package-level link records. OPF metadata links, OPF collection links, and OCF container links expose raw media types, base media types, normalized media types, parameter maps, parameter names/counts, syntax validity, and link-scoped diagnostics. Collection link reports also count parameterized and invalid link media types for package-review queues.

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` -> 1 file, 4062 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 files, 67021 assertions, 0 failures

No Pandoc, EPUBCheck, zip/unzip, office suite, TeX/browser engine, Jupyter, Node tooling, external validator, online service, live provider test, or live-service provider test was invoked.
