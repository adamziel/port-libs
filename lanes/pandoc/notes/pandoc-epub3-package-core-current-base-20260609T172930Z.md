# pandoc-epub3-package-core-current-base-20260609T172930Z

Slice: EPUB nav document diagnostics on accepted base `c81626c45e7d21d758317085b4726dfd30e905210`.

## Behavior

`EpubReader` now adds `nav.documentDiagnostics` and `nav.documentDiagnosticCount`
to EPUB import reports. The report summarizes bounded XHTML nav document
structure issues without changing existing nav target resolution:

- hidden primary nav sections,
- missing direct ordered-list containers,
- empty nav sections,
- nav sections missing `epub:type`, and
- duplicate primary `toc`, `landmarks`, or `page-list` sections.

Section summaries now retain `hasOrderedList` and flattened `itemCount` so
reviewers can distinguish an empty nav from one whose targets failed later.

## Evidence

- `php -l lanes/pandoc/src/EpubReader.php`
  - no syntax errors
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 3736 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - `epub3 package handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `39 test files, 56530 assertions, 0 failures`
- `git diff --check -- lanes/pandoc`
  - passed

## Accounting

- `lane-status.json` `phpPass`: `2799 -> 2800`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3032 -> 3033`.
- Added `mappedEpubNavDocumentDiagnosticsCases = 1`.
- Added `epubNavDocumentDiagnosticsAssertions = 22`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`,
`EpubReader`, DOM/libxml XML parsing, existing EPUB fixture construction, and
existing package-reference resolution.

No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip,
ZipArchive, EPUBCheck, browser renderer, JavaScript/media execution, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted OCF container/rootfile validation, OPF metadata,
manifest, spine, rendition, nav/NCX target resolution, nav item semantic source
provenance, page-list/page-break extraction, navigation/spine reconciliation,
auxiliary nav handoff, primary target policy, media-fragment classification,
NCX head/pageList/navList handling, XHTML content/resource scans, CSS export
policy, media overlays, bindings, encryption, asset fallback chains, cover
image provenance, or ZIP package integrity work.

The covered surface is only nav document structure diagnostics for already
parsed XHTML nav sections.

## Follow-Up

Keep deeper EPUBCheck parity, XHTML-to-AST normalization, CSS cascade/export UX,
full nav accessibility semantics, and Haskell/Pandoc runner comparison as
separate bounded slices.
