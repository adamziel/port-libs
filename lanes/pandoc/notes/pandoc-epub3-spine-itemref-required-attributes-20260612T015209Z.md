# Pandoc EPUB3 Spine Itemref Required Attributes

Slice: `plib-hyajy`

This slice makes compact EPUB package ingestion tolerate malformed OPF spine
`itemref` entries that omit `idref`.

- `EpubPackage::parseSpine()` now preserves missing-`idref` itemrefs instead of
  throwing before package review can inspect the EPUB.
- Spine validation reports missing required itemref attributes with
  `missing-spine-itemref-idref`, aggregate counts, affected indexes, and
  attribute names.
- WordPress import handoff exposes the spine missing-required-attribute summary
  alongside existing spine item diagnostics.

Focused coverage lives in `lanes/pandoc/tests/EpubPackageTest.php`.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1784 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 68787 assertions, 0 failures
