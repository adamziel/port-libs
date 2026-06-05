# Pandoc EPUB3 Package Core Current Base

Slice: `pandoc-epub3-package-core-current-base-20260605T052001Z`
Base accepted HEAD: `689a1d63f07b4ac9ee6dd4da0f28692001c18354`

## Behavior Added

- Added bounded OPF manifest resource-property reporting to `EpubReader`.
- Manifest items now carry deterministic `resourceFlags` and
  `resourceReviewFlags` for EPUB `nav`, `cover-image`, `mathml`, `svg`,
  `remote-resources`, `scripted`, and `switch` properties.
- `readPackage()` now exposes `resourceProperties` at top level,
  `importReport.resourceProperties`, and `document.resourceProperties`.
- XHTML spine handoff blocks now carry the content document's
  `resourceReviewFlags`, so WordPress review queues can flag MathML, SVG,
  remote-resource, scripted, and switch-bearing content without parsing CSS,
  executing scripts, rendering, fetching remote media, or invoking Pandoc.
- Manifest asset reports also preserve the resource flags for non-XHTML
  publication resources.
- Updated the WordPress EPUB3 handoff smoke with a package chapter marked for
  MathML, inline SVG, and remote-resource review plus a scripted fallback
  handler.

## Source Truth

- W3C EPUB 3.3 requires the manifest to list publication resources and uses
  manifest `item` `properties` to describe resources that contain scripting,
  MathML, SVG, remote resources, navigation, cover images, or switch content:
  `https://w3c.github.io/epub-specs/epub33/core/`.
- This slice only preserves package-declared resource metadata. It does not
  inspect or validate XHTML internals beyond the existing raw-spine handoff.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 369 assertions, 0 failures.

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed: 1 test file, 370 assertions, 1 failure. The new resource-property
  test failed because `resourceProperties` was absent.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 396 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed: `epub3 package handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `649 -> 650`.
- mapped native checks: `1125 -> 1126`.
- EPUB3 package focused cases: `16 -> 17`.
- EPUB3 package focused assertions: `369 -> 396`.
- This slice adds `+1` EpubReader PASS case and `+27` focused EpubReader
  assertions over the accepted baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcPackagePath`, `AstNode`, `WordPressBlockWriter`, and the existing OPF
manifest parsing paths. Full upstream Pandoc runner parity remains blocked by
the missing hydrated Pandoc checkout and Haskell Cabal dependency closure
already recorded in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
metadata/DC/meta extraction, OPF metadata links/refinements, manifest/spine
parsing, direct XHTML spine handoff, nav/NCX, landmarks/page-list navigation,
OPF guide/collections, alternate renditions, OCF encryption/obfuscated-font
preflight, SMIL media-overlay parsing, remote nav/NCX/SMIL reference
retention, OPF fallback chain resolution, package asset export reporting,
remote OPF manifest resource reporting, or OPF binding-handler reporting.

The new surface is only the package-level handoff of OPF manifest resource
property declarations and their WordPress review flags.

## Exclusions

Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
KaTeX, Typst, browser renderers, media players, handler runtimes, remote
fetches, roff, decryption helpers, font deobfuscators, online sanitizers, or
online services.

## Follow-Up

Keep richer XHTML-to-AST conversion, CSS cascade/resource policy, remote
resource fetching/security policy beyond unfetched diagnostics and declared
resource-property flags, linked-record schema interpretation, handler
execution policy beyond static OPF binding diagnostics, multiple-rendition
selection UX, and broader EPUBCheck-style validation as separate bounded EPUB
slices.
