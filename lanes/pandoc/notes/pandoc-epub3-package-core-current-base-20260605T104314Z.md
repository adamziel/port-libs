# pandoc-epub3-package-core-current-base-20260605T104314Z

Base: `c6b8bdd91e9129ca076584776bb76e4fcded4d0c`

## Source Truth

- Existing lane package contract: implement native PHP EPUB package behavior
  under `lanes/pandoc/**` without shelling out to Pandoc, zip/unzip,
  browser renderers, EPUBCheck, online services, remote fetches, or JavaScript.
- Pandoc's EPUB reader eventually imports XHTML content documents and their
  resources into the shared document model. This slice keeps the current raw
  XHTML handoff but adds bounded package-resource evidence for review queues:
  local references, missing package targets, remote references that must not be
  fetched, and embedded MathML/SVG/script markers.
- The pinned upstream Pandoc Haskell runner is not hydrated in this worktree,
  so this is bounded native EPUB3 package support, not upstream runner parity.

## Implementation

- Added an aggregate `xhtmlResourceReport` to `EpubReader` results,
  `importReport`, and document attributes.
- Extended XHTML asset records with:
  `contentResourceFlags`, `contentResourceReviewFlags`, `contentReferences`,
  `contentDiagnostics`, and the underlying `contentResourceReport`.
- Scans common XHTML/SVG reference attributes (`href`, `src`, `data`,
  `poster`, `xlink:href`) relative to the package part with the existing
  `OpcPackagePath` resolver.
- Reports remote references as unfetched diagnostics and missing local package
  targets as review diagnostics without interrupting raw XHTML handoff.
- Detects embedded MathML, SVG, and scripted XHTML markers from content rather
  than only trusting OPF `properties`.
- Updated the WordPress EPUB3 package smoke to assert content-derived review
  flags on the raw HTML block handoff.

## Verification

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 705 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed: `epub3 package handoff self-test ok`.
- `php -l lanes/pandoc/src/EpubReader.php`,
  `php -l lanes/pandoc/tests/EpubReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  passed with no syntax errors.

- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane json ok\n";'`
  passed: `lane json ok`.
- `git diff --check -- lanes/pandoc` passed with no whitespace errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `842 -> 843`.
- mapped native checks: `1301 -> 1302`.
- EPUB3 package focused cases: `28 -> 29`.
- EPUB3 package focused assertions: `665 -> 705`.
- This slice adds `+1` EpubReader PASS case and `+40` focused assertions over
  the accepted EPUB3 baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `EpubReader`,
`ZipPackage`, `OpcPackagePath`, `AstNode`, and `WordPressBlockWriter`. Full
upstream Pandoc runner parity remains blocked by the missing hydrated Pandoc
checkout and Haskell Cabal dependency closure already recorded in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
rootfile selection, OPF metadata/DC/meta raw extraction, metadata refinements,
metadata link byte resolution, accessibility metadata, OPF package prefix
parsing, OPF manifest parsing, basic spine parsing, direct raw XHTML spine
handoff, nav/NCX TOC parsing, landmark navigation extraction, page-list/page
break reporting, OPF guide/collections, alternate renditions, spine
page-progression/page-spread metadata, invalid `itemref linear` diagnostics,
OCF encryption/obfuscated-font preflight, SMIL media-overlay parsing, remote
nav/NCX/SMIL reference retention, OPF fallback chain resolution, package asset
export reporting, remote OPF manifest resource reporting, OPF binding-handler
reporting, OPF manifest resource-property reporting, OPF `media:duration`
metadata, SMIL clip timing normalization, package/resource/itemref refinement
handoff, or all-non-linear spine diagnostics.

The new surface is only content-derived XHTML package-resource review evidence
for existing XHTML assets and raw HTML block attributes.

## Exclusions

Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
KaTeX, Typst, browser renderers, EPUBCheck, media players, handler runtimes,
remote fetches, JavaScript execution, decryption helpers, font deobfuscators,
online sanitizers, or online services.

## Follow-Up

Keep full XHTML-to-AST conversion, CSS cascade/resource policy, media
extraction/export policy, remote-resource fetching policy beyond unfetched
diagnostics, multiple-rendition selection UX, OPF refinement-cycle validation,
EPUBCheck-style validation, and deeper reading-system layout behavior as
separate bounded EPUB slices.
