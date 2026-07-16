# pandoc-epub3-package-core-current-base-20260605T111432Z

Base: `376511a589dcfa618b0d7e98aa876de9116785a2`

## Source Truth

- Existing lane package contract: implement native PHP EPUB package behavior
  under `lanes/pandoc/**` without shelling out to Pandoc, zip/unzip,
  browser renderers, EPUBCheck, online services, JavaScript, or remote fetches.
- EPUB OPF manifest `properties="remote-resources"` marks publication
  resources that may depend on remote resources. WordPress import review needs
  a package-level handoff that reconciles declared remote-resource resources
  with actually observed XHTML resource-loading references, while keeping
  ordinary external navigation links and remote URLs unfetched.
- The pinned upstream Pandoc Haskell runner is not hydrated in this worktree,
  so this is bounded native EPUB3 package support, not upstream runner parity.

## Implementation

- Added a package-level `remoteResources` report to `EpubReader` results,
  `importReport`, and document AST attributes.
- The report preserves OPF-declared `remote-resources` manifest items, observed
  resource-loading remote references from XHTML content scans, undeclared XHTML
  remote-resource diagnostics, and declared-but-unobserved review diagnostics.
- External `a href` navigation links remain covered by the existing nav/XHTML
  reference reports and are intentionally not counted as remote resource loads.
- Updated the WordPress EPUB3 package handoff smoke to assert the report for a
  declared remote image without fetching the remote URL.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 705 assertions, 0 failures`.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 740 assertions, 0 failures`.
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

- `phpPass`: `856 -> 857`.
- mapped native checks: `1314 -> 1315`.
- EPUB3 package focused cases: `29 -> 30`.
- EPUB3 package focused assertions: `705 -> 740`.
- This slice adds `+1` EpubReader PASS case and `+35` focused assertions over
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
manifest/spine parsing, direct raw XHTML spine handoff, nav/NCX parsing,
landmarks/page-list/page-break reporting, OPF guide/collections, alternate
renditions, spine page-progression/page-spread metadata, invalid `linear`
diagnostics, OCF encryption/obfuscated-font preflight, SMIL media-overlay
parsing, remote nav/NCX/SMIL reference retention, fallback-chain resolution,
asset export reporting, remote OPF manifest resource reporting, binding-handler
reporting, manifest resource-property reporting, OPF `media:duration` metadata,
SMIL clip timing normalization, package/resource/itemref refinement handoff,
all-non-linear spine diagnostics, or raw XHTML content reference scanning.

The new surface is only package-level reconciliation between OPF
`remote-resources` declarations and observed XHTML resource-loading remote
references for WordPress import review.

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
