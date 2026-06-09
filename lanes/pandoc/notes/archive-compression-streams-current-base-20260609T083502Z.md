# Archive Compression Streams Current Base 2026-06-09

Slice: `pandoc-archive-compression-streams-current-base-20260609T083502Z`

Base accepted HEAD: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Behavior

- `ArchiveCompressionStreams::tarEntries()` now rejects TAR streams whose end marker has fewer than two zero blocks.
- This aligns the older lightweight gzip-tar helper with the stricter native `TarArchive` / `ArchiveCompressionStream` package-boundary invariant before WordPress import entries are exposed.
- The WordPress archive-compression preflight self-test now proves valid gzip-tar packets still expose safe content/media entries while gzip-wrapped TAR packets with a single zero end-marker block are blocked.

## Evidence

- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamsTest.php`
  - Result: `1 test files, 47 assertions, 0 failures`
- Red-first focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamsTest.php`
  - Result after adding the single-zero-block assertion before implementation: failed with `Expected exception RuntimeException was not thrown`
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamsTest.php`
  - Result: `1 test files, 48 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-compression-preflight.php --self-test`
  - Result: `archive compression preflight self-test passed`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `ArchiveCompressionStreams`, the existing gzip member decoder, the lightweight TAR reader, focused PHP tests, and the WordPress archive-compression preflight example.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `tar`, `gzip`, `zip`/`unzip`, LZ4 CLI, external archive tool, TeX/PDF engine, browser renderer, external converter, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted archive-compression work for the newer `TarArchive` two-block end-marker policy, TAR end-marker trailing-byte metadata preflight, gzip member boundaries, decoded package chunks, ZIP data descriptors, ZIP64 EOCD or extra fields, Unicode extra fields, central-directory inventory/signatures, archive extra data records, local-header name/metadata/span/order policy, ZIP platform sidecar metadata, creator-host systems, external attributes, split archives, unsupported compression methods, source-name policy, nested archive discovery, archive-bomb ratios, deflate wrapper integrity, zlib/LZ4 dictionary policy, LZ4 skippable/content-size/block-size/source-boundary policy, TAR sparse/multivolume/incremental/link/special-file metadata, or ZIP package primitives.

The owned behavior is only the legacy `ArchiveCompressionStreams` gzip-tar extraction path rejecting truncated one-zero-block TAR end markers before package entry exposure.

## Follow-Up

Next archive-compression work should stay non-overlapping. Good candidates are stream wrappers for ZIP EOCD trailing/offset policy, central-directory repair plans, or ZIP extra-field structure/id policy across compressed package carriers.
