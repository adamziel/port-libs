# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T183448Z`
Base accepted HEAD: `491fa94b2ad9759bb28ac262b0ad00542377c4c9`
Date: 2026-06-05 UTC

No current Pandoc rework note was present under
`.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Behavior

- Added bounded Unix file-type classification to `ZipPackageEntry`.
- `ZipPackage::fromString()` and `ZipPackage::fromParts()` now reject Unix
  FIFO, character-device, block-device, socket, and unknown special-file
  entries before DOCX, ODT, EPUB, or WordPress media import paths expose them
  as package bytes.
- Regular-file and directory Unix modes remain valid, and non-Unix creator
  hosts keep their external-attribute bits as provenance only.
- The WordPress ZIP package preflight smoke now reports
  `zipUnixSpecialFilePolicy=rejected`.

## Source Truth

Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed containers. Those containers
should expose package files and directories to readers, not Unix filesystem
special objects encoded through central-directory external attributes. This
bounded native PHP support-library slice ports that ZIP package safety contract
without invoking Pandoc, `ZipArchive`, zip/unzip, Word, LibreOffice, or any
external archive/conversion tool.

## Verification

Baseline focused check before adding the new case:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 490 assertions, 0 failures
```

Red-first focused check after adding the case and before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
FAIL rejects unix special file zip entries before office package import preflight
Expected exception RuntimeException was not thrown
1 test files, 491 assertions, 1 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 504 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Syntax, JSON, and diff checks:

```text
php -l lanes/pandoc/src/ZipPackage.php
php -l lanes/pandoc/src/ZipPackageEntry.php
php -l lanes/pandoc/tests/ZipPackageTest.php
php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php
php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'
git diff --check -- lanes/pandoc
```

Root harness: not run - isolated micro-slice.

## Delta

- Focused ZIP package PASS cases: `58 -> 59`, adding 1 PASS case.
- Focused ZIP package assertions: `490 -> 504`, adding 14 assertions.
- Manifest mapped checks: `1488 -> 1489`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 145`.
- Lane PHP pass count: `1036 -> 1037`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, `ZipPackageEntry`, in-process CRC/DEFLATE handling, package
preflight example, and focused PHP test harness.

Full upstream Pandoc runner parity remains gated on hydrating/building the
pinned Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but
Unix special-file preflight is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, local entry order,
data descriptors, CRC/local-header integrity, central/local extra-field
parsing, duplicate extra-field IDs, extended or NTFS timestamps, ZIP64
extra-field or EOCD rejection, Unix symlink rejection, Unix executable
permission preflight, DOS directory-attribute consistency, drive-letter path
rejection, local-layout/slack rejection, central-directory signature
provenance, creator host-system preflight, strict comment policy, payload
read-integrity preflight, compression-method policy, archive-compression
streams, OPC relationships, DOCX/ODT/EPUB readers, table geometry,
doctemplates, math/TeX, syntax highlighting, or Markdown/HTML reader/writer
behavior. It owns only Unix special-file external-attribute rejection in the
shared ZIP package primitive.

## Follow-Up

Keep reader-level strict archive policy wiring, full ZIP64 large-archive
support, spanning archives, non-deflate decompressor implementation,
AES/encrypted payload handling, cryptographic central-directory signature
validation, and package-specific default media policies as separate bounded
slices unless a concrete DOCX/ODT/EPUB fixture requires one earlier.
