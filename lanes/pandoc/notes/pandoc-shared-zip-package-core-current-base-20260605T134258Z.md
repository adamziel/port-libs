# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T134258Z`
Base accepted HEAD: `1ac2eb089e2f253a0d8b6eb3b2c58d8fdc918d15`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded DOS directory external-attribute/name consistency preflight to
  `ZipPackage`.
- `ZipPackage::fromString()` now rejects non-directory entry names carrying
  the DOS directory external attribute before DOCX, EPUB, ODT, or WordPress
  media import paths treat the entry as regular file bytes.
- `ZipPackageEntry::hasDosDirectoryAttribute()` exposes the bit-level metadata
  for focused preflight assertions and future package readers.
- Valid slash-suffixed directory entries with the DOS directory attribute still
  parse and read as empty directory entries.
- The WordPress ZIP package preflight smoke now reports
  `zipDosDirectoryAttributePolicy=rejected`.

## Source Truth

Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed containers. The native package
reader already uses the ZIP part name to decide whether an entry is a directory.
An entry whose name is not directory-shaped but whose external attributes mark
it as a directory is inconsistent package metadata, so this slice blocks it at
ZIP preflight instead of handing it to document/media import code as a file.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, zip/unzip, Word,
LibreOffice, external archive tool, external office tool, external conversion
service, online sanitizer, or online service was used.

## Verification

Baseline focused check before adding the new case:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 387 assertions, 0 failures
```

Red-first focused check after adding the test and before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
FAIL rejects zip DOS directory attributes without directory names before package import preflight
Expected exception RuntimeException was not thrown
1 test files, 388 assertions, 1 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 391 assertions, 0 failures
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

php -l lanes/pandoc/src/ZipPackageEntry.php
No syntax errors detected in lanes/pandoc/src/ZipPackageEntry.php

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

- Focused ZIP PASS cases: `51 -> 52`, adding 1 PASS case.
- Focused ZIP assertions: `387 -> 391`, adding 4 assertions.
- Manifest mapped checks: `1382 -> 1383`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 135`.
- Lane PHP pass count: `925 -> 926`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, `ZipPackageEntry`, in-process CRC/DEFLATE handling, package
preflight example, and focused PHP test harness. Full upstream Pandoc runner
parity remains gated on hydrating/building the pinned Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but ZIP directory-attribute
consistency preflight is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, local entry order,
data descriptors, CRC/local-header name integrity, central/local extra-field
parsing, extended or NTFS timestamps, ZIP64 extra-field rejection, Unix symlink
rejection, raw/decoded unsafe path rejection, directory payload rejection,
local-entry overlap/slack rejection, duplicate local-header-offset rejection,
aggregate size preflight, Unix executable preflight, package comment preflight,
central-directory digital-signature provenance, EOCD archive-layout preflight,
bounded extraction-version policy, compression-method preflight, stored-entry
size consistency, bounded per-entry reads, gzip/tar/LZ4 archive streams, OPC
relationships, DOCX/ODT/EPUB readers, table geometry, doctemplates, math/TeX,
syntax highlighting, or Markdown/HTML reader/writer behavior. It owns only
directory external-attribute consistency for non-directory ZIP entry names.

## Follow-Up

Keep full ZIP64 large-archive support, spanning archive support, non-deflate
decompressor implementation, AES/encrypted payload handling, cryptographic
central-directory signature validation, and reader-level default archive policy
wiring as separate bounded ZIP package slices unless a concrete DOCX/ODT/EPUB
fixture requires one earlier.
