# pandoc-epub3-package-core-current-base-20260605T083055Z

Base: `c95d42ada7c1e699c25b1acfd56c5ce5fa8279d5`

## Source Truth

- Existing lane package contract: implement native PHP EPUB package behavior
  under `lanes/pandoc/**` without shelling out to Pandoc, zip/unzip,
  browser renderers, EPUBCheck, online services, or remote fetches.
- EPUB OPF package documents can declare metadata vocabulary prefixes in the
  package `prefix` attribute. WordPress import review needs those bindings
  preserved with diagnostics when declarations are malformed, while raw
  metadata property names remain unchanged.
- The pinned upstream Pandoc checkout is not hydrated in this worktree, so
  this is bounded native EPUB3 package support, not Haskell runner parity.

## Implementation

- Added native OPF package prefix parsing to `EpubReader`.
- The package summary now keeps the raw `prefix` string and exposes
  deterministic `prefixes`, ordered `prefixBindings`, and
  `prefixDiagnostics` for malformed or duplicate declarations.
- The import report reuses the existing package summary, so WordPress EPUB
  review packets can audit schema/marc-style metadata vocabulary bindings
  without fetching resources or invoking external validators.
- Updated the WordPress EPUB3 package handoff example to self-test and print
  the package prefix metadata.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 588 assertions, 0 failures`.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: `1 test files, 601 assertions, 0 failures`.
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

- `phpPass`: `775 -> 776`.
- mapped native checks: `1234 -> 1235`.
- EPUB3 package focused cases: `4 -> 5`.
- EPUB3 package focused assertions: `62 -> 75`.
- Focused EpubReader coverage: `24 PASS / 588 assertions -> 25 PASS / 601
  assertions`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcPackagePath`, and the existing EPUB OPF/package parsing path. Full upstream
Pandoc runner parity remains blocked by the missing hydrated Pandoc checkout
and Haskell Cabal dependency closure already recorded in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
rootfile selection, metadata/DC/meta raw extraction, metadata refinements,
metadata link byte resolution, accessibility metadata, OPF manifest/spine
parsing, direct XHTML spine handoff, nav/NCX TOC parsing, landmark navigation
extraction, page-list/page-break reporting, OPF guide/collections, alternate
renditions, spine page-progression/page-spread metadata, OCF encryption/
obfuscated-font preflight, SMIL media-overlay parsing, remote nav/NCX/SMIL
reference retention, OPF fallback chain resolution, package asset export
reporting, remote OPF manifest resource reporting, OPF binding-handler
reporting, OPF manifest resource-property reporting, OPF `media:duration`
metadata, or SMIL clip timing normalization.

The new surface is only OPF package prefix vocabulary parsing plus malformed
prefix diagnostics in the package/import-report metadata handoff.

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
multiple-rendition selection UX, EPUBCheck-style validation, and deeper
reading-system layout behavior as separate bounded EPUB slices.
