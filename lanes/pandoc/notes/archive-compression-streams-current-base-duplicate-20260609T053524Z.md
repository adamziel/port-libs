# Archive Compression Streams Package Prefix Policy

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-archive-compression-streams-current-base-duplicate-20260609T053524Z`
- Base accepted HEAD: `43b1a4a1010b27f9642a54fbdd65b896e3bf9eec`

## Behavior

Added `ArchiveCompressionStream::inspectZipPackagePrefixPolicy()` as a bounded stream-level wrapper around the existing native `ZipPackage::packagePrefixPreflight()` support. The policy decodes ZIP bytes from plain ZIP, gzip+ZIP, zlib+ZIP, raw-deflate+ZIP, and LZ4+ZIP streams, then reports package-prefix byte count, preview hex, executable-stub signature, local-header span normalization with the prefix removed, and review-before-conversion diagnostics without exposing a `ZipPackage` object for prefixed packages.

This is intentionally metadata-only preflight. It does not shell out to Pandoc, Word, LibreOffice, zip/unzip, tar, LZ4 binaries, TeX/PDF engines, browser renderers, external converters, or online services.

## Evidence

- Baseline focused archive stream test before the patch: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 4801 assertions, 0 failures`.
- After patch: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 4952 assertions, 0 failures`.
- Delta: `+1` focused PHP PASS line and `+151` focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` -> `wordpress-archive-stream-preflight self-test passed`.

## Non-Overlap

This reuses the existing raw `ZipPackage::packagePrefixPreflight()` package primitive and adds only the archive stream handoff wrapper plus stream-format coverage. It does not repeat the accepted data-descriptor, data-descriptor integrity, ZIP64 EOCD, ZIP64 extra-field, Unicode extra-field, central-directory inventory, archive extra data record, local-header name/flag mismatch, local-header metadata mismatch, local-header span, local-header order, encrypted package, general-purpose flag, creator-host, external-attribute, split-archive, or unsupported compression-method stream policies.

## Dependency Closure

No new support component is needed. Existing native PHP ZIP package preflight and archive stream decoders are reused. External decompressor/package tools remain out of scope for this lane.

## Follow-Up

Continue archive compression work only on non-overlapping stream wrappers or package fixture diagnostics that improve real DOCX/ODT/EPUB package handoff without invoking external tools.
