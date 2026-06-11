# Pandoc EPUB3 Spine Itemref Duplicate Provenance

Slice: `plib-omeu2` / EPUB3 package ingestion.

Implemented a bounded native PHP package-validation handoff for duplicate OPF
`spine/itemref` IDs. `EpubPackage` now reports duplicate itemref IDs as
package-validation diagnostics while preserving every reading-order item,
including idrefs, package part names, linear tokens, and repeated-idref
provenance for reviewer queues.

Repeated `idref` rows are metadata-only provenance, not automatic validation
failures, so compact ingestion does not collapse legal repeated reading-order
entries.

No Pandoc, EPUBCheck, office suite, TeX/browser engine, zip/unzip, external
validator, online service, live provider test, or live-service provider test was
used.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1573 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66712 assertions, 0 failures

Parity accounting:

- `phpPass`: `3133 -> 3134`
- `mappedEpubSpineItemrefDuplicateCases`: `1`
- `epubSpineItemrefDuplicateAssertions`: `28`
