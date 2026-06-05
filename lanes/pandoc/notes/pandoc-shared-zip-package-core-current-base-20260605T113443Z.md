# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T113443Z`
Base accepted HEAD: `651615e05fea9d010bb9bbcaa297afe05c6cf991`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added a bounded central-directory extraction-version policy to
  `ZipPackage`.
- ZIP entries whose `version needed to extract` exceeds `20` now fail before
  package entries are exposed to DOCX, ODT, EPUB, or WordPress media handoff
  paths.
- Stored and deflated packages with extraction versions `10` and `20` remain
  supported, including existing data-descriptor and metadata preflight paths.
- The WordPress ZIP/package preflight example now reports unsupported
  extraction-version metadata as a rejected package policy.

## Source Truth

Pandoc package readers only need bounded DOCX/ODT/EPUB ZIP primitives for this
lane: stored entries, deflated entries, central-directory inventory, local
header integrity, metadata preflight, and safe byte reads. ZIP64, BZip2, LZMA,
AES/encrypted payloads, and other higher-version ZIP features are outside this
bounded native reader. Failing closed on `version needed to extract > 20`
keeps those archives explicit rather than exposing entries whose payload format
requires unsupported ZIP features.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, zip/unzip, Word,
LibreOffice, office tooling, external archive tool, external conversion
service, online sanitizer, or online service was used.

## Verification

Baseline focused check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 338 assertions, 0 failures
```

Red-first focused check after adding expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 339 assertions, 1 failures
Failure: Expected exception RuntimeException was not thrown
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 341 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php | rg -c '^PASS '
48
```

Focused delta over the previous ZIP package run: `338 -> 341` assertions
(`+3`) and `47 -> 48` focused PASS cases (`+1`).

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
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but unsupported ZIP
extraction-version rejection is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, local entry order,
data descriptors, CRC/local-header integrity, central/local extra-field
parsing, extended or NTFS timestamps, ZIP64 extra-field rejection, Unix symlink
rejection, raw/decoded unsafe path rejection, directory payload rejection,
local-entry overlap/slack rejection, duplicate local-header-offset rejection,
aggregate size preflight, Unix executable preflight, package comment preflight,
bounded per-entry reads, gzip/tar/LZ4 archive streams, central-directory
digital-signature provenance, OPC relationships, DOCX/ODT/EPUB readers, table
geometry, doctemplates, math/TeX, syntax highlighting, or Markdown/HTML
reader/writer behavior. It owns only bounded rejection of unsupported ZIP
`version needed to extract` metadata before package import.

## Follow-Up

Keep non-deflate decompression methods, AES/encrypted payload support,
spanning archives, cryptographic central-directory signature validation, and
full ZIP64 large-archive support as separate bounded ZIP package slices unless
a concrete DOCX/ODT/EPUB fixture requires them.
