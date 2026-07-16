# EPUB Binding Handler Dependency Inventory

Slice: `pandoc-epub-binding-handler-dependency-inventory`

`EpubPackage` now includes OPF `bindings/mediaType@handler` references in
`manifestDependencyInventory` and compact package review rows. The dependency
inventory reports `binding-handler` edges with local ZIP byte provenance,
external-target policy, missing-handler diagnostics, target relation usability,
and WordPress import handoff fields.

This stays under `lanes/pandoc` and does not invoke Pandoc, EPUBCheck,
zip/unzip, ZipArchive, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - `1 test files, 3270 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 88336 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`

Metric movement:

- `phpPass`: `3723 -> 3724`
- `phpFail`: `0`
- Mapped upstream manifest cases: `3742 -> 3743`
- `mappedEpubManifestDependencyInventoryCases`: `1 -> 2`
- `epubManifestDependencyInventoryAssertions`: `57 -> 120`
