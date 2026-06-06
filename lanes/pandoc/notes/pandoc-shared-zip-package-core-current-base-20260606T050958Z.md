# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260606T050958Z`
Base accepted HEAD: `dffb68d11b769f872d4da32f21b819394fad38ff`
Date: 2026-06-06 UTC

No current Pandoc rework note was present under
`.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Behavior

- `ZipPackage::dataDescriptorPreflight()` now reports local-header CRC and
  size placeholder values for entries that use ZIP data descriptors.
- `ZipPackage::fromString()` now rejects descriptor-backed entries when the
  local header carries nonzero CRC, compressed-size, or uncompressed-size
  placeholders before exposing DOCX, ODT, EPUB, or WordPress media bytes.
- Valid descriptor-backed entries keep existing signed/unsigned descriptor
  support and now expose `hasZeroLocalHeaderPlaceholders` provenance for review
  packets.
- The WordPress ZIP package preflight smoke now reports the zero-placeholder
  descriptor metadata and strict placeholder rejection policy.

Source truth: ZIP data descriptor entries carry authoritative CRC and size
metadata after the payload. For a bounded package reader used by Pandoc-style
DOCX/ODT/EPUB import, local-header CRC/size fields must remain unambiguous
zero placeholders when the descriptor flag is set; contradictory nonzero local
values are rejected instead of being hidden by central-directory metadata.

## Evidence

Baseline focused ZIP package verification before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 641 assertions, 0 failures
```

Focused ZIP package verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 659 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Root harness: not run - isolated micro-slice.

## Delta

- Focused ZIP package test cases: `65 -> 66`.
- Focused ZIP package assertions: `641 -> 659` (`+18`).
- Manifest mapped checks: `1647 -> 1648`.
- Lane PHP pass count: `1201 -> 1202`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`ZipPackageEntry`, zlib raw-deflate handling, in-process ZIP fixture builders,
and the existing WordPress ZIP package preflight smoke.

No Pandoc, Cabal solver/build/test command, Haskell runner, `ZipArchive`,
zip/unzip, Word, LibreOffice, external archive tool, external office tool,
browser renderer, online sanitizer, online service, or live provider test was
executed.

Full upstream Pandoc runner parity remains gated on hydrating/building the
pinned Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

## Non-Overlap

This does not repeat accepted ZIP central-directory parsing, local entry order,
stored-first mimetype checks, CRC/local-header validation for non-descriptor
entries, descriptor CRC/size validation, ZIP64 descriptor rejection,
central/local extra-field parsing, duplicate extra-field IDs, extended or NTFS
timestamps, ZIP64 extra-field or EOCD rejection, Unix symlink/special-file
rejection, central-directory digital-signature provenance, unsupported
compression-method preflight, deflate trailing-byte validation, encrypted-flag
rejection, DOS directory-attribute checks, path hierarchy collision preflight,
or archive-compression streams.

It owns only data-descriptor local-header placeholder provenance and rejection
of nonzero descriptor placeholders.

## Follow-Up

Keep full ZIP64 large-archive support, spanning archives, AES/encrypted payload
support, non-deflate decompressor implementation, cryptographic
central-directory signature validation, and strict package-reader default
policy wiring as separate bounded ZIP/package slices unless a concrete
DOCX/ODT/EPUB fixture requires one earlier.
