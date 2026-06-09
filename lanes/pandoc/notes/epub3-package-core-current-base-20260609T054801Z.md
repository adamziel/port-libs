# EPUB3 Package Identifier Preflight

Slice: `pandoc-epub3-package-core-current-base-20260609T054801Z`
Base accepted HEAD: `2c84ca27878846c6b3725d422a6af783d4bbe9c7`

## Behavior

Implemented bounded native PHP EPUB3 package preflight support for OPF
identifier review metadata.

- `EpubPackage` now exposes `metadata.uniqueIdentifier` with
  `unique-identifier` selection provenance, matched `dc:identifier` entries,
  duplicate match counts, and deterministic diagnostics.
- `metadata.identifierDetails` now records whether each identifier was selected
  by the package unique-id binding and whether its value duplicates another
  identifier.
- `metadata.identifierSummary` and `metadata.identifierDiagnostics` expose
  selected value/id/index, identifier schemes/types, duplicate value groups,
  and combined unique-id/duplicate-value diagnostics.
- `summary().wordpressImport.metadataDetails` carries the same reports so
  WordPress review queues can flag ambiguous EPUB source identifiers without
  rejecting otherwise inspectable packages.

## Evidence

Baseline focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 248 assertions, 0 failures`

Red-first verification after adding the focused test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 250 assertions, 1 failures`
- Failure: `uniqueIdentifier` and `identifierSummary` were absent from
  `EpubPackage::metadata()`.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 282 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- Result: `epub3 package preflight self-test ok`

Focused delta: +1 PHP PASS case and +34 focused assertions in
`EpubPackageTest.php`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`EpubPackage`, `ZipPackage`, DOM/libxml NONET XML parsing, existing OPF
metadata/refinement parsing, focused PHP tests, and the WordPress EPUB package
preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, browser renderer, external converter, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted EPUB OCF container/rootfile parsing, OPF
manifest/spine parsing, OPF metadata refinements, OPF metadata link records,
metadata-link vocabulary, package/collection remote link policy, bindings,
guide/collection handling, nav/NCX targets, rich `EpubReader` unique-identifier
diagnostics, rich reader vendor metadata, XHTML resource scans, content-feature
reconciliation, CSS export policy, OCF sidecars, encryption, media overlays,
asset fallback, or PDF/DOCX/ODT support rows. The new surface is restricted to
lightweight `EpubPackage` OPF identifier preflight diagnostics and WordPress
handoff metadata.

## Follow-Up

Next EPUB3 package work should choose a non-overlapping package gap such as
lightweight manifest duplicate-part diagnostics, navigation target coverage, or
media-overlay summary parity.
