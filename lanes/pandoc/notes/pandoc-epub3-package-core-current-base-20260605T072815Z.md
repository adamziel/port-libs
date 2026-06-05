# Pandoc EPUB3 Package Core Current Base

Slice: `pandoc-epub3-package-core-current-base-20260605T072815Z`
Base accepted HEAD: `6ab7d921878968a04f160c754722667c2cd32bc9`

## Behavior Added

- Added bounded EPUB3 SMIL media-overlay clip timing handoff to `EpubReader`.
- SMIL `audio` `clipBegin` and `clipEnd` values now keep the original strings
  and also expose `clipBeginSeconds`, `clipEndSeconds`, `clipDurationSeconds`,
  `clipValid`, and `clipDiagnostics` on each parsed overlay item.
- Existing SMIL clock parsing is reused for full clocks, partial clocks, and
  metric values such as `1.25s` and `2250ms`.
- Invalid clip clocks and `clipEnd` values earlier than `clipBegin` stay as
  item-level diagnostics instead of blocking XHTML spine handoff or fetching
  audio.
- The WordPress EPUB3 package smoke now asserts the normalized clip durations
  for local and remote audio references without running media tooling.

## Source Truth

- W3C EPUB Media Overlays use SMIL `par` items with text/audio references and
  audio clip timing for synchronized reading order review.
- W3C EPUB 3 package behavior keeps media-overlay resources as package
  resources; this slice only preserves and normalizes declared timing metadata.

## Verification

Focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 535 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed: `epub3 package handoff self-test ok`.
- `php -l lanes/pandoc/src/EpubReader.php` passed with no syntax errors.
- `php -l lanes/pandoc/tests/EpubReaderTest.php` passed with no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php` passed
  with no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane json ok\n";'`
  passed: `lane json ok`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `744 -> 745`.
- mapped native checks: `1203 -> 1204`.
- EPUB3 package focused cases: `22 -> 23`.
- EPUB3 package focused assertions: `499 -> 535`.
- This slice adds `+1` EpubReader PASS case and `+36` focused EpubReader
  assertions over the accepted EPUB3 baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcPackagePath`, the existing SMIL media-overlay parser, and the existing
bounded SMIL clock parser. Full upstream Pandoc runner parity remains blocked
by the missing hydrated Pandoc checkout and Haskell Cabal dependency closure
already recorded in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
metadata/DC/meta raw extraction, metadata refinements, metadata link byte
resolution, accessibility metadata, OPF manifest/spine parsing, direct XHTML
spine handoff, nav/NCX, landmarks/page-list navigation, OPF guide/collections,
alternate renditions, spine page-progression/page-spread metadata, OCF
encryption/obfuscated-font preflight, SMIL media-overlay XML parsing, remote
nav/NCX/SMIL reference retention, OPF fallback chain resolution, package asset
export reporting, remote OPF manifest resource reporting, OPF binding-handler
reporting, OPF manifest resource-property reporting, or OPF `media:duration`
metadata.

The new surface is only normalized per-item SMIL audio clip timing and
diagnostics for invalid or reversed clip ranges.

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
