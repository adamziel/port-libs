# Pandoc Archive Compression Streams Current Base 20260609T014307Z

## Behavior Target

TarArchive checksum-policy preflight now exposes safe metadata summaries for TAR metadata records without exposing package payload bytes. PAX records report `metadataKind`, sorted `paxHeaderKeys`, and a header count; GNU long-name and long-link records report their metadata kind plus value size. Regular payload records keep metadata fields null or empty.

This keeps checksum provenance review useful for WordPress/archive import queues where a signed checksum packet may use PAX metadata for the final path while the actual content remains governed by `checksum-provenance-only-no-extraction`.

## Source Truth And Non-Overlap

This is bounded native PHP archive support under the archive/compression-stream slice. It does not run Pandoc, Cabal/Haskell runners, tar, zip/unzip, lz4, ZipArchive, Word, LibreOffice, external archive tools, online services, live provider tests, or live-service provider tests.

The slice avoids overlapping accepted archive work for gzip member framing/provenance, PAX atime/ctime and hdrcharset policy, GNU long-name extraction, GNU long-link/link/device rejection policy, ZIP64 and ZIP payload integrity preflight, LZ4 dictionary/frame behavior, nested archive policy, source-name policy, and expansion-ratio policy.

## Verification Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes were present.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3380 assertions, 0 failures`.
- Red-first focused test: the same command failed as expected with `1 test files, 3364 assertions, 1 failures` after adding checksum metadata-summary assertions, because checksum-policy entries did not expose `metadataKind` or PAX header summary fields.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3391 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` passed.
- PHP lint: `php -l lanes/pandoc/src/TarArchive.php`, `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`, and `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php` all reported no syntax errors.
- JSON validation: `lane-status.json` decoded successfully.
- Diff check: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: unchanged. This strengthens an existing mapped focused case rather than adding a new TestRunner PASS case.
- Focused assertions: `3380 -> 3391` for `ArchiveCompressionStreamTest.php`.
- Manifest/mapped denominator: unchanged; no new upstream inventory unit was claimed.

## Dependency Closure

No new support component is needed. The slice reuses native `TarArchive`, `ArchiveCompressionStream`, `GzipStream`, focused archive tests, and the lane-local WordPress archive-stream example. External archive executables and upstream Pandoc runner dependency closure remain outside this micro-slice.

## Next

Archive/compression follow-up should choose a non-overlapping native gap such as TAR metadata-record layout source segments, ZIP central-directory comment policy, nested package policy refinement, or archive fixture provenance that can be covered by focused PHP tests without external archive tools.
