# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T194326Z`
Base accepted HEAD: `f281a354364ddf14101a5176b72ed27f0c7958ca`
Date: 2026-06-05 UTC

No current Pandoc rework note was present under
`.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Behavior

- Added shared stored-first entry preflight to `ZipPackage`.
- `ZipPackage::storedFirstEntryPreflight()` reports whether a package part is
  the first local-header entry, uses stored compression, has no local or
  central ZIP extra fields, and matches expected bytes exactly.
- `ZipPackage::assertStoredFirstEntry()` exposes the same policy as a reader
  guard with package-specific diagnostics.
- Strict `EpubReader` and `OdfReader` mimetype admission now reuse the shared
  ZIP policy for EPUB/ODT containers.
- The WordPress ZIP package preflight smoke now reports stored-first ODT
  mimetype admission and extra-field rejection.

## Source Truth

Pandoc consumes EPUB and ODT as ZIP-backed containers. Their `mimetype` package
entry is a fixed-byte structural marker that must be first in local-header
order, stored rather than compressed, and free of ZIP extra fields before a
strict reader treats the archive as an ODT/EPUB package. This bounded native PHP
support-library slice ports that ZIP package contract without invoking Pandoc,
`ZipArchive`, zip/unzip, Word, LibreOffice, or any external archive/conversion
tool.

## Verification

Focused ZIP package verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 545 assertions, 0 failures
```

Focused reader regression verification:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1006 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1009 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Syntax, JSON, and diff checks:

```text
php -l lanes/pandoc/src/ZipPackage.php
php -l lanes/pandoc/src/EpubReader.php
php -l lanes/pandoc/src/OdfReader.php
php -l lanes/pandoc/tests/ZipPackageTest.php
php -l lanes/pandoc/tests/EpubReaderTest.php
php -l lanes/pandoc/tests/OdfReaderTest.php
php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php
php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'
git diff --check -- lanes/pandoc
```

Root harness: not run - isolated micro-slice.

## Delta

- Focused ZIP package PASS cases: adds 1 PASS case.
- Focused ZIP package assertions: current suite is 545 assertions.
- Manifest mapped checks: `1504 -> 1505`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 172`.
- Lane PHP pass count: `1051 -> 1052`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, strict EPUB/ODF readers, in-process package test builders,
package preflight example, and focused PHP test harness.

Full upstream Pandoc runner parity remains gated on hydrating/building the
pinned Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but
stored-first mimetype package admission is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, local entry order,
data descriptors, CRC/local-header integrity, central/local extra-field
parsing, duplicate extra-field IDs, extended or NTFS timestamps, ZIP64
extra-field or EOCD rejection, Unix symlink/special-file rejection, compression
method policy, payload read-integrity preflight, archive-compression streams,
OPC relationships, DOCX body/properties/styles/media parsing, EPUB spine/nav,
ODF content/styles/meta mapping, table geometry, doctemplates, math/TeX,
syntax highlighting, or Markdown/HTML reader/writer behavior. It owns only the
shared stored-first mimetype preflight used by strict EPUB/ODT readers.

## Follow-Up

Keep permissive reader compatibility choices, full ZIP64 large-archive support,
spanning archives, AES/encrypted payload handling, cryptographic
central-directory signature validation, and package-specific media policy as
separate bounded slices unless a concrete DOCX/ODT/EPUB fixture requires one
earlier.
