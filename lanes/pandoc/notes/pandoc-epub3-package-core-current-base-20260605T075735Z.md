# pandoc-epub3-package-core-current-base-20260605T075735Z

Base: `6bf66e4eb549e893548d86a7960f7cf19c5eeeba`

## Source Truth

- Existing lane package contract: implement native PHP EPUB package behavior
  under `lanes/pandoc/**` without shelling out to Pandoc, zip/unzip,
  browser renderers, EPUBCheck, online services, or remote fetches.
- EPUB navigation documents may expose `nav epub:type="page-list"` entries
  whose targets represent print/source page break landmarks. WordPress import
  review needs those targets associated with resolved spine XHTML blocks when
  possible, while remote or package-local non-spine targets must remain
  explicit diagnostics rather than being fetched or silently dropped.
- The pinned upstream Pandoc checkout is not hydrated in this worktree, so
  this is bounded native EPUB3 package support, not Haskell runner parity.

## Implementation

- Added a package-level `pageBreaks` report derived from the parsed EPUB3
  page-list navigation section.
- The report flattens nested page-list entries, preserves labels, hrefs,
  resolved targets, fragments, EPUB types, original nav diagnostics, and maps
  internal page targets back to the resolved spine content part.
- Remote page-list targets are retained as `external-page-list-reference`
  diagnostics and are not fetched. Package-local page-list targets outside the
  spine are retained as `page-list-target-outside-spine` diagnostics.
- Exposed `pageBreaks` at the top level, in `importReport`, on the document
  AST attrs, and as per-spine `pageBreaks` / `pageBreakCount` attrs on raw
  XHTML handoff blocks.
- Updated the WordPress EPUB3 package handoff example to self-test and print
  page-break metadata.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 535 assertions, 0 failures`.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 588 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: `20 test files, 8940 assertions, 0 failures`.
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

- `phpPass`: `759 -> 760`.
- mapped native checks: `1218 -> 1219`.
- EPUB3 package focused cases: `23 -> 24`.
- EPUB3 package focused assertions: `535 -> 588`.
- This slice adds `+1` EpubReader PASS case and `+53` focused EpubReader
  assertions over the accepted EPUB3 baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcPackagePath`, existing EPUB navigation parsing, and the existing raw XHTML
AST handoff. Full upstream Pandoc runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell Cabal dependency closure already recorded
in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
metadata/DC/meta raw extraction, metadata refinements, metadata link byte
resolution, accessibility metadata, OPF manifest/spine parsing, direct XHTML
spine handoff, nav/NCX TOC parsing, landmark navigation extraction, raw
page-list target parsing, OPF guide/collections, alternate renditions, spine
page-progression/page-spread metadata, OCF encryption/obfuscated-font
preflight, SMIL media-overlay parsing, remote nav/NCX/SMIL reference
retention, OPF fallback chain resolution, package asset export reporting,
remote OPF manifest resource reporting, OPF binding-handler reporting, OPF
manifest resource-property reporting, OPF `media:duration` metadata, or SMIL
clip timing normalization.

The new surface is only package-level page-break reporting from page-list nav
entries plus per-spine raw XHTML AST handoff metadata.

## Exclusions

Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
KaTeX, Typst, browser renderers, EPUBCheck, media players, handler runtimes,
remote fetches, roff, decryption helpers, font deobfuscators, online
sanitizers, or online services.

## Follow-Up

Keep richer XHTML-to-AST conversion, CSS cascade/resource policy, media
extraction/export policy, remote-resource security policy beyond unfetched
diagnostics, multiple-rendition selection UX, EPUBCheck-style validation, and
deeper reading-system layout behavior as separate bounded EPUB slices.
