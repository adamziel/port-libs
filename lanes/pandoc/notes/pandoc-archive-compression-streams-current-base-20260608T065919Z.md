# Pandoc archive compression streams current base

- Lane: pandoc
- Slice: pandoc-archive-compression-streams-current-base-20260608T065919Z
- Base accepted HEAD: ffcd9253ba667545698caf23a94d2a208517e323

## Behavior

Added dictionary-aware LZ4 package stream inspection for bounded TAR and ZIP
handoff fixtures. The new ArchiveCompressionStream entrypoints accept explicit
external dictionaries keyed by LZ4 Dict-ID, decode the supplied dictionary-backed
frames through Lz4Frame, and preserve normal package inspection output plus
stream provenance:

- `inspectPackageStreamWithLz4Dictionaries()`
- `inspectTarStreamWithLz4Dictionaries()`
- `inspectZipStreamWithLz4Dictionaries()`

Default archive inspection remains fail-closed for LZ4 dictionary frames unless a
caller supplies matching fixture dictionaries. The stream metadata reports frame
counts, skippable metadata frames, dictionary frame counts, dictionary IDs,
dictionary sizes, supplied-dictionary flags, block types, compressed sizes, and
decoded data sizes so Pandoc package preflight can separate missing-dictionary
blockers from malformed package streams.

## Source Truth

This slice ports the bounded LZ4 external-dictionary package contract needed for
Pandoc support fixtures. It reuses the existing native Lz4Frame dictionary decode
behavior and existing TAR/ZIP package readers. It does not shell out to Pandoc,
Cabal/Haskell runners, tar, zip/unzip, lz4, ZipArchive, office tools, TeX/PDF
engines, online services, live provider tests, or live-service provider tests.

## Verification

- Baseline before change: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `1 test files, 1507 assertions, 0 failures`
- Red-first check after adding the focused test only:
  - `1 test files, 1507 assertions, 1 failures`
  - Failure: `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries()`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `1 test files, 1547 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - `wordpress-archive-stream-preflight self-test passed`
- PHP lint:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
- JSON validation:
  - `lanes/pandoc/lane-status.json valid`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json valid`
- Whitespace check: `git diff --check -- lanes/pandoc`
  - no output
- Root harness: not run - isolated micro-slice

## Status Delta

- Focused archive test assertions: 1507 -> 1547 (+40)
- Focused PASS cases: +1
- `lane-status.json` `phpPass`: 1554 -> 1555
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: 1975 -> 1976
- Archive compression stream mapped core cases: 11 -> 12
- Archive compression stream focused assertions: 120 -> 160

## Dependency Closure

No new support component is needed. The slice reuses the existing native
Lz4Frame, ArchiveCompressionStream, TarArchive, ZipPackage, focused archive
tests, and WordPress archive preflight example.

## Non-Overlap

This does not repeat existing gzip framing/provenance, raw/zlib deflate,
preset-dictionary zlib package inspection, LZ4 decode-only dictionary policy,
TAR PAX timestamp/charset/duplicate-key/sparse/multivolume/incremental/link/
special-file policies, nested package discovery, archive-bomb ratio checks, or
ZIP package primitives. It only wires external-dictionary LZ4 frame inspection
into package-level TAR/ZIP handoff output.

## Follow-Up

- Add multi-frame dictionary-backed LZ4 package inspection cases if a real
  Pandoc package fixture needs split frame provenance.
- Add explicit bzip2/xz exclusion diagnostics if future package fixtures require
  unsupported compression reporting.
- Extend nested archive review limits only after a real Pandoc package fixture
  needs deeper recursive inspection.
