# Pandoc EPUB NCX Spine TOC Binding

Hook: `plib-gs04s`

Scope: compact native PHP EPUB3 package ingestion now preserves raw OPF spine `toc` binding provenance for review packets.

`EpubPackage` now records raw spine `toc` metadata, classifies empty `toc=""` bindings as `empty-spine-toc-attribute` package-validation diagnostics, and exposes the compact `ncxSpineTocBinding` review record in `wordpressImport`. Empty, missing, and non-NCX toc bindings still fall back to a manifest NCX scan when a valid NCX item exists.

This slice does not invoke Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Counters:

- `phpPass`: `3652 -> 3653`
- `phpFail`: `0`
- `mappedEpubNcxSpineTocBindingCases`: `1`
- `epubNcxSpineTocBindingAssertions`: `34`

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - `1` file, `2717` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46` files, `86174` assertions, `0` failures
