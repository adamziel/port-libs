# pandoc-epub3-package-core-current-base-20260605T090414Z

Base: `b9d00b6837cac44ef0c4badaf79b30b8e03592c4`

## Source Truth

- Existing lane package contract: implement native PHP EPUB package behavior
  under `lanes/pandoc/**` without shelling out to Pandoc, zip/unzip,
  browser renderers, EPUBCheck, online services, or remote fetches.
- W3C EPUB 3.3 package document source truth:
  `https://www.w3.org/TR/epub-33/`. The shared `id` attribute is allowed on
  `item`, `itemref`, `manifest`, `package`, and `spine`; package metadata
  `meta`/`link` `refines` values establish an association with the referenced
  resource or package element, and resource expressions should reference the
  manifest entry ID when describing publication resources.
- The pinned upstream Pandoc Haskell runner is not hydrated in this worktree,
  so this is bounded native EPUB3 package support, not upstream runner parity.

## Implementation

- Reused the existing OPF metadata `refinementsById` map for package-level
  and resource-level EPUB package handoff.
- Added additive `id` and `refinements` fields to the OPF package summary and
  spine-property report.
- Added manifest-item `refinements`, spine itemref `id`, and spine itemref
  `refinements` output.
- Propagated itemref IDs and refinements onto raw XHTML AST blocks so
  WordPress review packets keep reading-order-specific metadata such as
  rendition viewport refinements.
- Updated the WordPress EPUB3 handoff smoke to self-test package/resource/
  spine/itemref refinements.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 601 assertions, 0 failures`.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 621 assertions, 0 failures`.
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

- `phpPass`: `790 -> 791`.
- mapped native checks: `1250 -> 1251`.
- EPUB3 package focused cases reconciled to `6`.
- EPUB3 package focused assertions reconciled to `95`.
- Focused EpubReader coverage: `25 PASS / 601 assertions -> 26 PASS / 621
  assertions`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcPackagePath`, and the existing EPUB OPF metadata parser. Full upstream
Pandoc runner parity remains blocked by the missing hydrated Pandoc checkout
and Haskell Cabal dependency closure already recorded in lane status.

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
property reporting, OPF `media:duration` metadata, or SMIL clip timing
normalization.

The new surface is only preserving existing OPF `meta refines="#..."` records
on package, manifest publication resource, spine, and spine `itemref` targets
for import-review metadata handoff.

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
layout behavior, and OPF refinement-cycle validation as separate bounded EPUB
slices.
