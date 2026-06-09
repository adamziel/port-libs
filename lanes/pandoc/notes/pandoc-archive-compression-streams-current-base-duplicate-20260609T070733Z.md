# Pandoc Archive Compression Streams - ZIP Modification Times

Micro-slice: `pandoc-archive-compression-streams-current-base-duplicate-20260609T070733Z`

Base accepted HEAD: `030e94cf137586963da96dca64555cebe2ff01ee`

## Implementation

- Added `ArchiveCompressionStream::inspectZipModificationTimePolicy()` for ZIP
  packages carried as raw ZIP, gzip ZIP, zlib ZIP, raw-deflate ZIP, and LZ4 ZIP
  streams.
- The wrapper decodes the bounded carrier, delegates to native
  `ZipPackage::modificationTimePreflight()`, and returns DOS, extended
  timestamp, NTFS, invalid DOS timestamp, package byte-size, handoff policy,
  extraction policy, and stream provenance metadata.
- Invalid DOS modification timestamps are marked
  `review-before-conversion` / `zip-modification-time-review`; clean timestamp
  metadata remains `metadata-only-no-extraction`.
- Extended `wordpress-archive-stream-preflight.php --self-test` with a
  gzip-wrapped DOCX-shaped ZIP fixture that preserves a Word document
  modification timestamp for WordPress import review.

## Evidence

- `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 5792 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2470 -> 2471`.
- Added `1` focused PHP PASS line.
- Added `178` focused assertions in `ArchiveCompressionStreamTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP
`ZipPackage::modificationTimePreflight()`, gzip/zlib/raw-deflate/LZ4 stream
decoding, archive stream inspection support, and lane-local PHP fixtures.

Full upstream Pandoc/Haskell runner parity remains a separate upstream-runner
dependency task requiring a hydrated Pandoc checkout and Haskell test
executables. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`,
`unzip`, `tar`, `gzip`, `lz4`, `ZipArchive`, external converter, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted gzip member metadata, gzip timestamp/platform
policy, gzip trailer integrity, gzip package-boundary checks, zlib preset
dictionaries, LZ4 dictionary/frame limit/source-boundary policies, ZIP comment
policy, ZIP creator host/external attributes, ZIP general purpose flags, ZIP
encryption, ZIP unsupported compression methods, ZIP64 EOCD/extra fields, ZIP
Unicode extra fields, ZIP local-header name/metadata/span policies, split ZIP
markers, duplicate central-directory names, TAR duplicate member names, TAR
sparse/multi-volume/incremental/link/special-file policies, or archive bomb
ratio checks.

## Follow-Up

Keep ZIP path-hierarchy/name-hygiene policy, duplicate local-header offset
stream policy, compressed ZIP raw-name collision surfacing, and raw strict
import wrappers as separate bounded archive slices.
