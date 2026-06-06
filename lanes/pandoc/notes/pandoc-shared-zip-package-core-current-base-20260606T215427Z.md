# Pandoc ZIP Package Core Current-Base Handoff

Slice: `pandoc-shared-zip-package-core-current-base-20260606T215427Z`
Accepted base: `dee21061aaf1fbb0aab4f4e3f945291f29676e20`

## Behavior

Implemented one bounded ZIP package primitive for Office/EPUB/ODT package preflight: `ZipPackage::zip64ExtraFieldPreflight()` scans raw central-directory and local-header metadata for ZIP64 extended-information extra fields before the bounded reader tries to expose package entries. It reports which ZIP32 sentinel fields require ZIP64 values (`uncompressedSize`, `compressedSize`, `localHeaderOffset`, `diskStart`), parses the corresponding 64-bit or 32-bit values, surfaces unneeded or trailing ZIP64 extra-field bytes, and marks the entry unsupported while preserving the existing fail-closed ZIP64 import behavior.

The reader still rejects ZIP64 package extraction. This is a diagnostic and planning primitive so DOCX, EPUB, ODT, and WordPress review paths can explain large-package metadata instead of reporting only a generic unsupported archive failure.

## Source Truth

This follows the ZIP APPNOTE extended-information extra-field layout already reflected by the lane's ZIP64 EOCD and ZIP64 descriptor preflights: central-directory ZIP64 fields are present only for corresponding max-value size, offset, and disk fields, in the order uncompressed size, compressed size, local header offset, disk start. Local headers use the same size placeholders for uncompressed and compressed sizes. No external archive tools were used.

## Evidence

Baseline:
`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
Result: `1 test files, 944 assertions, 0 failures`

After implementation:
`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
Result: `1 test files, 977 assertions, 0 failures`

Example smoke:
`php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
Result: `zip package writer preflight self-test passed`

Status delta: +1 focused PHP PASS case, +33 focused assertions, manifest mapped ZIP package support cases `1819 -> 1820`, and `phpPass` `1406 -> 1407`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`, `ZipPackageEntry`, raw central/local header scanning, ZIP64 extended-information extra-field decoding, and the existing focused PHP test harness. Full ZIP64 EOCD planning, ZIP64 package expansion/extraction, encrypted/AES payload handling, non-deflate decompression, cryptographic signature verification, and reader-level policy wiring remain separate bounded follow-up work.

## Non-Overlap

This patch does not repeat accepted ZIP central-directory signature provenance, NTFS/extended timestamp preflight, Unix symlink/special-file rejection, path/case/Unicode collision checks, invalid DOS timestamp preflight, trailing deflate validation, ZIP64 EOCD/locator detection, ZIP64 data-descriptor rejection, or general-purpose flag preflight. It only adds explainable ZIP64 extended-information extra-field planning while keeping import fail-closed.
