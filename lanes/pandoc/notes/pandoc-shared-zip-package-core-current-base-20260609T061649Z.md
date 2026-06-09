# Pandoc ZIP Package Core Current-Base Slice

Session: `port-dev-pandoc-zip-package-20260609T061649Z`
Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T061649Z`
Base accepted HEAD: `54e4f08a09f2e83c9a94575366cb4582953b41b9`

## Behavior

ZIP central-directory file headers carry a relative offset to each entry local
file header. For DOCX/ODT/EPUB package handoff, two central-directory records
that point to the same local header can make one payload appear as multiple
Office media parts. This slice keeps package construction blocked, but now also
surfaces structured pre-instantiation evidence:

- `ZipPackage::centralDirectoryInventoryPreflight()` reports duplicate local
  header offset groups with entry names, central-directory indexes, and central
  directory record offsets.
- `ZipPackage::rawStrictImportPreflight()` includes the inventory and the
  `central-directory-duplicate-local-header-offsets` diagnostic even when
  `ZipPackage::fromString()` refuses to instantiate the archive.
- `wordpress-zip-package-preflight.php` self-test covers the WordPress import
  preflight path without invoking `zip`, `unzip`, Pandoc, Office tools, or any
  external converter.

## Evidence

Baseline before the slice:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 2687 assertions, 0 failures
```

Focused verification after the slice:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 2706 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Root harness: not run - isolated micro-slice.

## Delta

- Added 1 focused PHP PASS case.
- Added 19 focused assertions in `ZipPackageTest.php`.
- Increased mapped ZIP package core support cases from 22 to 23.
- Increased mapped denominator from 2824 to 2825.

## Dependency Closure

No new support component is needed. This reuses the existing native ZIP
central-directory inventory scanner, raw strict import preflight, and WordPress
ZIP preflight example. Full upstream Pandoc package runner parity remains a
separate upstream-runner dependency task.

## Non-Overlap

This does not repeat the accepted ZIP clusters for raw extra-field ID/value
policy, Unicode path/comment extras, ZIP64 sentinel handling, central-directory
repair plans, data descriptor integrity, platform metadata, name hygiene,
permissions, comments, or symlink/special-file rejection. The new behavior is
specifically central-directory records sharing a local header offset before
package construction.
