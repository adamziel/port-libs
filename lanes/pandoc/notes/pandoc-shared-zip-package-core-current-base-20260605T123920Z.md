# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T123920Z`
Base accepted HEAD: `b075679df11f2da22eb4cf1f317dbce011ea97e8`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded ZIP end-of-central-directory archive-layout preflight.
- `ZipPackage::endOfCentralDirectoryPreflight()` now summarizes raw package
  EOCD metadata before full package import: EOCD offset, disk fields,
  central-directory entry counts, central-directory offset/size/end, package
  comment bytes/length, single-disk support, ZIP64 marker state, and whether
  the bounded reader can support the archive layout.
- `ZipPackage::archivePreflight()` exposes the same layout metadata for loaded
  packages and adds central-directory digital-signature presence, offset, and
  length.
- Focused tests prove ordinary single-disk DOCX/ODT/EPUB-style packages remain
  readable while split-disk and ZIP64-marker EOCD records are visible to
  preflight and rejected before package entries are handed to import paths.
- The WordPress ZIP package preflight smoke now reports archive layout offsets,
  entry counts, single-disk support, split-disk policy, and ZIP64 EOCD policy.

## Source Truth

Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed containers. ZIP package
readers need the EOCD record to locate the central-directory inventory and to
reject spanned or ZIP64 archives before exposing part names or media bytes.
This slice ports only that bounded format contract in native PHP; it does not
implement spanning archives, ZIP64 large-archive decoding, or external archive
extraction.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, zip/unzip, Word,
LibreOffice, office tooling, external archive tool, external conversion
service, online sanitizer, or online service was used.

## Verification

Baseline focused check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 359 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 385 assertions, 0 failures
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

- Focused ZIP PASS cases: `49 -> 50`, adding 1 PASS case.
- Focused ZIP assertions: `359 -> 385`, adding 26 assertions.
- Manifest mapped checks: `1356 -> 1357`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 157`.
- Lane PHP pass count: `898 -> 899`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, `ZipPackageEntry`, in-process CRC/DEFLATE handling, package
preflight example, and focused PHP test harness. Full upstream Pandoc runner
parity remains gated on hydrating/building the pinned Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but ZIP EOCD archive-layout
preflight is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, local entry order,
data descriptors, CRC/local-header integrity, central/local extra-field
parsing, extended or NTFS timestamps, ZIP64 extra-field rejection, Unix symlink
rejection, raw/decoded unsafe path rejection, directory payload rejection,
local-entry overlap/slack rejection, duplicate local-header-offset rejection,
aggregate size preflight, Unix executable preflight, package comment preflight,
central-directory digital-signature provenance, bounded extraction-version
policy, compression-method preflight, bounded per-entry reads, gzip/tar/LZ4
archive streams, OPC relationships, DOCX/ODT/EPUB readers, table geometry,
doctemplates, math/TeX, syntax highlighting, or Markdown/HTML reader/writer
behavior. It owns only EOCD archive-layout summary and split/ZIP64 marker
policy before package import.

## Follow-Up

Keep full ZIP64 large-archive support, spanning archive support, non-deflate
decompressor implementation, AES/encrypted payload handling, cryptographic
central-directory signature validation, and reader-level default archive
policy wiring as separate bounded ZIP package slices unless a concrete
DOCX/ODT/EPUB fixture requires one earlier.
