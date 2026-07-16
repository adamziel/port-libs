# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260607T125750Z`
Base accepted HEAD: `2e4b6f0eabfb7def5602d165b8fc29ed2ef60bb3`

## Behavior

GNU tar documents multi-volume archive state through the old GNU `M` typeflag and PAX `GNU.volume.*` metadata. This slice keeps Pandoc package import fail-closed for those split entries:

- `TarArchive::fromString()` now rejects GNU multi-volume typeflag `M` and non-empty `GNU.volume.*` PAX metadata before package entries are exposed.
- `TarArchive::multiVolumePolicyPreflight()` provides metadata-only diagnostics for blocked split-volume entries, including typeflag/PAX family, continuation offset source, original filename, declared volume size, payload size, and entry layout offsets.
- `ArchiveCompressionStream::inspectTarMultiVolumePolicy()` applies the same policy after bounded gzip/zlib/raw/lz4 TAR decode and includes compressed stream provenance.
- `wordpress-archive-stream-preflight.php` now includes a gzip-wrapped multi-volume TAR policy smoke with extraction blocked.

Source truth:

- GNU tar internals manual, `typeflag` values including multi-volume behavior: https://www.gnu.org/s/tar/manual/html_chapter/Tar-Internals.html
- GNU tar formats manual, POSIX/GNU volume metadata context: https://www.gnu.org/software/tar/manual/html_chapter/Formats.html

## Evidence

Baseline focused test:

`php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`

Result: `1 test files, 912 assertions, 0 failures`.

Final focused test:

`php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`

Result: `1 test files, 953 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`

Result: `wordpress-archive-stream-preflight self-test passed`.

Syntax and whitespace checks:

- `php -l lanes/pandoc/src/TarArchive.php` passed.
- `php -l lanes/pandoc/src/ArchiveCompressionStream.php` passed.
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php` passed.
- `git diff --check -- lanes/pandoc` passed.

## Dependency Closure

No new support component is needed. The slice reuses native `TarArchive`, `ArchiveCompressionStream`, `GzipStream`, focused PHP fixtures, and the existing WordPress archive stream preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Stack, tar, gzip, zip/unzip, lz4, ZipArchive, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.
