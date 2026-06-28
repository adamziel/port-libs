# ODF Basic/Dialog XLB script indexes

Slice: `plib-02uv3` (`2026-06-28`).

## Scope

- Rich `OdfReader` and compact `OpenDocumentPackage` package review now treat
  Basic/Dialog `*.xlb` library indexes as XML-backed
  `basic-library-index` script sidecars.
- `Basic/Standard/script.xlb` and `Dialogs/Standard/dialog.xlb` remain blocked
  under `script-package-bytes-blocked` and stay out of document media and
  WordPress handoff.
- Declared package rows, ZIP inventory roles, rich script metadata, and compact
  manifest review rows keep the same metadata-only provenance as existing
  `script-lb.xml` and `dialog-lb.xml` index files.

## Direct-Format Accounting

- Extended the rich script package fixture with `Basic/Standard/script.xlb`.
- Extended the compact/rich dialog package sidecar fixture with
  `Dialogs/Standard/dialog.xlb`.
- Extended the compact package review fixture to keep `.xlb` indexes aligned
  with existing Basic library index accounting.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php`
- `php -l lanes/pandoc/tests/OdfDialogPackageSidecarTest.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php lanes/pandoc/tests/OdfDialogPackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 3 test files, 2,234 assertions, 0 failures

No Pandoc, office suites, TeX/browser engines, unzip/zip commands, Jupyter,
Node tooling, external validators, or online services were invoked.
