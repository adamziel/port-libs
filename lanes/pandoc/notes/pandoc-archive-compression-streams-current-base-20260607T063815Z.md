# Pandoc Archive Compression Streams Current Base 2026-06-07T06:38:15Z

Lane: `pandoc`
Slice: `pandoc-archive-compression-streams-current-base-20260607T063815Z`
Base accepted HEAD: `5b53644c4db20cfb702ed9ef7894f15ca40cdc21`

## Behavior

Added bounded supplied-dictionary LZ4 frame decoding for archive fixture bytes:

- `Lz4Frame::decodeWithDictionaries()` and `framesWithDictionaries()` accept an explicit map of 32-bit Dict-ID values to byte-string dictionaries.
- Dictionary-backed frames still fail closed through the existing `decode()` / `frames()` paths unless a matching external dictionary is supplied.
- Independent dictionary blocks are decoded with the same supplied dictionary for each block; linked blocks use the dictionary only as the initial frame history before normal block-history updates.
- Block checksum, content checksum, content-size, block-size, max-output, and missing/wrong-dictionary failures remain enforced.
- `ArchiveCompressionStream` now exposes explicit LZ4 dictionary decode wrappers for TAR and ZIP stream bytes.
- The WordPress archive stream preflight example keeps metadata-only dictionary policy review and now also proves an importer-owned dictionary can decode a bounded dictionary-backed LZ4 stream.

## Source Truth

The LZ4 frame specification describes the optional Dictionary ID field as a decoder hint for selecting the external dictionary. It also specifies that independent blocks are initialized with the same dictionary, while linked blocks use the dictionary only at the beginning of the frame. This slice ports that bounded contract into native PHP for package fixture streams without invoking `lz4` or any external archive tool.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`, external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

Reference used: https://android.googlesource.com/platform/external/lz4/+/HEAD/doc/lz4_Frame_format.md

## Verification

- Baseline focused test before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 860 assertions, 0 failures`.
- Focused test after edits:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 880 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test grew from `860` to `880` assertions.
- `lane-status.json` `phpPass` moves from `1460` to `1461`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves from `1878` to `1879`.
- Archive-compression manifest counters move from `11` to `12` mapped support cases and from `120` to `140` focused support assertions in this accepted worktree.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `Lz4Frame` frame parsing/raw-block decoding, `ArchiveCompressionStream` dispatch wrappers, in-memory LZ4 fixtures, the WordPress archive stream preflight example, and the focused PHP harness. Full upstream Pandoc/Haskell runner parity, external archive-tool validation, automatic external-dictionary discovery, filesystem extraction, recursive conversion, sparse-file reconstruction, hardlink/symlink materialization, non-deflate ZIP methods, and multi-volume archive handling remain separate bounded follow-up work.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation, split-gzip XFL/OS/CRC32 and byte-layout provenance, raw/zlib DEFLATE provenance, LZ4 frame parsing or writing, LZ4 compressed/uncompressed block decoding without external dictionaries, LZ4 skippable metadata, LZ4 dictionary metadata-only policy preflight, content-size validation, block/content checksums, ZIP/OPC package primitives, ZIP encryption flag rejection, TAR PAX path/size/owner/atime/ctime/hdrcharset metadata, duplicate PAX keyword policy, GNU long-name parsing, TAR link-policy preflight, sparse-entry blocking and sparse-map provenance, GNU long-link rejection, typeflag `7` contiguous file handling, trailing-slash regular-entry directory normalization, TAR end-marker validation, TAR drive-letter rejection, base-256 numeric decoding, signed checksum compatibility, nested package discovery, generic TAR/ZIP package-kind detection, strict special-file rejection, or special-file policy preflight.

## Follow-Up

Keep encrypted archive preflight, archive-bomb heuristics, sparse-file reconstruction, hardlink/symlink materialization policy, filesystem extraction, recursive content conversion, non-deflate ZIP methods, multi-volume archive diagnostics, automatic dictionary discovery policy, external archive-tool validation, and full upstream-runner parity as separate bounded slices unless concrete package fixtures require them.
