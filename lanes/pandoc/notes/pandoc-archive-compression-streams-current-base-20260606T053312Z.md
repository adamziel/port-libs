# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260606T053312Z`

Base accepted HEAD: `5461910d04f397e37087574b1ad2209244ea6334`

## Behavior

- Added bounded TAR PAX zero-length record handling.
- `TarArchive::fromString()` now applies zero-length PAX keyword values as
  scoped metadata deletions before resolving effective entry metadata.
- Local PAX deletion records remove inherited global keys for the next entry
  without mutating later global state; global PAX deletion records remove keys
  for subsequent entries.
- Deleted `mtime`/`uname`/review keys now fall back to the entry header or
  disappear from review metadata instead of surfacing empty values or failing
  timestamp parsing.
- The WordPress archive preflight smoke now includes a gzip-wrapped TAR packet
  that verifies local PAX provenance deletion and inherited global provenance.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. POSIX-style PAX records use an empty value to delete an inherited keyword,
which matters for package fixtures that carry global review provenance and then
clear it for selected DOCX/ODT/EPUB/WordPress import payloads. The behavior is
implemented in native PHP without invoking Pandoc, Cabal, Haskell runners, tar,
gzip, zip/unzip, lz4, ZipArchive, external archive tools, browser renderers,
online sanitizers, online services, or live provider tests.

## Evidence

- No current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  note was present for this lane.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 554 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 554 assertions, 1 failures`.
  - Failure: local zero-length PAX `mtime=` was parsed as an invalid timestamp
    instead of deleting inherited global metadata.
- After implementation:
  - `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 578 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1210 -> 1211`.
- `benchmarkDenominator.mapped`: `1656 -> 1657`.
- Manifest archive-compression counters:
  `archiveCompressionStreamCoreCases=11`,
  `mappedArchiveCompressionStreamCoreCases=11`, and
  `archiveCompressionStreamCoreAssertions=125`.
- Focused archive test grew from `554` to `578` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `TarArchive`,
`ArchiveCompressionStream`, `GzipStream`, the focused PHP test harness, and the
existing WordPress archive preflight example. Full upstream Pandoc runner
parity remains blocked on hydrating the pinned Pandoc checkout and building the
Haskell Tasty executables for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip Latin-1/provenance
labels, gzip text-hint flags, split-gzip TAR member provenance, raw/zlib
DEFLATE provenance, LZ4 frame parsing/writing, ZIP/OPC package primitives, TAR
PAX path/size/owner/access-time/change-time metadata parsing, duplicate PAX
keyword rejection, GNU long-name metadata, GNU long-link rejection, typeflag
`7` contiguous file handling, trailing-slash regular-entry directory
normalization, TAR end-marker validation, TAR drive-letter rejection, base-256
numeric decoding, TAR sparse/link/device rejection, or generic TAR/ZIP
package-kind detection.

## Next

Keep nested archive discovery, encrypted archive preflight, sparse-file
reconstruction, hardlink/symlink extraction policy, non-deflate ZIP methods,
dictionary-backed LZ4 frames, and full upstream-runner parity as separate
bounded slices.
