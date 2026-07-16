# pandoc-epub3-package-core-current-base-20260609T060820Z

Base accepted HEAD: `e69688e6fb90929d5ef223b4abd0518b8b130e83`

## Behavior

Implemented compact native PHP EPUB3 package preflight support for OPF
`media-overlay` references in `EpubPackage`.

`EpubPackage` now preserves manifest and spine `media-overlay` IDs, exposes a
compact `mediaOverlays()` report, and adds WordPress import summary aliases for
media-overlay items, text targets, audio targets, and diagnostics.

The compact report resolves bounded SMIL media overlays from package parts and
records:

- referenced manifest item IDs;
- resolved SMIL part names, byte length, and CRC32 metadata;
- `media:duration` metadata from package-level and overlay-refined OPF meta
  entries;
- SMIL text and audio targets resolved relative to the SMIL part;
- parsed clip begin/end seconds and clip duration;
- missing overlay manifest items;
- unexpected non-SMIL overlay media types;
- missing, remote, and invalid text/audio references;
- invalid clock values.

This is compact `EpubPackage` preflight only. It does not execute playback,
fetch remote audio, invoke EPUBCheck, or change the richer `EpubReader`
timeline model.

## Evidence

Baseline focused verification before this patch:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 282 assertions, 0 failures`

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 324 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- Result: `epub3 package preflight self-test ok`

Syntax and JSON checks:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php -l lanes/pandoc/examples/wordpress-epub3-package-preflight.php`
- Result: no syntax errors
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode((string) file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " ok\n"; }'`
- Result: both JSON files decoded successfully
- `git diff --check -- lanes/pandoc`
- Result: passed

Focused delta: +1 PHP TestRunner PASS case and +42 focused assertions in
`EpubPackageTest.php`. `lane-status.json` moves `phpPass` from `2422` to
`2423`; `UPSTREAM_TEST_MANIFEST.json` moves mapped support from `2811` to
`2812`, `mappedEpub3PackageCoreCases` from `6` to `7`, and
`epub3PackageCoreAssertions` from `112` to `154`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. This slice reuses native PHP
`EpubPackage`, `ZipPackage`, package part lookup, OPF metadata parsing,
DOM/libxml NONET XML parsing, the focused PHP test runner, and the existing
WordPress EPUB3 package preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, EPUBCheck, external converter, browser renderer, online service,
live provider test, or live-service provider test was executed.

Full upstream Pandoc runner parity remains a separate upstream-runner
dependency task requiring hydrated pinned upstream sources and Haskell test
executables.

## Non-Overlap

No active `port-pandoc-*.needs-lane-rework.md` rework note existed before
editing.

This does not repeat accepted OCF container/rootfile handling, compact OPF
metadata refinements, metadata link vocabulary diagnostics, guide/nav/NCX
preflight, media-type bindings, OPF collections, remote-resource policy,
vendor metadata, accessibility metadata, XHTML/CSS resource scans, rich
`EpubReader` media-overlay timeline behavior, OCF sidecars, encryption, asset
fallback, or embedded media/object/frame slices. It is restricted to compact
OPF media-overlay preflight summaries and WordPress import handoff aliases.

## Follow-Up

Good non-overlapping EPUB3 package follow-ups are rendition/layout metadata,
fallback-chain resolution, encrypted-resource review policy, nav/NCX edge
cases, or XHTML-to-AST conversion boundaries.
