# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T095746Z`
Base accepted HEAD: `f277d8c62948bf03fc46bb2f8adb59a7a1bac47e`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded ZIP general-purpose flag preflight during central-directory
  parsing.
- `ZipPackage::fromString()` now rejects entries marked with enhanced-deflate,
  compressed-patched-data, or unknown/reserved general-purpose flag bits before
  DOCX/EPUB/ODT readers can expose package part names or bytes.
- Supported metadata bits remain accepted: UTF-8 names/comments, data
  descriptors, and deflate compression option bits.
- The WordPress ZIP package preflight smoke now records compressed-patched-data
  rejection as an import policy before media attachment handoff.

## Source Truth

Pandoc consumes DOCX, EPUB, and ODT as ZIP-backed containers. ZIP general
purpose bits can alter payload interpretation beyond ordinary stored/deflated
file data. This bounded native PHP reader only supports stored/deflated entries,
UTF-8 metadata, data descriptors, and harmless deflate option bits; patch-data
and enhanced-deflate envelopes must stay blocked until separately implemented.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, Word, LibreOffice,
office tooling, `zip`, `unzip`, tar CLI, LZ4 CLI, TeX/PDF engine, external
template engine, browser renderer, online sanitizer, or online service was
executed.

## Verification

Baseline focused check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 307 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 312 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Syntax and diff checks:

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

- Focused ZIP PASS cases: `44 -> 45`, adding 1 PASS case.
- Focused ZIP assertions: `307 -> 312`, adding 5 assertions.
- Manifest mapped checks: `1279 -> 1280`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 136`.
- Lane PHP pass count: `819 -> 820`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage` and `ZipPackageEntry` primitives, in-process CRC/DEFLATE handling,
the accepted data-descriptor path, and the WordPress ZIP package preflight
example. Full upstream Pandoc runner parity remains gated on hydrating the
pinned Pandoc checkout with Cabal project/package files.

## Non-Overlap

This does not repeat accepted central-directory parsing, package writing, local
entry order exposure, data descriptors, CRC/local-header integrity checks,
central/local extra-field parsing, extended or NTFS timestamp metadata, ZIP64
extra-field rejection, Unix symlink rejection, Unix executable permission
preflight, raw/decoded unsafe path rejection, directory payload rejection,
local-entry overlap rejection, duplicate local-header-offset rejection,
central-directory tail rejection, aggregate size preflight, ZIP version-needed
exposure, bounded per-entry reads, gzip/tar/LZ4 archive streams, OPC
relationships/content types, DOCX/ODT/EPUB readers, syntax highlighting, table
geometry, math/TeX, doctemplates, legacy DOC/CFB, or Markdown/HTML reader and
writer behavior. It only adds bounded ZIP general-purpose flag policy for
unsupported payload interpretation bits.

## Follow-Up

Keep AES/encrypted payload support, spanning archives, verified
central-directory signature parsing, full ZIP64 large-archive support, default
reader size-limit policies, and non-deflate compression methods as separate
bounded ZIP package slices unless a concrete DOCX/ODT/EPUB fixture requires
one earlier.
