# Pandoc Archive Compression Streams Current Base 2026-06-07T06:04:57Z

Lane: `pandoc`
Slice: `pandoc-archive-compression-streams-current-base-20260607T060457Z`
Base accepted HEAD: `45539ec04b8219d154701e97e362a3479d34ee84`

## Behavior

Added bounded metadata-only LZ4 dictionary-frame policy preflight:

- `Lz4Frame::dictionaryPolicyPreflight()` inspects mixed LZ4 streams without decoding package bytes.
- Skippable frames retain review metadata, id, offsets, and sizes.
- Data frames preserve dictionary id, content-size flag, block-size class, block/content checksum flags, block count/types, compressed byte size, offsets, and sizes.
- Frames with the LZ4 dictionary-id flag report `policy: blocked` plus `lz4-dictionary-frame-not-decoded` and `lz4-external-dictionary-required` diagnostics.
- `ArchiveCompressionStream::inspectLz4DictionaryPolicy()` exposes the policy for archive review packets.
- Existing decode and LZ4 TAR paths remain fail-closed for dictionary-backed frames.
- `wordpress-archive-stream-preflight.php` now includes dictionary-backed LZ4 review metadata and confirms extraction stays blocked.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support row. LZ4 frame descriptors carry an optional 32-bit dictionary ID flag, and dictionary-backed streams require an external dictionary before payload decoding. Document-import package fixtures need reviewer-visible provenance and an explicit blocked policy, not external dictionary execution or archive extraction.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`, external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Verification

- Focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 860 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
- Required syntax and diff checks before handoff:
  - `php -l lanes/pandoc/src/Lz4Frame.php`
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test grew from the previous archive-slice evidence of `828` assertions to `860` assertions.
- `lane-status.json` `phpPass` moves from `1459` to `1460`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves from `1876` to `1877`.
- Archive-compression counters move from `11` to `12` mapped support cases and from `120` to `152` focused support assertions in this accepted worktree.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `Lz4Frame`, `ArchiveCompressionStream`, in-memory LZ4 fixtures, the WordPress archive stream preflight example, and the focused PHP test harness. Full upstream Pandoc/Haskell runner parity, external archive-tool validation, LZ4 dictionary decompression, filesystem extraction, recursive conversion, sparse-file reconstruction, hardlink/symlink materialization, non-deflate ZIP methods, and multi-volume archive handling remain separate bounded follow-up work.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation, split-gzip XFL/OS/CRC32 and byte-layout provenance, raw/zlib DEFLATE provenance, LZ4 frame parsing or writing, LZ4 compressed/uncompressed block decoding, LZ4 skippable metadata, content-size validation, block/content checksums, ZIP/OPC package primitives, ZIP encryption flag rejection, TAR PAX path/size/owner/atime/ctime/hdrcharset metadata, duplicate PAX keyword policy, GNU long-name parsing, TAR link-policy preflight, sparse-entry blocking and sparse-map provenance, GNU long-link rejection, typeflag `7` contiguous file handling, trailing-slash regular-entry directory normalization, TAR end-marker validation, TAR drive-letter rejection, base-256 numeric decoding, signed checksum compatibility, nested package discovery, generic TAR/ZIP package-kind detection, strict special-file rejection, or special-file policy preflight.

## Follow-Up

Keep archive-bomb heuristics, encrypted archive preflight, sparse-file reconstruction, hardlink/symlink materialization policy, filesystem extraction, recursive content conversion, non-deflate ZIP methods, dictionary-backed LZ4 decompression, multi-volume archive diagnostics, external archive-tool validation, and full upstream-runner parity as separate bounded slices unless concrete package fixtures require them.
