# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T022818Z`

Base accepted HEAD: `314b4b94d04b24d343511693d1f213bac248820d`

## Implementation

- Added bounded tar stream auto-detection to `ArchiveCompressionStream`.
- `detectTarFormat()` validates candidate wrappers and returns the single
  detected tar format.
- `decodeTarBytesAuto()` returns decoded tar bytes only after the stream
  validates as exactly one bounded `TarArchive` candidate.
- `openTarAuto()` opens the validated detected candidate through the existing
  tar reader while preserving independent compressed-byte and unpacked-byte
  limits.
- Candidate detection covers plain tar, gzip-tar, zlib-wrapped DEFLATE tar,
  raw-DEFLATE tar, and LZ4-framed tar with skippable metadata.
- The WordPress ZIP/package preflight smoke now uses auto-detection for its
  gzip-wrapped tar review packet.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Pandoc-side package fixtures and WordPress import review packets often
arrive as opaque bytes where extension metadata is not trusted. The bounded
native contract here is: try only the known support wrappers, decode under the
configured byte limit, validate the result as a safe tar packet, and expose
bytes only when exactly one candidate succeeds.

It does not implement compressed ZIP dispatch, recursive nested archive
discovery, multi-member tar concatenation policy, tar sparse files,
hardlink/symlink materialization, encrypted archive preflight, filesystem
extraction, or registry-specific gzip subfield semantics.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 152 assertions, 0 failures`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 183 assertions, 0 failures`.
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `541 -> 543`.
- `benchmarkDenominator.mapped`: `1018 -> 1020`.
- `archiveCompressionStreamCoreCases`: stale manifest counter corrected from
  `10` to the current focused `18`; focused test file moved from `16 -> 18`
  PASS cases in this slice.
- `archiveCompressionStreamCoreAssertions`: stale manifest counter corrected
  from `101` to the current focused `183`; focused test file moved from
  `152 -> 183` assertions in this slice.

## Dependency Closure

No new external support component is needed. This composes existing native PHP
`GzipStream`, `DeflateStream`, `Lz4Frame`, and `TarArchive` helpers. Full
upstream Pandoc runner parity remains blocked on hydrating and building the
pinned Haskell test executables; this archive auto-detection work does not need
that runner and does not introduce a new dependency.

## Non-Overlap

This does not repeat accepted explicit archive dispatch, gzip member framing,
gzip extra subfield validation, POSIX tar regular file/directory handling, PAX
metadata, GNU long-name metadata, raw/zlib DEFLATE wrapper validation,
independent/skippable/dependent LZ4 frame decoding, ZIP/OPC package primitives,
XML/HTML5 DOM helpers, DOCX/ODT/EPUB readers, doctemplates, YAML metadata,
CSL/BibTeX, table geometry, math/TeX conversion, PDF handoff planning, legacy
DOC/CFB, charset, syntax highlighting, or Markdown/HTML reader and writer
behavior.

## Follow-Up

Keep compressed ZIP dispatch, recursive nested archive policy, multi-member tar
concatenation policy, tar sparse files, hardlink/symlink materialization
policy, encrypted archive preflight, filesystem extraction, and
registry-specific gzip subfield semantics as separate bounded slices unless
concrete Pandoc package fixtures require them.
