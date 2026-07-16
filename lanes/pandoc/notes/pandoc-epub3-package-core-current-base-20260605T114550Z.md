# pandoc-epub3-package-core-current-base-20260605T114550Z

Base: `21ca4e606962df02c069c2fe826037f969abd856`

## Source Truth

- Existing lane package contract: implement native PHP EPUB package behavior
  under `lanes/pandoc/**` without shelling out to Pandoc, zip/unzip,
  browser renderers, EPUBCheck, JavaScript, online services, or remote fetches.
- EPUB packages commonly carry both EPUB3 nav XHTML and legacy NCX navigation.
  WordPress import review needs these navigation targets reconciled with the
  resolved spine content, so missing, remote, outside-spine, and uncovered
  linear reading-order cases are explicit instead of hidden behind raw nav
  parsing.
- The pinned upstream Pandoc Haskell runner is not hydrated in this worktree,
  so this is bounded native EPUB3 package support, not upstream runner parity.

## Implementation

- Added a package-level `navigation` report to `EpubReader` results,
  `importReport`, and document AST attributes.
- The report flattens EPUB3 nav TOC and NCX navigation targets, preserves
  labels, hrefs, fragments, NCX playOrder, source diagnostics, and maps
  internal targets back to resolved spine content parts.
- The report flags outside-spine navigation targets, missing package targets,
  remote unfetched targets, and linear spine items with no nav/NCX coverage.
- Updated the WordPress EPUB3 package handoff smoke to assert navigation
  target coverage without invoking external validators or fetchers.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 740 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed: `epub3 package handoff self-test ok`.

Red-first after adding the focused navigation-coverage test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed: `1 test files, 741 assertions, 1 failures`; `navigation` was not
  exposed.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 797 assertions, 0 failures`.
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

- `phpPass`: `876 -> 877`.
- mapped native checks: `1334 -> 1335`.
- EPUB3 package focused cases: `30 -> 31`.
- EPUB3 package focused assertions: `740 -> 797`.
- This slice adds `+1` EpubReader PASS case and `+57` focused assertions over
  the accepted EPUB3 baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `EpubReader`,
`ZipPackage`, `OpcPackagePath`, `AstNode`, and `WordPressBlockWriter`. Full
upstream Pandoc runner parity remains blocked by the missing hydrated Pandoc
checkout and Haskell Cabal dependency closure already recorded in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
rootfile selection, OPF metadata/DC/meta extraction, metadata refinements,
metadata link byte resolution, accessibility metadata, package prefix parsing,
manifest/spine parsing, direct raw XHTML spine handoff, raw nav/NCX parsing,
landmarks/page-list/page-break reporting, OPF guide/collections, alternate
renditions, spine page-progression/page-spread metadata, invalid `linear`
diagnostics, OCF encryption/obfuscated-font preflight, SMIL media-overlay
parsing, remote nav/NCX/SMIL reference retention, fallback-chain resolution,
asset export reporting, remote OPF manifest resource reporting, binding-handler
reporting, manifest resource-property reporting, OPF `media:duration` metadata,
SMIL clip timing normalization, package/resource/itemref refinement handoff,
all-non-linear spine diagnostics, raw XHTML content reference scanning, or OPF
`remote-resources` reconciliation.

The new surface is only package-level reconciliation of nav/NCX navigation
targets against resolved spine coverage for WordPress import review.

## Exclusions

Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
KaTeX, Typst, browser renderers, EPUBCheck, JavaScript, media players, handler
runtimes, remote fetches, decryption helpers, font deobfuscators, online
sanitizers, or online services.

## Follow-Up

Keep full XHTML-to-AST conversion, CSS cascade/resource policy, media
extraction/export policy, external link-vs-resource review UX, multiple
rendition selection UX, OPF refinement-cycle validation, EPUBCheck-style
validation, and deeper reading-system layout behavior as separate bounded EPUB
slices.
