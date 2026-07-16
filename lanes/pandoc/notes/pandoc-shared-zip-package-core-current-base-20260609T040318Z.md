# Pandoc ZIP Package Core Current-Base Slice

Slice: `pandoc-shared-zip-package-core-current-base-20260609T040318Z`
Base accepted HEAD: `72a53fe4cb43f993ddc490102ccddab53f4ddfb1`

## Behavior

Added bounded hidden-span provenance to `ZipPackage::localHeaderSpanPreflight()` for ZIP local entry gaps that are not claimed by any central-directory entry.

The preflight still rejects these packages before DOCX/EPUB/ODT media handoff, but now each issue entry also exposes:

- `unclaimedBytesPreviewHex`
- `unclaimedBytesPreviewByteCount`
- `unclaimedBytesSignature`

The signature label recognizes common ZIP record signatures such as local file headers, central-directory headers, data descriptors, archive extra data records, ZIP64 EOCD records/locators, central-directory digital signatures, and EOCD records. This lets WordPress review queues distinguish an orphan local header from opaque slack without extracting hidden payloads or invoking external archive tools.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 2357 assertions, 0 failures`
  - Delta from this patch: `+1` focused PASS case and `+16` focused assertions.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage` central-directory and local-header span scanners plus the existing WordPress ZIP package preflight smoke.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`, `unzip`, `ZipArchive`, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted package-prefix/MZ-stub handling, central-directory recovery metadata, ZIP64 EOCD/extra-field accounting, Unicode name or extra-field policy, raw-name provenance, symlink/special-file rejection, local-header name/metadata mismatches, central-directory signatures, archive extra-data record policy, encryption/compression policy, or data-descriptor integrity. It only adds bounded preview/signature metadata for already rejected unclaimed local-entry span bytes.

## Next

Good follow-ups are DOCX/EPUB/ODT reader consumption of strict ZIP diagnostics, ZIP64/data-descriptor edge diagnostics not already covered, or remaining package media policy gaps with focused native PHP tests.
