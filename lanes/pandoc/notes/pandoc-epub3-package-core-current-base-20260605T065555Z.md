# Pandoc EPUB3 Package Core Current Base

Slice: `pandoc-epub3-package-core-current-base-20260605T065555Z`
Base accepted HEAD: `b70b567aca418540e07049329182483d4bd89175`

## Behavior Added

- Added bounded EPUB OPF `media:duration` handoff to `EpubReader`.
- Package-level duration metadata is exposed as `mediaDurations.total` with
  the raw SMIL clock value and bounded seconds conversion.
- `media:duration` entries with `refines="#overlay-id"` now resolve to OPF
  SMIL media-overlay manifest items and include manifest href/target/part,
  media type, and the spine content items that reference the overlay.
- Parsed `mediaOverlays` now carry `duration`, `durationSeconds`, and the full
  `durationMetadata` entry so WordPress import queues can review audio timing
  without running media players or EPUBCheck.
- Invalid SMIL clock values and refinements pointing at missing or non-SMIL
  manifest items are kept as diagnostics instead of blocking XHTML handoff.
- The WordPress EPUB3 package example self-test now covers publication and
  overlay duration metadata.

## Source Truth

- W3C EPUB 3.3 defines `media:duration` in the media overlays vocabulary and
  describes it as the duration of the full presentation or a specific media
  overlay document: `https://w3c.github.io/epub-specs/epub33/core/`.
- W3C EPUB Media Overlays 3.2 documents package metadata using a
  publication-level `media:duration` plus `refines` entries for each overlay
  manifest item: `https://w3c.github.io/epub-specs/archive/epub32/spec/epub-mediaoverlays.html`.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 467 assertions, 0 failures.

Red-first after adding media-duration expectations:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed: 1 test file, 468 assertions, 1 failure because `mediaDurations`
  was absent from the package result.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 499 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed: `epub3 package handoff self-test ok`.
- `php -l lanes/pandoc/src/EpubReader.php` passed with no syntax errors.
- `php -l lanes/pandoc/tests/EpubReaderTest.php` passed with no syntax
  errors.
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php` passed
  with no syntax errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `725 -> 726`.
- mapped native checks: `1184 -> 1185`.
- EPUB3 package focused cases: `21 -> 22`.
- EPUB3 package focused assertions: `467 -> 499`.
- This slice adds `+1` EpubReader PASS case and `+32` focused EpubReader
  assertions over the accepted baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcPackagePath`, existing OPF metadata parsing, existing manifest lookup, and
existing SMIL media-overlay parsing. Full upstream Pandoc runner parity remains
blocked by the missing hydrated Pandoc checkout and Haskell Cabal dependency
closure already recorded in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
metadata/DC/meta raw extraction, metadata refinements, metadata link byte
resolution, accessibility metadata, OPF manifest/spine parsing, direct XHTML
spine handoff, nav/NCX, landmarks/page-list navigation, OPF guide/collections,
alternate renditions, spine page-progression/page-spread metadata, OCF
encryption/obfuscated-font preflight, SMIL media-overlay XML parsing, remote
nav/NCX/SMIL reference retention, OPF fallback chain resolution, package asset
export reporting, remote OPF manifest resource reporting, OPF binding-handler
reporting, or OPF manifest resource-property reporting.

The new surface is only OPF media-duration metadata handoff and diagnostics for
publication-level and SMIL overlay-refined duration entries.

## Exclusions

Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
KaTeX, Typst, browser renderers, EPUBCheck, audio/media tooling, handler
runtimes, remote fetches, roff, decryption helpers, font deobfuscators, online
sanitizers, or online services.

## Follow-Up

Keep richer SMIL clock grammar, media-overlay duration completeness checks,
XHTML-to-AST conversion, CSS cascade/resource policy, media extraction/export
policy, remote-resource security policy, multiple-rendition selection UX,
EPUBCheck-style validation, and deeper reading-system layout behavior as
separate bounded EPUB slices.
