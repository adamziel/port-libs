# EPUB Rootfile Media-Type Diagnostics Slice

- Bead: `plib-xtz0b`
- Base: current `origin/main` at `896de35817`
- Scope: `lanes/pandoc` EPUB3 package ingestion

## Change

EPUB OCF container rootfiles now promote media-type syntax diagnostics into the package validation result. Malformed rootfile parameters and duplicate parameter names are recontextualized as rootfile diagnostics instead of inert manifest wording, while OPF rootfile selection still uses the valid base media type.

WordPress package review summaries now expose rootfile media-type parameter items, parameter names, and rootfile media-type diagnostics directly next to the existing manifest media-type review arrays.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
