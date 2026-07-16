# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260609T020928Z`
Base: `ae05f994f04ccc78db62e7bd6dd42669f76246b1`
Lane: `pandoc`

## Scope

This slice adds bounded native PHP TAR metadata-record layout reporting for
compressed archive streams. `ArchiveCompressionStream::inspectTarStream()` and
`ArchiveCompressionStream::inspectTarStreamAuto()` now expose
`metadataLayoutCount` and `metadataLayouts` for PAX/GNU metadata records.

Each layout is derived from `TarArchive::checksumPolicyPreflight()` and records
the metadata record role/type, TAR byte offsets, payload sizes, PAX key
summaries, checksum policy diagnostics, and decoded stream source segments. The
layout intentionally exposes `metadataValueSize` instead of metadata payload
bytes so WordPress review/import packets can audit archive provenance without
dumping arbitrary metadata contents.

## Source Truth And Non-Overlap

This is native bounded support-library work for Pandoc package/archive import
coverage. It does not invoke Pandoc, Cabal/Haskell runners, Word, LibreOffice,
tar, gzip, zip/unzip, lz4, external archive tools, online services, live
provider tests, or live-service provider tests.

The slice does not repeat the already accepted gzip header/trailer integrity,
raw-deflate member decoding, TAR checksum/path/directory/file extraction, TAR
entry layouts, LZ4 frame/skippable block handling, ZIP64 extra-field handoff,
nested archive policy, unsupported bzip2/xz/zstd diagnostics, or sparse-file
metadata preflight clusters. It fills the archive note follow-up for metadata
record layout source segments.

## Verification Evidence

No current Pandoc rework note was present under
`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 3391 assertions, 0 failures
```

Red-first after adding the metadata-layout test:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 3393 assertions, 1 failures
```

Expected red failure: `metadataLayoutCount` was absent before the
implementation.

Final focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 3413 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test
wordpress-archive-stream-preflight self-test passed
```

Syntax and handoff hygiene:

```text
php -l lanes/pandoc/src/ArchiveCompressionStream.php
No syntax errors detected in lanes/pandoc/src/ArchiveCompressionStream.php

php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php
No syntax errors detected in lanes/pandoc/tests/ArchiveCompressionStreamTest.php

php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php
No syntax errors detected in lanes/pandoc/examples/wordpress-archive-stream-preflight.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok

git diff --check -- lanes/pandoc
(no output; exit 0)
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2124` -> `2125`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2551` -> `2552`
- `archiveCompressionStreamCoreCases`: `11` -> `12`
- `archiveCompressionStreamCoreAssertions`: `120` -> `142`
- Focused `ArchiveCompressionStreamTest.php`: `3391` -> `3413` assertions

## Dependency Closure

No new support component is needed. The slice reuses native
`ArchiveCompressionStream`, `TarArchive::checksumPolicyPreflight()`,
`GzipStream`, focused PHP tests, and the existing WordPress archive-stream
preflight example.

Full upstream Pandoc runner parity remains out of scope for this isolated
micro-slice and blocked by the existing audit: there is no hydrated Pandoc
checkout or Pandoc Cabal package/project file in the permitted local scope, so
running the Haskell test suites would require broad checkout hydration plus
dependency graph builds.

## Follow-Up

Archive-compression follow-up should stay bounded and non-overlapping:
ZIP central-directory comment policy, nested package policy refinement, archive
fixture provenance, filesystem extraction policy metadata, sparse-file
reconstruction, hardlink/symlink materialization, external archive-tool
validation, and full upstream runner parity.
