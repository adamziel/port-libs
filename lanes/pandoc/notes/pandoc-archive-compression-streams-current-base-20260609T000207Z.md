# pandoc archive compression streams current-base 2026-06-09

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-archive-compression-streams-current-base-20260609T000207Z`
- Accepted base: `48a59c8d15f1cb4b103c2c2657a62cb105c4a87a`
- Bounded behavior: TAR entry-name collision preflight for case-insensitive and Unicode-normalized package handoff.

## Behavior

`TarArchive` now exposes `caseInsensitiveNamePreflight()` and `assertNoCaseInsensitiveNameCollisions()`.
The preflight groups exact TAR entry names by a case-folded, NFC-normalized collision key and reports:

- collision group count and affected entry count;
- exact entry names in each collision group;
- per-entry `case-insensitive-name-collision` issues;
- `review-before-conversion` handoff policy when collisions are present.

Exact TAR reads remain unchanged, so `packet/media/Café.PNG` and `packet/media/café.png` stay addressable by exact name while strict import review can reject the package before mapping paths onto a case-insensitive or Unicode-normalized destination.

`ArchiveCompressionStream::inspectTarCaseInsensitiveNamePolicy()` exposes the same policy for compressed TAR streams with gzip provenance. The WordPress archive-stream preflight example now includes a gzip-wrapped TAR review packet that blocks Unicode-equivalent media names before import.

## Verification

- Rework notes: none matched `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3072 assertions, 0 failures`.
- Red-first: the new focused test failed with `Call to undefined method PortLibs\Pandoc\TarArchive::caseInsensitiveNamePreflight()`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3107 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` passed.
- PHP lint passed for:
  - `lanes/pandoc/src/TarArchive.php`
  - `lanes/pandoc/src/ArchiveCompressionStream.php`
  - `lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
- JSON validation passed for:
  - `lanes/pandoc/lane-status.json`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1996 -> 1997`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2414 -> 2415`.
- Focused archive stream assertions: `3072 -> 3107`.

## Dependency Closure

No new support component is needed. This reuses the native `TarArchive`, `GzipStream`, and `ArchiveCompressionStream` helpers with bounded Unicode normalization fallbacks. No Pandoc, Cabal/Haskell runner, tar, gzip, zip/unzip, lz4, ZipArchive, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat prior ZIP Unicode-name collision work: it applies the same class of strict preflight to TAR packages and compressed TAR streams, where only exact duplicate entry names were previously rejected.
