# pandoc-epub3-package-core-current-base-20260605T125327Z

Base: `1125a317d034bc3f9bd44ea87bb86a514513b307`

## Source Truth

- Existing lane contract: implement native PHP EPUB package behavior under
  `lanes/pandoc/**` without shelling out to Pandoc, zip/unzip, browser
  renderers, EPUBCheck, JavaScript, remote fetches, online services, or
  external validators.
- EPUB OPF `unique-identifier` is package metadata that names the canonical
  `dc:identifier` entry by id. WordPress import review needs this binding,
  fallback provenance, and unresolved/duplicate/missing diagnostics visible
  instead of a single scalar identifier silently hiding package metadata issues.
- The pinned upstream Pandoc Haskell runner is not hydrated in this worktree,
  so this is bounded native EPUB3 package support, not upstream runner parity.

## Implementation

- Added an OPF `uniqueIdentifier` report to `EpubReader` metadata, package
  metadata, `importReport`, and document AST metadata attributes.
- The report preserves the declared OPF `unique-identifier` id, selected
  `dc:identifier` value, selection source, all identifier entries, matched
  entries, duplicate counts, and validity diagnostics.
- Added diagnostics for missing OPF `unique-identifier`, unresolved declared
  ids, duplicate canonical identifier ids, and packages with no
  `dc:identifier` entries while keeping the first identifier fallback visible
  for review where possible.
- Updated the WordPress EPUB3 handoff smoke so the self-test covers the
  canonical identifier path and an unresolved identifier diagnostic without
  fetching or externally validating the package.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 797 assertions, 0 failures`.

Red-first after adding the focused identifier test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed: `1 test files, 798 assertions, 1 failures` because
  `metadata.uniqueIdentifier` was absent.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 843 assertions, 0 failures`.
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

- `phpPass`: `905 -> 906`.
- mapped native checks: `1363 -> 1364`.
- EPUB3 package focused cases: `31 -> 32`.
- EPUB3 package focused assertions: `797 -> 843`.
- This slice adds `+1` EpubReader PASS case and `+46` focused assertions over
  the accepted EPUB3 baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `EpubReader`,
`ZipPackage`, `OpcPackagePath`, `AstNode`, and `WordPressBlockWriter`. Full
upstream Pandoc runner parity remains blocked by the missing hydrated Pandoc
checkout and Haskell Cabal dependency closure already recorded in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
rootfile selection, OPF prefix parsing, metadata link byte resolution,
accessibility metadata, package/resource/itemref refinements, manifest/spine
parsing, direct raw XHTML spine handoff, nav/NCX parsing, navigation coverage,
landmarks/page-list/page-break reporting, OPF guide/collections, alternate
renditions, spine page-progression/page-spread metadata, invalid `linear`
diagnostics, OCF encryption/obfuscated-font preflight, SMIL media-overlay
parsing, remote nav/NCX/SMIL reference retention, fallback-chain resolution,
asset export reporting, remote OPF manifest resource reporting, binding-handler
reporting, manifest resource-property reporting, OPF `media:duration`
metadata, SMIL clip timing normalization, all-non-linear spine diagnostics,
raw XHTML content reference scanning, or OPF `remote-resources`
reconciliation.

The new surface is only OPF package unique-identifier binding and diagnostic
handoff for WordPress import review.

## Exclusions

Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
KaTeX, Typst, browser renderers, EPUBCheck, JavaScript, media players, handler
runtimes, remote fetches, decryption helpers, font deobfuscators, online
sanitizers, external validators, or online services.

## Follow-Up

Keep XHTML-to-AST conversion, CSS cascade/resource policy, media
extraction/export policy, external link-vs-resource review UX, multiple
rendition selection UX, OPF refinement-cycle validation, EPUBCheck-style
validation, and deeper reading-system layout behavior as separate bounded EPUB
slices.
