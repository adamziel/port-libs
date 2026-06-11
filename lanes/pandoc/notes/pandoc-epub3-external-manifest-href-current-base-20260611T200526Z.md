# EPUB3 External Manifest Href Ingestion

- Bead: `plib-ki3y6`
- Base: current `origin/main` `a0fb299ad0`
- Scope: `lanes/pandoc` EPUB3 package ingestion only.

## Change

`EpubPackage` now preserves absolute OPF manifest `href` values as external, non-fetched manifest items instead of aborting package ingestion. External manifest rows keep their target URL, use `partName: null`, block byte exposure, and surface `external-manifest-href-target` diagnostics through manifest validation and WordPress package-validation summaries.

The asset summary and manifest part lookup now skip non-package manifest targets so external assets do not appear as ZIP package parts.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1482 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66066 assertions, 0 failures
