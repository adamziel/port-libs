# Pandoc ZIP Package Current-Base Duplicate Slice

Micro-slice: `pandoc-shared-zip-package-core-current-base-duplicate-20260609T072812Z`

Accepted base: `a1a91bfe7d5262693edcff0a01e559e971d0689a`

Date: 2026-06-09 UTC

## Behavior

`ZipPackage` raw strict preflight now records unsafe local-header raw and decoded names before package instantiation. A ZIP entry with a safe central-directory name such as `word/media/review.png` but a local file-header name such as `word/../media/review.png` now reports structured local-header diagnostics:

- `local-header-unsafe-raw-name`
- `local-header-raw-name-parent-directory-segment`
- `local-header-unsafe-decoded-name`
- `local-header-decoded-name-parent-directory-segment`

Strict package construction still rejects the package before any embedded Office media bytes are exposed.

## Evidence

- Rework notes: no matching `port-pandoc-*.needs-lane-rework.md` note was present for this lane before the slice.
- Baseline focused test before this change: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 2736 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 2761 assertions, 0 failures`.
- WordPress ZIP package smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` printed `zip package writer preflight self-test passed`.

## Status Delta

- `phpPass`: `2493 -> 2494`
- Mapped denominator: `2870 -> 2871`
- `zipPackageCoreSupportCases`: `22 -> 23`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`
- `zipPackageCoreAssertions`: `161 -> 186`
- Focused assertion delta: `+25`

## Dependency Closure

No new native support component is needed. This slice reuses the existing pure-PHP `ZipPackage` reader/writer, raw strict preflight scanners, local header metadata scanners, and WordPress ZIP package preflight example. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`/`unzip`, `ZipArchive`, external archive tool, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ZIP creator-host preflight, archive-extra timestamp behavior, NTFS timestamp parsing, ZIP64 EOCD handling, encryption policy, Unix symlink rejection, conflicting central/local metadata, compression method policy, data descriptor handling, local-header span checks, or central/local name mismatch rejection. This slice owns only structured raw strict diagnostics for unsafe local-header names hidden behind otherwise safe central-directory names.

## Next

Choose a non-overlapping ZIP package primitive gap such as generated-package metadata policy, bounded ZIP64 local-header policy, or central-directory repair provenance. Keep the work native PHP and do not execute external archive or document conversion tools.
