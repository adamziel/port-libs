# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T110224Z`
Base accepted HEAD: `9586f7b81deef73283e1d5ac369075b87b707fbd`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded ZIP central-directory digital signature record parsing.
- `ZipPackage` now preserves signature record bytes when the EOCD central
  directory size either excludes the signature record or includes it as part of
  the central-directory tail.
- `centralDirectorySignaturePreflight()` exposes presence, offset, byte length,
  raw signature bytes, and an explicit `not-performed-native-bounded-reader`
  cryptographic-verification status for reviewer provenance.
- Malformed signature records whose declared length leaves trailing bytes still
  fail before package entries are exposed.
- The WordPress ZIP preflight example now treats central-directory signature
  metadata as inspectable package provenance instead of rejecting otherwise
  well-formed DOCX/ODT/EPUB-style package inventories.

## Source Truth

ZIP central-directory digital signature records use signature `0x05054b50`,
a 16-bit payload length, and payload bytes placed after the central file header
records and before EOCD. Pandoc package readers need the package inventory and
media bytes for DOCX, ODT, and EPUB handoff; this slice ports only the bounded
format contract and provenance exposure. It does not implement cryptographic
trust validation or external archive extraction.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, zip/unzip, Word,
LibreOffice, office tooling, external archive tool, external conversion
service, online sanitizer, or online service was used.

## Verification

Baseline focused check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 327 assertions, 0 failures
```

Focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 338 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php | rg -c '^PASS '
47
```

Focused delta over the previous ZIP package run: `327 -> 338` assertions
(`+11`) and `46 -> 47` focused PASS cases (`+1`).

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Syntax and JSON checks:

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

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, `ZipPackageEntry`, in-process CRC/DEFLATE handling, package
preflight example, and focused PHP test harness. Full upstream Pandoc runner
parity remains gated on hydrating/building the pinned Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but central-directory signature
record provenance is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, local entry order,
data descriptors, CRC/local-header integrity, central/local extra-field
parsing, extended or NTFS timestamps, ZIP64 extra-field rejection, Unix symlink
rejection, raw/decoded unsafe path rejection, directory payload rejection,
local-entry overlap/slack rejection, duplicate local-header-offset rejection,
aggregate size preflight, Unix executable preflight, package comment preflight,
bounded per-entry reads, gzip/tar/LZ4 archive streams, OPC relationships,
DOCX/ODT/EPUB readers, table geometry, doctemplates, math/TeX, syntax
highlighting, or Markdown/HTML reader/writer behavior. It owns only bounded
ZIP central-directory digital signature record provenance plus malformed
signature-tail rejection.

## Follow-Up

Keep AES/encrypted payload support, spanning archives, cryptographic validation
of central-directory signatures, full ZIP64 large-archive support, and
non-deflate compression methods as separate bounded ZIP package slices unless a
concrete DOCX/ODT/EPUB fixture requires them.
