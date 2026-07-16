# Pandoc Archive Compression Streams Current Base 2026-06-08T05:46:30Z

Lane: pandoc

Micro-slice: `pandoc-archive-compression-streams-current-base-20260608T054630Z`

Accepted base: `4cdbc422e45adc25f1ad62ce24e13ad1c7bd277e`

## Behavior

This slice tightens bounded native TAR package preflight by rejecting ASCII C0
control bytes and DEL in effective TAR paths before any package entry is
exposed. The shared `TarArchive::assertSafePath()` guard now covers ustar header
names, PAX `path` overrides, PAX `linkpath` metadata, GNU long-name metadata,
and generated entries from `TarArchive::fromEntries()`.

The WordPress archive stream preflight example now includes a control-byte TAR
name smoke so review packets fail closed before hidden filename bytes reach
package import UI.

## Source Truth And Scope

The lane maps the Pandoc archive/package support-library contract, not external
archive extraction behavior. TAR/PAX metadata can carry byte strings, but this
native PHP lane exposes only UTF-8, package-safe path names to DOCX/ODT/EPUB and
WordPress review handoffs. Invisible control bytes are rejected alongside the
existing absolute path, traversal, drive-letter, backslash, invalid UTF-8, and
unsupported TAR entry policies.

No Pandoc, Cabal solver/build/test command, Haskell runner, tar, gzip,
zip/unzip, lz4, ZipArchive, Word, LibreOffice, external archive tool, online
service, live provider test, or live-service provider test was executed.

## Verification

- Rework notes: no matching `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` note existed.
- Baseline focused command: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 1467 assertions, 0 failures`.
- Red-first focused command: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` failed as expected with `1 test files, 1468 assertions, 1 failures`; the new control-byte TAR path case did not throw before implementation.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 1472 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` passed with `wordpress-archive-stream-preflight self-test passed`.
- Syntax checks: `php -l` passed for `lanes/pandoc/src/TarArchive.php`, `lanes/pandoc/tests/ArchiveCompressionStreamTest.php`, and `lanes/pandoc/examples/wordpress-archive-stream-preflight.php`.
- JSON validation: `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` parsed with `JSON_THROW_ON_ERROR`.
- Final hygiene command: `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `1542 -> 1543`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1963 -> 1964`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 125`.
- Focused archive coverage: `1467 -> 1472` assertions.

## Dependency Closure

No new support component is needed. The slice reuses the existing native
`TarArchive`, `ArchiveCompressionStream`, `GzipStream`, focused archive tests,
and the WordPress archive stream preflight example.

## Non-Overlap And Follow-Up

This does not change gzip member framing, raw/zlib deflate, LZ4 dictionaries,
ZIP package parsing, PAX timestamp or hdrcharset policy, duplicate PAX keyword
policy, sparse or multi-volume reconstruction, special file handling, archive
bomb thresholds, encrypted archive preflight, or external extraction behavior.

Useful follow-up remains: propagate archive-bomb thresholds into concrete
readers, add encrypted package handoff diagnostics where missing, add sparse
reconstruction review metadata without exposing sparse bytes, and cover
non-deflate ZIP compression policy gaps.
