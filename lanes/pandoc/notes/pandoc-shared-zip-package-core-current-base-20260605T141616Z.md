# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T141616Z`
Base: `9700d9b1bd119a0dd921be7ebc5da28ba3672c98`

## Change

- Added bounded ZIP64 end-of-central-directory locator and record preflight to
  `ZipPackage::endOfCentralDirectoryPreflight()`.
- `ZipPackage::fromString()` now rejects ZIP64 EOCD records explicitly before
  DOCX, EPUB, ODT, or WordPress media import paths expose package bytes.
- The WordPress ZIP package preflight smoke now reports
  `zip64LocatorPolicy`, `zip64LocatorDetected`, and
  `zip64LocatorRecordSize`.
- Updated the Pandoc lane manifest/status counters for one new mapped ZIP
  support case.

## Source Truth

Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed containers. ZIP64 package
trailers are part of the ZIP archive contract, not document content. This
bounded native PHP package reader still does not implement ZIP64 large-archive
loading, but it now classifies ZIP64 EOCD locator metadata directly instead of
treating the trailer as arbitrary central-directory slack.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, zip/unzip, Word,
LibreOffice, external archive tool, external office tool, external conversion
service, online sanitizer, or online service was used.

## Verification

Baseline focused check before adding the new case:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 391 assertions, 0 failures
```

Red-first focused check after adding the test and before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
PHP Warning: Undefined array key "hasZip64EndOfCentralDirectoryLocator"
FAIL preflights zip64 end of central directory locator before package import
1 test files, 394 assertions, 1 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 399 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Syntax, JSON, and diff checks:

```text
php -l lanes/pandoc/src/ZipPackage.php
No syntax errors detected in lanes/pandoc/src/ZipPackage.php

php -l lanes/pandoc/tests/ZipPackageTest.php
No syntax errors detected in lanes/pandoc/tests/ZipPackageTest.php

php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php
No syntax errors detected in lanes/pandoc/examples/wordpress-zip-package-preflight.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'
lanes/pandoc/lane-status.json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok

git diff --check -- lanes/pandoc
```

Root harness: not run - isolated micro-slice.

## Delta

- Focused ZIP PASS cases: `52 -> 53`, adding 1 PASS case.
- Focused ZIP assertions: `391 -> 399`, adding 8 assertions.
- Manifest mapped checks: `1396 -> 1397`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 139`.
- Lane PHP pass count: `940 -> 941`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, `ZipPackageEntry`, in-process CRC/DEFLATE handling, package
preflight example, and focused PHP test harness. Full upstream Pandoc runner
parity remains gated on hydrating/building the pinned Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but ZIP64 EOCD locator preflight
is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, local entry order,
data descriptors, CRC/local-header name integrity, central/local extra-field
parsing, extended or NTFS timestamps, ZIP64 extra-field rejection, Unix symlink
rejection, raw/decoded unsafe path rejection, directory payload rejection, DOS
directory-attribute consistency, local-entry overlap/slack rejection, duplicate
local-header-offset rejection, aggregate size preflight, Unix executable
preflight, package comment preflight, central-directory digital-signature
provenance, classic EOCD archive-layout marker preflight, bounded
extraction-version policy, compression-method preflight, stored-entry size
consistency, bounded per-entry reads, gzip/tar/LZ4 archive streams, OPC
relationships, DOCX/ODT/EPUB readers, table geometry, doctemplates, math/TeX,
syntax highlighting, or Markdown/HTML reader/writer behavior. It owns only
ZIP64 EOCD locator/record classification and explicit bounded-reader rejection.

## Follow-Up

Keep full ZIP64 large-archive support, spanning archive support, non-deflate
decompressor implementation, AES/encrypted payload handling, cryptographic
central-directory signature validation, and reader-level default archive policy
wiring as separate bounded ZIP package slices unless a concrete DOCX/ODT/EPUB
fixture requires one earlier.
