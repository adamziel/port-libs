# Pandoc ZIP Package Core Current-Base Slice

Slice: `pandoc-shared-zip-package-core-current-base-20260607T093656Z`
Base accepted HEAD: `b86d159cdf99a07a68249d9af6c697b1a15bfa78`
Date: 2026-06-07 UTC

## Behavior

`ZipPackageEntry` now rejects ZIP NTFS extra fields whose four reserved bytes
are nonzero before parsing timestamp attributes or exposing package entries.
This keeps malformed DOCX/EPUB/ODT media package metadata from reaching the
WordPress preflight path.

Source-truth boundary: the bounded native ZIP reader treats the NTFS extra
field as four reserved bytes followed by tagged attributes. The existing parser
already failed closed on truncated NTFS attribute headers/payloads and wrong
timestamp attribute sizes; this slice adds the missing reserved-byte guard.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 1019 assertions, 0 failures`.
- Red-first: the same focused command failed with `1 test files, 1019
  assertions, 1 failures` because the nonzero-reserved NTFS package was
  accepted.
- Final: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 1020 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php
  --self-test` passed with `zip package writer preflight self-test passed`.
- PHP lint passed for `ZipPackageEntry.php`, `ZipPackageTest.php`, and
  `wordpress-zip-package-preflight.php`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `1484 -> 1485`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1902 -> 1903`.
- ZIP package support cases: `22 -> 23`.
- Mapped ZIP package support cases: `22 -> 23`.
- Mapped ZIP NTFS extra-field checks: `7 -> 8`.
- ZIP package focused assertions: `161 -> 162`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`ZipPackage`/`ZipPackageEntry` extra-field parsing, byte-built ZIP fixtures,
the focused Pandoc lane test runner, and the WordPress ZIP package preflight
example. No Pandoc, Cabal/Haskell runner, zip/unzip, ZipArchive, Word,
LibreOffice, external archive tool, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat the accepted ZIP central-directory signature, Unicode name
collision, invalid DOS timestamp, trailing-deflate, ZIP64, data-descriptor,
symlink, path, or timestamp round-trip slices. It only tightens malformed NTFS
reserved-byte validation before package entry exposure.
