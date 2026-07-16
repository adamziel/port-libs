# pandoc-epub-nav-item-label-diagnostics-current-base-20260609T225900Z

Slice: `pandoc-epub-nav-item-label-diagnostics-current-base-20260609T225900Z`

This slice extends bounded native EPUB navigation document diagnostics. Full
`EpubReader` import reports and compact `EpubPackage` validation reports now
emit `missing-primary-nav-item-label` diagnostics when primary `toc`,
`page-list`, or `landmarks` entries have no text label for review handoff.

The diagnostic preserves section and entry context, including section id/type,
item or entry index, href/target, and nested depth where available. Existing
navigation target resolution and target-policy diagnostics are unchanged.

Verification:

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - 1 file, 3770 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file, 659 assertions, 0 failures
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - `epub3 package handoff self-test ok`
- `git diff --check -- lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 57796 assertions, 0 failures

Status delta:

- `lane-status.json` `phpPass`: `2868 -> 2870`
- `lane-status.json` suite progress: `771 -> 773`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3073 -> 3075`
- `mappedEpubNavDocumentDiagnosticsCases`: `3 -> 5`
- `epubNavDocumentDiagnosticsAssertions`: `51 -> 93`

No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip,
EPUBCheck, browser renderer, external validator, online service, live provider
test, or live-service provider test was executed.

Non-overlap:

This does not repeat accepted OCF container/rootfile validation, OPF metadata,
manifest, spine, rendition, NCX parsing, nav/NCX target resolution,
page-list/page-break extraction, navigation/spine reconciliation, auxiliary
navigation handoff, primary target policy, media-fragment classification,
section-level nav structure diagnostics, primary section heading diagnostics,
XHTML content/resource scans, CSS export policy, media overlays, bindings,
encryption, asset fallback chains, cover image provenance, or ZIP package
integrity work.
