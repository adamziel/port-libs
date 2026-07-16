# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T103005Z`
Base accepted HEAD: `17084c137d0018e6cf17e49bcac91c3e1cb47745`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added `ZipPackage::commentPreflight()` for bounded ZIP package provenance.
- The preflight decodes raw package comments as UTF-8 when valid and CP437
  otherwise, preserving the raw EOCD comment bytes and an encoding label.
- Per-entry comment summaries now expose decoded comments, raw central
  directory comment bytes, encoding labels, byte lengths, and a commented-entry
  subset for reviewer queues.
- Generated package comments are now required to be valid UTF-8 before writing,
  matching the existing generated entry-name and entry-comment policy.
- The WordPress ZIP package preflight smoke now reports package comment
  encoding and commented entry names.

## Source Truth

Pandoc DOCX, EPUB, and ODT conversion paths consume ZIP-backed containers where
central-directory metadata is the package inventory. ZIP comments are metadata,
not payload bytes, but they are still user-visible provenance in review packets.
This slice keeps the accepted native PHP ZIP reader/writer contract bounded to
metadata preflight and does not perform filesystem extraction.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, `zip`, `unzip`, Word,
LibreOffice, office tooling, external conversion service, or online service was
used.

## Verification

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 312 assertions, 0 failures
```

Focused red-first check after adding the test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
Call to undefined method PortLibs\Pandoc\ZipPackage::commentPreflight()
Expected exception RuntimeException was not thrown
1 test files, 313 assertions, 2 failures
```

Focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 327 assertions, 0 failures
```

Full focused Pandoc lane verification:

```text
php tools/run-tests.php lanes/pandoc/tests
20 test files, 10305 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '
822
```

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

## Delta

- Focused ZIP assertions: `312 -> 327`, adding 15 assertions.
- Focused ZIP PASS cases: adds 1 PASS case.
- Manifest mapped checks: `1295 -> 1296`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 146`.
- Lane PHP pass count: `835 -> 836`.

## Dependency Closure

No new support component is needed. This reuses the existing lane-local
`ZipPackage` and `ZipPackageEntry` primitives plus the accepted WordPress ZIP
package preflight smoke. Full upstream Pandoc runner parity remains gated on
hydrating the pinned Pandoc checkout with Cabal project/package files, but ZIP
comment provenance preflight is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, package writing, local
entry order exposure, data descriptors, CRC/local-header checks, local-header
eager integrity, central/local extra-field parsing, extended or NTFS timestamp
handling, ZIP64 extra-field rejection, Unix symlink rejection, Unix executable
permission preflight, raw/decoded unsafe path rejection, directory payload
rejection, local-entry overlap/slack rejection, duplicate local-header-offset
rejection, unsupported compression method read-time rejection, central-directory
tail rejection, bounded reads, package size preflight, gzip/tar/LZ4 archive
streams, OPC relationships/content types, DOCX/ODT/EPUB readers, or
Markdown/HTML reader and writer behavior. It only adds package and entry
comment provenance preflight plus generated package-comment UTF-8 validation.

## Follow-Up

Keep verified central-directory signature parsing policy, full ZIP64
large-archive support, AES/encrypted payload policy, spanning archive policy,
non-deflate compression methods, default reader size-limit policy, and
reader-level wiring for `commentPreflight()` / `assertNoExecutableFiles()` as
separate bounded ZIP package slices.
