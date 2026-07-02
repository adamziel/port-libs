# ZIP Local Header Data Descriptor Provenance

Date: 2026-07-02
Bead: plib-4a52e

## Summary

`ZipPackage::localHeaderPreflight()` now carries metadata-only data descriptor provenance for shared ZIP/OPC package review. The preflight reports signed and unsigned descriptor counts, descriptor bytes, zero local-header placeholder counts, descriptor issue rollups, and per-entry descriptor offsets, spans, CRC32 values, ZIP64-sized-field flags, and central-directory match state.

This keeps direct local-header review aligned with the existing strict import and raw strict import handoff surfaces without exposing package payload bytes or invoking external ZIP tools.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` with 1 file, 6,117 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` with 1 file, 5,333 assertions, 0 failures

No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine, Typst, Jupyter, Node, zip/unzip, validators, or live services were invoked.
