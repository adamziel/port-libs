# Pandoc Archive Compression Streams Current Base 2026-06-08T02:51:48Z

Lane: `pandoc`
Slice: `pandoc-archive-compression-streams-current-base-20260608T025148Z`
Base accepted HEAD: `02ca21f0a770f96178de4e85f83f87d2bf977c2c`

## Behavior

Added bounded TAR PAX creation timestamp provenance for archive review packets:

- `TarArchiveEntry` now exposes nullable `createdAt` metadata.
- `TarArchive::fromString()` maps effective PAX `LIBARCHIVE.creationtime`,
  `SCHILY.birthtime`, and `birthtime` records to integer Unix timestamps,
  preserving fractional source records in raw PAX metadata while flooring the
  typed timestamp like existing `mtime`, `atime`, and `ctime` handling.
- `TarArchive::fromEntries()` accepts `createdAt` and emits bounded
  `LIBARCHIVE.creationtime` PAX metadata for generated review TAR packets.
- `ArchiveCompressionStream::inspectTarStream()` includes `createdAt` in
  entry layout metadata for WordPress importer review.
- The WordPress archive preflight example now confirms generated creation-time
  metadata is preserved through gzip-wrapped TAR inspection.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. POSIX PAX standard timestamp records already carry `mtime`, `atime`, and
`ctime`; common archive producers also use libarchive/star PAX keys for file
birth/creation time. Document-import review packets need this as inert metadata
provenance before DOCX/ODT/EPUB handoff, not filesystem timestamp application
or external archive extraction.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`,
external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool,
browser renderer, online sanitizer, online service, live provider test, or
live-service provider test was executed.

## Verification

- Baseline focused test before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1397 assertions, 0 failures`.
- Red-first focused test after adding the new expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: failed before parser wiring because `TarArchiveEntry` did not yet
    receive the parser-side `createdAt` constructor argument.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1418 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
- Syntax:
  - `php -l lanes/pandoc/src/TarArchive.php`
  - `php -l lanes/pandoc/src/TarArchiveEntry.php`
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.
- JSON status/manifest:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both files valid.
- Diff whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test grew from `1397` to `1418` assertions.
- `lane-status.json` `phpPass` moves from `1537` to `1538`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves from `1956` to `1957`.
- Archive-compression counters move from `11` to `12` mapped support cases and
  record current focused archive-compression evidence at `1418` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `TarArchive`,
`TarArchiveEntry`, `ArchiveCompressionStream`, in-memory TAR/PAX fixtures, the
WordPress archive stream preflight example, and the focused PHP test harness.
Full upstream Pandoc/Haskell runner parity, external archive-tool validation,
filesystem timestamp application, archive extraction, sparse-file
reconstruction, hardlink/symlink materialization, non-deflate ZIP methods, and
multi-volume archive handling remain separate bounded follow-up work.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation,
split-gzip XFL/OS/CRC32 and byte-layout provenance, raw/zlib DEFLATE
provenance, zlib/LZ4 dictionary policy or supplied-dictionary decode,
independent/dependent LZ4 frame parsing/writing, ZIP/OPC package primitives,
ZIP encryption or compression-method preflight, TAR PAX path/size/owner/
atime/ctime/hdrcharset metadata, duplicate PAX keyword policy, PAX filesystem
xattr/ACL/file-flag preflight, GNU long-name parsing, TAR link-policy
preflight, sparse-entry blocking and sparse-map provenance, GNU long-link
rejection, typeflag `7` contiguous file handling, trailing-slash regular-entry
directory normalization, TAR end-marker validation, TAR drive-letter rejection,
base-256 numeric decoding, signed checksum compatibility, nested package
discovery, generic TAR/ZIP package-kind detection, special-file rejection, or
archive-bomb ratio preflight.

## Follow-Up

Keep archive-bomb heuristics, encrypted archive preflight, sparse-file
reconstruction, hardlink/symlink materialization policy, filesystem extraction,
recursive content conversion, non-deflate ZIP methods, dictionary-backed LZ4
fixtures, multi-volume archive diagnostics, external archive-tool validation,
and full upstream-runner parity as separate bounded slices unless concrete
package fixtures require them.
