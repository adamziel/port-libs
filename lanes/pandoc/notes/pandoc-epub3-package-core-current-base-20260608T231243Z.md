# pandoc-epub3-package-core-current-base-20260608T231243Z

Slice: EPUB3 NCX navList aggregate navigation handoff on accepted base
`2a117f9ba2effc54e8f915363aa5ed476910dbad`.

## Behavior

Legacy NCX files can carry supplemental `navList` / `navTarget` groups outside
the primary `navMap`. `EpubReader` already parsed those groups into the raw
NCX report, but the aggregate `navigation` review packet only counted XHTML
nav TOC entries and NCX `navMap` entries.

This slice exposes NCX `navList` targets as supplemental aggregate navigation
metadata:

- `ncxNavListCount` and `ncxNavListTargetCount`.
- `supplementalTargetCount`, mapped-spine, external, missing, and
  outside-spine counters.
- `supplementalItems` with source `ncx-nav-list`, list index/id/title
  provenance, target resolution, spine mapping, and diagnostics.
- `supplementalDiagnostics` for external/missing supplemental targets.

The primary `targetCount`, `mappedSpineTargetCount`, `ncxCount`, and linear
spine coverage semantics are intentionally unchanged so supplemental legacy
review lists do not satisfy primary reading-order navigation coverage.

## Focused Evidence

- Baseline before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 3110 assertions, 0 failures`.
- Red-first after adding focused assertions:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed with `1 test files, 3111 assertions, 1 failures` because
  `navigation.ncxNavListCount` was absent.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 3134 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed with `epub3 package handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/EpubReader.php`,
  `php -l lanes/pandoc/tests/EpubReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  passed.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`,
`EpubReader`, `OpcPackagePath`, DOM/libxml XML parsing, existing package
reference resolution, and the WordPress EPUB3 package handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip,
ZipArchive, EPUBCheck, browser renderer, JavaScript/media execution, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This patch does not repeat accepted OCF mimetype/container/rootfile validation,
OCF metadata/rights/signature sidecars, OPF metadata/manifest/spine parsing,
OPF prefix vocabulary resolution, raw XHTML spine handoff, nav XHTML parsing,
NCX `navMap`/`pageList` target resolution, NCX head/docTitle/docAuthor
metadata, navigation/spine reconciliation, guide/collection links, alternate
renditions, spine and asset fallback chains, bindings, media overlays,
trigger/switch review flags, remote-resource reconciliation, encryption,
obfuscated-font preflight, EPUB CFI fragments, or ZIP package integrity work.

The new surface is only aggregate review metadata for already parsed legacy
NCX `navList` supplemental targets.

## Follow-Up

Keep fuller NCX role/type validation, XHTML-to-AST conversion, CSS cascade/media
export policy, active media playback, EPUBCheck validation, remote-resource
policy, and full Haskell/Pandoc runner comparison as separate bounded slices.
