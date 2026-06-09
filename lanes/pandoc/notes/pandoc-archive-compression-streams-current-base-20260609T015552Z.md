# pandoc-archive-compression-streams-current-base-20260609T015552Z

Base accepted HEAD: `a19062aa0e7b6be3ad1a3778a5e0376791e8169f`

Implemented one bounded archive-compression support slice: `ArchiveCompressionStream::inspectZipCentralDirectoryInventoryPolicy()` now decodes plain ZIP, gzip-wrapped ZIP, zlib-deflate ZIP, raw-deflate ZIP, and LZ4-framed ZIP carriers before running the native `ZipPackage::centralDirectoryInventoryPreflight()` scanner. The wrapper preserves stream provenance, entry counts, central-directory signature location/length, and marks unverified central-directory digital signatures as metadata-only review diagnostics without exposing package entries.

Focused evidence:

- Baseline before edits: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 3380 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 3521 assertions, 0 failures`.
- Delta: `+1` PHP PASS case and `+141` focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` passed.

Status delta:

- `lane-status.json` `phpPass`: `2093 -> 2094`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2504 -> 2505`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 261`.

Dependency closure:

- No new support component is needed. This slice reuses native `ZipPackage`, `GzipStream`, `DeflateStream`, and `Lz4Frame` support already present under `lanes/pandoc/src`.
- No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, tar, lz4 CLI, external converter, online service, live provider test, or live-service provider test was executed.

Non-overlap:

- Avoided prior accepted archive stream coverage for gzip header/member policies, TAR sparse/multivolume/incremental/link/special/checksum/filesystem/case-insensitive policy, ZIP64 EOCD/extra fields, split ZIP, archive extra data records, encrypted ZIPs, general-purpose flags, unsupported compression methods, unsupported nested bzip2/xz/zstd, archive-bomb policy, zlib/LZ4 dictionaries, LZ4 skippable frames, LZ4 block-size policy, and decoded source chunks.

Follow-up:

- ZIP local-header flag/name mismatch policy across stream wrappers.
- Unsupported compression depth diagnostics across nested package candidates.
