# pandoc-epub3-package-core-current-base-20260605T101356Z

Base: `4ad60712d10804701fb3a159914426a65c11dc92`

## Source Truth

- Existing lane package contract: implement native PHP EPUB package behavior
  under `lanes/pandoc/**` without shelling out to Pandoc, zip/unzip,
  browser renderers, EPUBCheck, online services, or remote fetches.
- EPUB spine `itemref linear="no"` marks auxiliary content outside the primary
  reading order. A package whose spine itemrefs are all non-linear leaves the
  primary reading-order handoff empty, so the import report should expose that
  state for reviewer triage instead of silently treating it as ordinary order.
- The pinned upstream Pandoc Haskell runner is not hydrated in this worktree,
  so this is bounded native EPUB3 package support, not upstream runner parity.

## Implementation

- Added OPF spine summary fields to `EpubReader`:
  `itemCount`, `linearItemCount`, `nonLinearItemCount`, `hasLinearItems`, and
  `primaryReadingOrderEmpty`.
- Added a package-level `spine-has-no-linear-items` diagnostic when every
  spine itemref is non-linear.
- Propagated the summary through the existing `spineProperties` path into
  `importReport.spine.properties` and document attributes.
- Preserved raw XHTML handoff for non-linear spine entries so WordPress review
  packets can still inspect the source bytes.
- Updated the WordPress EPUB3 package smoke with a non-linear-only package
  variant while keeping the existing invalid-`linear` check.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 646 assertions, 0 failures`.

Red-first check after adding the focused test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed as expected because `spineProperties.itemCount` was absent; run
  summary: `1 test files, 647 assertions, 1 failures`.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 665 assertions, 0 failures`.
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

- `phpPass`: `826 -> 827`.
- mapped native checks: `1286 -> 1287`.
- EPUB3 package focused cases: `27 -> 28`.
- EPUB3 package focused assertions: `646 -> 665`.
- This slice adds `+1` EpubReader PASS case and `+19` focused assertions over
  the accepted EPUB3 baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `EpubReader`,
`ZipPackage`, `AstNode`, `WordPressBlockWriter`, and `OpcPackagePath`. Full
upstream Pandoc runner parity remains blocked by the missing hydrated Pandoc
checkout and Haskell Cabal dependency closure already recorded in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
rootfile selection, metadata/DC/meta raw extraction, metadata refinements for
Dublin Core elements, metadata link byte resolution, accessibility metadata,
OPF package prefix parsing, OPF manifest parsing, basic spine parsing, direct
XHTML spine handoff, nav/NCX TOC parsing, landmark navigation extraction,
page-list/page-break reporting, OPF guide/collections, alternate renditions,
spine page-progression/page-spread metadata, invalid `itemref linear` value
diagnostics, OCF encryption/obfuscated-font preflight, SMIL media-overlay
parsing, remote nav/NCX/SMIL reference retention, OPF fallback chain
resolution, package asset export reporting, remote OPF manifest resource
reporting, OPF binding-handler reporting, OPF manifest resource-property
reporting, OPF `media:duration` metadata, SMIL clip timing normalization, or
package/resource/itemref refinement handoff.

The new surface is only primary reading-order summary and diagnostics when
all OPF spine itemrefs are explicitly non-linear.

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
multiple-rendition selection UX, OPF refinement-cycle validation,
EPUBCheck-style validation, and deeper reading-system layout behavior as
separate bounded EPUB slices.
