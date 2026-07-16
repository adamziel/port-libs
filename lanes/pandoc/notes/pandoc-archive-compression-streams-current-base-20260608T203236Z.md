# Pandoc Archive Compression Streams Current Base 2026-06-08T20:32:36Z

Slice: `pandoc-archive-compression-streams-current-base-20260608T203236Z`

Base accepted HEAD: `bb37a42dff2002404bb134df44da31542c787c36`

## Behavior

- Added `ArchiveCompressionStream::inspectGzipMemberSourceNamePolicyAuto()` for metadata-only review of gzip member FNAME values on gzip-wrapped TAR/ZIP package streams.
- The policy checks each gzip member filename against the decoded package kind/format, so `*.tar` names match gzip-wrapped TAR bytes, `.docx`/`.zip` names match gzip-wrapped ZIP bytes, redundant `.zip.gz` member names are review-only, and TAR/ZIP kind mismatches are flagged before conversion.
- Missing gzip FNAME metadata is now explicit review metadata instead of silently relying on the outer upload name.
- The policy returns only review metadata, stream provenance, entry names/counts, and diagnostics. It does not return `tarBytes`, `zipBytes`, `archive`, or `package` objects.
- The WordPress archive preflight smoke now exercises the mismatch path for a gzip member named `wordpress-review-packet.tar` carrying ZIP package bytes.

## Evidence

- Rework note check: current top-level `port-pandoc-*.needs-lane-rework.md` glob returned no active files before editing.
- Baseline focused test before implementation: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2446 assertions, 0 failures`.
- Focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2515 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` -> `wordpress-archive-stream-preflight self-test passed`.
- PHP lint passed for `ArchiveCompressionStream.php`, `ArchiveCompressionStreamTest.php`, and `wordpress-archive-stream-preflight.php`.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `1816 -> 1817`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2240 -> 2241`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 189`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ArchiveCompressionStream`, `GzipStream`, `TarArchive`, `ZipPackage`, and the existing package source-name classifier.

Pandoc, Cabal/Haskell runners, tar, zip/unzip, lz4, ZipArchive, Word, LibreOffice, external archive tools, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat gzip member byte provenance, decoded package chunk source mapping, upload source-name mismatch policy, unsupported nested compression metadata, archive-bomb policy, TAR PAX timestamp/hdrcharset policy, ZIP trailing-deflate integrity, or ZIP data-descriptor/ZIP64/split/encryption/compression-method policy. It closes a separate package-stream handoff gap: gzip member filenames now have their own decoded package-kind policy before conversion.

## Next

A useful archive follow-up would be a non-overlapping native package-stream gap such as gzip multi-member package-boundary policy or additional bounded ZIP/TAR handoff metadata that is not already covered by ZIP encryption, compression-method, data-descriptor, split, ZIP64, or TAR PAX policy slices.
