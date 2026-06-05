# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T131038Z`
Base accepted HEAD: `cd196967e8c8e83a859b41ec0f33fc1ed9443b77`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded stored-entry size integrity preflight to `ZipPackage`.
- `ZipPackage::fromString()` now rejects stored method-0 entries whose central
  and local metadata declare different compressed and uncompressed sizes before
  DOCX, EPUB, ODT, or WordPress package readers see the entry inventory.
- Valid stored package media still reads normally.
- The WordPress ZIP package preflight smoke now reports
  `zipStoredSizeMismatchPolicy=rejected` for malformed stored media metadata.

## Source Truth

Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed containers. For stored ZIP
entries, compressed bytes are the uncompressed payload bytes, so mismatched
compressed/uncompressed size metadata is a malformed package layout. This slice
ports that bounded ZIP format contract in native PHP and blocks the malformed
entry during package preflight instead of delaying the error until `read()`.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, zip/unzip, Word,
LibreOffice, external archive tool, external office tool, external conversion
service, online sanitizer, or online service was used.

## Verification

Baseline focused check before adding the new case:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 385 assertions, 0 failures
```

Red-first focused check after adding the test and before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
FAIL rejects stored zip entry size mismatches before package import preflight
Expected exception RuntimeException was not thrown
1 test files, 386 assertions, 1 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 387 assertions, 0 failures
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

- Focused ZIP PASS cases: `50 -> 51`, adding 1 PASS case.
- Focused ZIP assertions: `385 -> 387`, adding 2 assertions.
- Manifest mapped checks: `1371 -> 1372`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 133`.
- Lane PHP pass count: `913 -> 914`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, `ZipPackageEntry`, in-process CRC/DEFLATE handling, package
preflight example, and focused PHP test harness. Full upstream Pandoc runner
parity remains gated on hydrating/building the pinned Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but stored ZIP entry size
integrity preflight is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, local entry order,
data descriptors, CRC/local-header name integrity, central/local extra-field
parsing, extended or NTFS timestamps, ZIP64 extra-field rejection, Unix symlink
rejection, raw/decoded unsafe path rejection, directory payload rejection,
local-entry overlap/slack rejection, duplicate local-header-offset rejection,
aggregate size preflight, Unix executable preflight, package comment preflight,
central-directory digital-signature provenance, EOCD archive-layout preflight,
bounded extraction-version policy, compression-method preflight, bounded
per-entry reads, gzip/tar/LZ4 archive streams, OPC relationships, DOCX/ODT/EPUB
readers, table geometry, doctemplates, math/TeX, syntax highlighting, or
Markdown/HTML reader/writer behavior. It owns only stored method-0 entry size
consistency before package import.

## Follow-Up

Keep full ZIP64 large-archive support, spanning archive support, non-deflate
decompressor implementation, AES/encrypted payload handling, cryptographic
central-directory signature validation, and reader-level default archive policy
wiring as separate bounded ZIP package slices unless a concrete DOCX/ODT/EPUB
fixture requires one earlier.
