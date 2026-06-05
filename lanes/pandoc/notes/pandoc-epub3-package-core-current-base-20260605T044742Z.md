# Pandoc EPUB3 Package Core Current Base

Slice: `pandoc-epub3-package-core-current-base-20260605T044742Z`
Base accepted HEAD: `7ab42d625cf7e087d60c6d4170fd43b20e2c75a0`

## Behavior Added

- Added bounded OPF metadata refinement grouping to `EpubReader`.
- Package metadata now exposes `metadata.refinementsById`, keyed by the
  referenced metadata id from `meta refines="#..."` and then by OPF property.
- Dublin Core metadata entries with an `id` now carry their matched
  `refinements` group directly, so identifier, creator, and title review data
  stays attached to the base metadata entry.
- Refinement records preserve property, subject id, original `refines`, text,
  content, scheme, id, and `xml:lang` values.
- Updated the WordPress EPUB3 handoff smoke to expose identifier-type and
  creator file-as/role/display-seq refinements for import review.

## Source Truth

- W3C EPUB 3.3 package metadata allows `meta` entries to refine publication
  metadata using `refines`, including common properties such as `identifier-type`,
  `file-as`, `role`, `display-seq`, `title-type`, and `alternate-script`:
  `https://www.w3.org/TR/epub-33/`.
- This slice only preserves and groups the package metadata facts. It does not
  interpret ONIX code lists, MARC relator semantics, alternate-script display
  policy, linked metadata schemas, or remote resources.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 353 assertions, 0 failures.

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed: 1 test file, 354 assertions, 1 failure. The new refinement test
  failed because `metadata.refinementsById` was absent.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 369 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed: `epub3 package handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `631 -> 632`
- mapped native checks: `1,106 -> 1,107`
- EPUB3 package focused cases: `15 -> 16`
- EPUB3 package focused assertions: `353 -> 369`
- This slice itself adds `+1` EpubReader PASS case and `+16` focused
  EpubReader assertions over the accepted baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcPackagePath`, `XmlHtmlDom`, and the existing EPUB OPF metadata parser.
Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
metadata/DC/meta extraction, OPF metadata `<link>` records, manifest/spine
parsing, direct XHTML spine handoff, nav/NCX, landmarks/page-list navigation,
OPF guide/collections, alternate renditions, OCF encryption/obfuscated-font
preflight, SMIL media-overlay parsing, remote nav/NCX/SMIL reference retention,
OPF fallback chain resolution, package asset export reporting, remote OPF
manifest resource reporting, or OPF binding-handler reporting.

The new surface is only grouped OPF `meta refines="#..."` handoff for package
metadata review.

## Exclusions

Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
KaTeX, Typst, browser renderers, media players, handler runtimes, remote
fetches, roff, decryption helpers, font deobfuscators, online sanitizers, or
online services.

## Follow-Up

Keep richer metadata vocabulary interpretation, ONIX/MARC code-list
normalization, alternate-script UI policy, XHTML-to-AST conversion, CSS
cascade/resource policy, remote resource fetching/security policy, multiple
rendition selection UX, and broader EPUBCheck-style validation as separate
bounded EPUB slices.
