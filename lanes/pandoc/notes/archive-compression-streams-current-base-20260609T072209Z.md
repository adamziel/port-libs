# Archive Compression Streams Current Base 2026-06-09

Slice: `pandoc-archive-compression-streams-current-base-20260609T072209Z`

Base accepted HEAD: `93c7fe92d8764429cde901a465ac3a9266aec0d4`

## Behavior

- Added `ArchiveCompressionStream::inspectZipPlatformMetadataPolicy()` for ZIP bytes carried as plain ZIP, gzip ZIP, zlib ZIP, raw-deflate ZIP, or LZ4 ZIP streams.
- The wrapper decodes the bounded stream, delegates to `ZipPackage::platformMetadataPolicyPreflight()`, preserves `zipBytes`, `packageByteSize`, and stream provenance, and reports platform sidecar diagnostics as metadata-only review policy without exposing a `ZipPackage` object.
- Focused coverage exercises macOS `__MACOSX`, AppleDouble, `.DS_Store`, Windows `Thumbs.db`, and `desktop.ini` entries across all supported ZIP stream carriers, including a central/local header name mismatch that blocks package instantiation while platform metadata remains inspectable.
- Updated `wordpress-archive-stream-preflight.php` so the WordPress review smoke surfaces gzip-wrapped ZIP platform sidecar metadata before import.

## Evidence

- Red-first focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the test before implementation: failed with `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectZipPlatformMetadataPolicy()`
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 5843 assertions, 0 failures`
- Added focused PASS line:
  - `preflights zip platform metadata across archive streams without package exposure`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `ArchiveCompressionStream`, `ZipPackage::platformMetadataPolicyPreflight()`, `GzipStream`, `DeflateStream`, `Lz4Frame`, focused PHP tests, and the existing WordPress archive-stream preflight example.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`/`unzip`, `tar`, external archive tool, LZ4 CLI, TeX/PDF engine, browser renderer, external converter, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted archive-compression work for gzip member boundaries, decoded package chunks, ZIP data descriptors, ZIP64 EOCD or extra fields, Unicode extra fields, central-directory inventory/signatures, archive extra data records, local-header name/metadata/span/order policy, package prefixes, encrypted package flags, general-purpose flags, comments, creator-host systems, external attributes, split archives, unsupported compression methods, source-name policy, nested archive discovery, archive-bomb ratios, deflate wrapper integrity, zlib/LZ4 dictionary policy, LZ4 skippable/content-size/block-size/source-boundary policy, TAR sparse/multivolume/incremental/link/special-file metadata, or HTML5 DOM link-relation behavior.

The owned behavior is ZIP platform sidecar metadata surfaced through compressed package streams before package object exposure.

## Follow-Up

Next archive-compression work should stay non-overlapping. Good candidates are stream wrappers for EOCD trailing/offset policy, central-directory repair plans, or ZIP extra-field structure/id policy across compressed package carriers.
