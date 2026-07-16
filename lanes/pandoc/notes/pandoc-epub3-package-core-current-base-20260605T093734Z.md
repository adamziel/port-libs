# pandoc-epub3-package-core-current-base-20260605T093734Z

Base: `56b931df2c191390c2ffd199ea6032951839d3df`

## Source Truth

- Existing lane package contract: implement native PHP EPUB package behavior
  under `lanes/pandoc/**` without shelling out to Pandoc, zip/unzip,
  browser renderers, EPUBCheck, online services, or remote fetches.
- W3C EPUB 3.3 package source truth:
  `https://w3c.github.io/epub-specs/epub33/core/`. The spine `itemref`
  `linear` attribute indicates whether a referenced item contributes to the
  primary reading order: `yes` or an omitted attribute is linear, while `no`
  marks auxiliary non-linear content.
- The local Pandoc upstream cache did not expose EPUB-specific test fixture
  files for this slice, and the pinned Haskell runner is not hydrated in this
  worktree. This is bounded native EPUB3 package support, not upstream runner
  parity.

## Implementation

- Added bounded OPF spine itemref `linear` parsing to `EpubReader`.
- Preserved raw `linear` values, whether the attribute was specified, validity,
  and normalized reading-order behavior on each spine item.
- Invalid `linear` values now remain readable as the default linear state while
  producing an `invalid-spine-linear-value` diagnostic in the spine item and
  package-level spine report.
- Propagated the linear metadata and item diagnostics onto raw XHTML AST blocks
  so WordPress import review packets can display malformed reading-order
  metadata at the block boundary.
- Updated the WordPress EPUB3 package handoff smoke to exercise the diagnostic.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 621 assertions, 0 failures`.

Red-first check after adding the focused test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed as expected: `Expected: 'maybe'`, `Actual: NULL` for missing
  `linearRaw`; run summary `1 test files, 624 assertions, 1 failures`.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 646 assertions, 0 failures`.
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

- `phpPass`: `808 -> 809`.
- mapped native checks: `1268 -> 1269`.
- EPUB3 package focused cases: `6 -> 7`.
- EPUB3 package focused assertions: `95 -> 120`.
- Focused EpubReader coverage: `26 PASS / 621 assertions -> 27 PASS / 646
  assertions`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `EpubReader`,
`ZipPackage`, `ZipPackageEntry`, and `OpcPackagePath`. Full upstream Pandoc
runner parity remains blocked by the missing hydrated Pandoc checkout and
Haskell Cabal dependency closure already recorded in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
rootfile selection, metadata/DC/meta raw extraction, metadata refinements for
Dublin Core elements, metadata link byte resolution, accessibility metadata,
OPF package prefix parsing, OPF manifest/spine parsing, direct XHTML spine
handoff, nav/NCX TOC parsing, landmark navigation extraction, page-list/page-
break reporting, OPF guide/collections, alternate renditions, spine page-
progression/page-spread metadata, OCF encryption/obfuscated-font preflight,
SMIL media-overlay parsing, remote nav/NCX/SMIL reference retention, OPF
fallback chain resolution, package asset export reporting, remote OPF manifest
resource reporting, OPF binding-handler reporting, OPF manifest resource-
property reporting, OPF `media:duration` metadata, SMIL clip timing
normalization, OPF package prefix declarations, or package/resource/itemref
refinement handoff.

The new surface is only `itemref linear` value normalization, validity
metadata, and diagnostics for malformed spine reading-order declarations.

## Exclusions

Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
KaTeX, Typst, browser renderers, EPUBCheck, media players, handler runtimes,
remote fetches, roff, decryption helpers, font deobfuscators, online
sanitizers, or online services.

## Follow-Up

Keep XHTML-to-AST conversion, CSS cascade/resource policy, media extraction/
export policy, remote-resource security policy beyond unfetched diagnostics,
multiple-rendition selection UX, EPUBCheck-style validation, reading-system
layout behavior, at-least-one-linear-spine validation, and OPF refinement-cycle
validation as separate bounded EPUB slices.
