# Pandoc ZIP Package Core Current Base: Data Descriptor Preflight

## Behavior

- Added `ZipPackage::dataDescriptorPreflight()` so DOCX/ODT/EPUB and
  WordPress review paths can inspect ZIP data descriptor entries without
  shelling out to `zip`, `unzip`, `ZipArchive`, Pandoc, Word, or LibreOffice.
- The preflight reports descriptor entry counts, signed vs unsigned descriptor
  counts, descriptor offsets, value offsets, descriptor lengths, CRC32, and
  compressed/uncompressed size provenance.
- `ZipPackage::fromString()` now rejects ZIP64-sized data descriptor bodies
  explicitly before package media handoff. The bounded reader still supports
  ordinary 32-bit data descriptors with and without the optional signature.

Source truth: Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed packages before
document conversion. ZIP data descriptors are normal package metadata for
streamed entries, but ZIP64 descriptor sizing belongs to the separate ZIP64
support boundary and must remain blocked in this bounded PHP reader.

## Evidence

Baseline focused ZIP package verification before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 555 assertions, 0 failures
```

Focused ZIP package verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 578 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Root harness: not run - isolated micro-slice.

## Delta

- Focused ZIP package PASS cases: +1.
- Focused ZIP package assertions: `555 -> 578`, adding 23 assertions.
- Manifest mapped checks: `1544 -> 1545`.
- ZIP package support cases: `21 -> 22`.
- Lane PHP pass count: `1092 -> 1093`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`ZipPackage`, `ZipPackageEntry`, zlib raw-deflate handling, in-process ZIP
fixture builders, and the WordPress ZIP package preflight smoke.

Full upstream Pandoc runner parity remains gated on hydrating/building the
pinned Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but this
ZIP data-descriptor behavior is fully covered by native PHP tests.

## Non-Overlap

This does not repeat accepted ZIP central/local metadata, stored-first
mimetype preflight, local entry order, CRC/local-header integrity, descriptor
CRC/size validation, central/local extra-field parsing, duplicate extra-field
IDs, extended or NTFS timestamps, ZIP64 extra-field or EOCD rejection, Unix
symlink/special-file rejection, compression method policy, aggregate size
preflight, bounded reads, central-directory signature metadata, raw-deflate
trailing-byte validation, archive-compression streams, OPC relationships,
DOCX body/properties/styles/media parsing, EPUB spine/nav, or ODF
content/styles/meta mapping. It owns only descriptor metadata review and
explicit ZIP64-sized descriptor rejection.

## Follow-Up

Keep full ZIP64 large-entry support, spanning archives, encrypted/AES payloads,
cryptographic central-directory signature validation, unsupported compression
methods, and package-specific media policy as separate bounded slices unless a
concrete DOCX/ODT/EPUB fixture requires one earlier.
