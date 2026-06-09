# Pandoc Archive Compression Streams Current Base 2026-06-09T05:29:16Z

Slice: `pandoc-archive-compression-streams-current-base-20260609T052916Z`

Base accepted HEAD: `003cd766d197b04fb23d7e77772dd1e8b0ccc6a3`

## Behavior

- Added `ArchiveCompressionStream::inspectZipCommentPolicy()` for ZIP bytes carried as plain ZIP, gzip ZIP, zlib ZIP, raw-deflate ZIP, or LZ4 ZIP streams.
- The wrapper decodes the bounded carrier, delegates to native `ZipPackage::commentPreflight()`, and reports package comments, entry comments, raw C0/DEL control bytes, Unicode format controls, and bidi controls as metadata-only review diagnostics.
- The policy adds `zip-comment-policy`, `handoffPolicy`, `extractionPolicy`, and strict-import diagnostics without returning a `ZipPackage` object or exposing decoded package entries through external tools.
- `wordpress-archive-stream-preflight.php` now self-tests a gzip-wrapped ZIP comment packet with package bidi metadata, an entry zero-width-joiner comment, and an entry DEL-control comment.

## Evidence

- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before patch: `1 test files, 4801 assertions, 0 failures`
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after patch: `1 test files, 4999 assertions, 0 failures`
- Added focused PASS line:
  - `preflights zip package and entry comments across archive streams before strict handoff`
- Assertion delta:
  - `+198`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`

Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2375 -> 2376`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2769 -> 2770`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 318`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ArchiveCompressionStream`, `ZipPackage::commentPreflight()`, `GzipStream`, `DeflateStream`, `Lz4Frame`, focused archive tests, and the existing WordPress archive stream preflight example.

No Pandoc, Cabal/Haskell runner, `tar`, `gzip`, `lz4`, `zip`, `unzip`, `ZipArchive`, Word, LibreOffice, external archive tool, external validator, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted archive-stream coverage for ZIP data descriptors, ZIP64 EOCD/extra fields, Unicode extra fields, central-directory inventory, split ZIP markers, archive extra data records, local-header name/metadata/span/order policies, encryption, general-purpose flags, creator-host/external-attribute policy, unsupported compression methods, unsupported-compression fingerprints, source-name policy, nested package discovery, archive bomb ratios, deflate wrapper integrity, zlib preset dictionaries, LZ4 dictionary/skippable/content-size/block-size policies, TAR end-marker/trailing-byte provenance, TAR sparse/multivolume/incremental/link/special-file policies, or gzip/LZ4 TAR record-boundary policies.

## Follow-Up

Useful archive follow-ups remain ZIP modification-time, path-hierarchy/name-hygiene, and raw strict-import policy wrappers across compressed carriers, or LZ4 ZIP source-boundary provenance for local-entry layouts.
