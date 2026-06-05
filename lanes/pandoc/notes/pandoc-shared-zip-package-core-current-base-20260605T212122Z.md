# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T212122Z`
Base accepted HEAD: `773fccc96bdf33d1c76679f0bbe94a6e82e54e4b`
Date: 2026-06-05 UTC

No current Pandoc rework note was present under
`.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Behavior

- Tightened `ZipPackage::read()` for deflated entries.
- The ZIP reader now streams raw deflate payloads through zlib and compares
  the consumed byte count with the declared compressed byte count.
- A deflated entry whose raw deflate stream ends before the declared compressed
  byte range is rejected as trailing compressed data, even if CRC32 and
  expanded size would otherwise match.
- The WordPress ZIP/package preflight smoke now reports this rejection before
  exposing Office/EPUB media bytes.

## Source Truth

Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed packages, and the bounded PHP
package reader must not treat hidden bytes after a deflate stream as importable
payload provenance. PHP `gzinflate()` accepts a raw deflate stream followed by
extra bytes, so this slice makes the native package reader validate full
compressed-byte consumption without invoking Pandoc, `ZipArchive`, zip/unzip,
Word, LibreOffice, or any external archive/conversion tool.

## Red-First Evidence

Focused ZIP test after adding the malformed-deflate fixture but before the
implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
FAIL rejects deflated zip payloads with trailing bytes before media handoff
Values are not identical
Expected: 1
Actual: 2
1 test files, 547 assertions, 1 failures
```

## Verification

Focused ZIP package verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 555 assertions, 0 failures
```

Focused ZIP-dependent package-reader regressions:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1464 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1006 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1009 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Root harness: not run - isolated micro-slice.

## Delta

- Focused ZIP package PASS cases: adds 1 PASS case.
- Focused ZIP package assertions: `545 -> 555`, adding 10 assertions.
- Manifest mapped checks: `1528 -> 1529`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 141`.
- Lane PHP pass count: `1076 -> 1077`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, in-process ZIP fixture builders, DOCX/ODF/EPUB package-reader
tests, and WordPress ZIP package preflight smoke.

Full upstream Pandoc runner parity remains gated on hydrating/building the
pinned Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but
raw-deflate payload-consumption validation is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, stored-first mimetype
preflight, local entry order, data descriptors, CRC/local-header integrity,
central/local extra-field parsing, duplicate extra-field IDs, extended or NTFS
timestamps, ZIP64 extra-field or EOCD rejection, Unix symlink/special-file
rejection, compression method policy, aggregate size preflight, bounded reads,
archive-compression streams, OPC relationships, DOCX body/properties/styles/
media parsing, EPUB spine/nav, ODF content/styles/meta mapping, table geometry,
doctemplates, math/TeX, syntax highlighting, or Markdown/HTML reader/writer
behavior. It owns only raw deflate stream-consumption validation for ZIP
package entries.

## Follow-Up

Keep ZIP64 large-entry support, spanning archives, encrypted/AES payloads,
cryptographic central-directory signature validation, unsupported compression
methods, and package-specific media policy as separate bounded slices unless a
concrete DOCX/ODT/EPUB fixture requires one earlier.
