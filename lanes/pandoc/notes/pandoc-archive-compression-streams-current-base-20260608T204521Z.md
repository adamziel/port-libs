# Pandoc Archive Compression Streams Current Base 20260608T204521Z

Lane: `pandoc`
Micro-slice: `pandoc-archive-compression-streams-current-base-20260608T204521Z`
Accepted base: `6479f65c1465d77f871d7146aaaa2d022aa27e3f`

## Behavior

`ArchiveCompressionStream` now exposes a bounded gzip member package-boundary preflight for gzip-wrapped TAR and ZIP streams. The preflight distinguishes a valid split-member gzip stream, where only the combined decoded payload forms one package, from concatenated gzip members that each contain a standalone package. The result stays metadata-only: policy, diagnostics, decoded-size/member summaries, standalone-package counts, and entry names are exposed, while decoded archive bytes and package objects are not.

WordPress archive-stream preflight now surfaces the review policy and diagnostics before importer handoff so multi-package gzip uploads can be reviewed instead of silently treated as one package stream.

## Evidence

- Rework notes: none found in `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 2446 assertions, 0 failures`.
- Red-first: the new focused test failed before implementation with `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectGzipMemberPackageBoundaryPolicy()`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 2477 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` passed.

## Status Delta

- `lane-status.json` `phpPass`: `1824 -> 1825`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2248 -> 2249`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 151`.

## Dependency Closure

No new support component is needed. This slice reuses native `GzipStream` member inspection, `TarArchive` and `ZipPackage` bounded metadata parsing, and `ArchiveCompressionStream` stream-policy helpers. No Pandoc, Cabal/Haskell runner, `tar`, `zip`/`unzip`, `lz4`, `ZipArchive`, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Next Task

Pick a non-overlapping archive/package-stream gap, such as compressed ZIP central-directory encryption metadata or an additional TAR PAX policy edge required by package fixtures.
