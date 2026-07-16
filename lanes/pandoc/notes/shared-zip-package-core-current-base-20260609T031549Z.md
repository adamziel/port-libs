# ZIP Package Prefix Preflight Slice

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T031549Z`
Accepted base: `66a83c16d67307dc6e017f1d9b83d8212b549eaa`

## Behavior

Added native ZIP package-prefix preflight coverage for ZIP-backed Pandoc package formats that have bytes before the first local file header. The new `ZipPackage::packagePrefixPreflight()` summary reports prefix byte count, preview hex, MZ executable stub detection, first local-header offset, central-directory and EOCD offsets after removing the prefix, local-header span issues with and without the prefix issue, and bounded-reader support status.

`ZipPackage::rawStrictImportPreflight()` now includes that package-prefix summary and adds explicit diagnostics for `package-prefix-bytes` and `package-prefix-mz-executable-stub`. Prefixed packages remain fail-closed for the bounded native reader.

## Verification

Baseline before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 2238 assertions, 0 failures
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 2272 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Syntax checks passed for the changed PHP files:

```text
php -l lanes/pandoc/src/ZipPackage.php
php -l lanes/pandoc/tests/ZipPackageTest.php
php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `ZipPackage` EOCD, central-directory, local-header span, raw strict import, and WordPress ZIP package preflight paths. It did not run or require Pandoc, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, PHP `ZipArchive`, external converters, online services, or live-service provider tests.

## Non-Overlap

This patch does not repeat stored-first mimetype data-descriptor handling, central-directory offset diagnostics, Unicode entry-name hygiene, external attribute policy, ZIP64 compatibility reporting, split archive policy, archive extra data records, encryption checks, compression-method checks, or data-descriptor integrity. It is limited to package-prefix/MZ-stub raw preflight and WordPress handoff diagnostics.

## Follow-Up

Next ZIP/OPC work should stay non-overlapping: central-directory recovery metadata beyond prefix policy, additional ZIP64 compatibility reporting, or DOCX/EPUB/ODT reader handoff code that consumes strict ZIP preflight diagnostics.
