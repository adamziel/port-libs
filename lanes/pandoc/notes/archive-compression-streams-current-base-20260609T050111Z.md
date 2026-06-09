# Archive Compression Streams Current Base 2026-06-09

Slice: `pandoc-archive-compression-streams-current-base-20260609T050111Z`

Base accepted HEAD: `945c3c6f54718c2e2a84ea6013a7f69ab7cd1d9a`

## Behavior

- Added `ArchiveCompressionStream::inspectZipCreatorHostSystemPolicy()` for ZIP bytes carried as plain ZIP, gzip ZIP, zlib ZIP, raw-deflate ZIP, or LZ4 ZIP streams.
- Added `ArchiveCompressionStream::inspectZipExternalAttributePolicy()` for the same carrier formats.
- Both wrappers decode the bounded stream and call existing raw `ZipPackage` central-directory preflights, avoiding package instantiation when entries should remain review-only, such as unknown creator host systems, Unix symlink attributes, and DOS directory-attribute/name mismatches.
- Updated `wordpress-archive-stream-preflight.php` so the WordPress review smoke surfaces the new gzip-wrapped ZIP creator-host and external-attribute diagnostics without exposing package bytes through external tools.

## Evidence

- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before patch: `1 test files, 4557 assertions, 0 failures`
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after patch: `1 test files, 4801 assertions, 0 failures`
- Added focused PASS line:
  - `preflights zip creator host and external attributes across archive streams`
- Assertion delta:
  - `+244`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`

## Non-Overlap

This does not repeat accepted archive-compression work for gzip member boundaries, decoded package chunks, ZIP data descriptors, ZIP64 EOCD/extra fields, Unicode extra fields, central-directory inventory, archive extra data records, local-header name/metadata/span/order policies, encryption, general-purpose flags, unsupported compression methods, source-name policy, nested package discovery, archive bomb ratios, deflate wrapper integrity, zlib preset dictionaries, LZ4 dictionary/skippable/content-size/block-size policies, or TAR metadata policies. It only wires the existing raw ZIP creator-host and external-attribute policy cluster through compressed ZIP stream carriers.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ArchiveCompressionStream`, `GzipStream`, `DeflateStream`, `Lz4Frame`, `ZipPackage` raw central-directory preflights, the focused PHP test runner, and the existing WordPress archive-stream smoke. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, tar/lz4 command-line tool, external converter, online service, live provider test, or live-service provider test was run.

## Follow-Up

Next archive-compression work should stay non-overlapping: recursive nested archive depth-limit behavior or additional stream-level ZIP policy wrappers are the clearest remaining bounded gaps.
