# Pandoc archive compression streams current base

- Lane: pandoc
- Slice: pandoc-archive-compression-streams-current-base-20260608T062456Z
- Base accepted HEAD: 84117a5c5c86d914c94d81dd6883757bcb9f37e0

## Behavior

Added dictionary-aware zlib package stream inspection for bounded TAR and ZIP
handoff fixtures. The new ArchiveCompressionStream entrypoints accept explicit
fixture dictionaries, decode preset-dictionary zlib streams through DeflateStream,
and preserve normal package inspection output plus stream provenance:

- `inspectPackageStreamWithZlibDictionaries()`
- `inspectTarStreamWithZlibDictionaries()`
- `inspectZipStreamWithZlibDictionaries()`

Default archive inspection remains fail-closed for preset-dictionary zlib
streams unless a caller supplies matching fixture dictionaries. The stream
metadata reports the zlib dictionary ID, dictionary size, dictionary Adler-32,
trailer Adler-32, header bytes, decoded byte count, and consumed compressed
bytes so Pandoc package preflight can distinguish missing-dictionary blockers
from ordinary malformed archive streams.

## Source truth

This slice ports the bounded zlib FDICT/preset-dictionary package contract needed
for Pandoc support fixtures. It uses native PHP zlib APIs and existing local TAR
and ZIP package readers. It does not shell out to Pandoc, tar, gzip, zip, unzip,
lz4, office tools, TeX/PDF engines, or online services.

## Verification

- Baseline before change: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `1 test files, 1467 assertions, 0 failures`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `1 test files, 1507 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - `wordpress-archive-stream-preflight self-test passed`
- PHP lint:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - all reported no syntax errors
- JSON validation:
  - `lanes/pandoc/lane-status.json valid`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json valid`
- Whitespace check: `git diff --check -- lanes/pandoc`
  - no output
- Root harness: not run - isolated micro-slice

## Status delta

- Focused archive test assertions: 1467 -> 1507 (+40)
- Focused PASS cases: +1
- `lane-status.json` `phpPass`: 1551 -> 1552
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: 1972 -> 1973
- Archive compression stream mapped core cases: 11 -> 12
- Archive compression stream focused assertions: 120 -> 160

## Dependency closure

No new support component is needed. The slice reuses the existing native
DeflateStream, ArchiveCompressionStream, TarArchive, ZipPackage, focused archive
tests, and WordPress archive preflight example.

## Non-overlap

This does not repeat existing gzip framing/provenance, raw deflate, ordinary
zlib decode/policy checks, LZ4 dictionary decode/policy, TAR PAX timestamp,
charset, duplicate-key, sparse, multivolume, incremental, link, special-file, or
filesystem-metadata policies, nested package discovery, archive-bomb ratio
checks, or ZIP package primitives. It only wires preset-dictionary zlib stream
inspection into package-level TAR/ZIP handoff output.

## Follow-up

- Add dictionary-aware LZ4 package inspection entrypoints if Pandoc fixtures need
  stream provenance at package preflight time.
- Add explicit bzip2/xz exclusion diagnostics if future package fixtures require
  unsupported compression reporting.
- Extend nested archive review limits only after a real Pandoc package fixture
  needs deeper recursive inspection.
