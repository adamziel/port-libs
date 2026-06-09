# pandoc-epub3-package-core-current-base-20260609T040318Z

Base accepted HEAD: `72a53fe4cb43f993ddc490102ccddab53f4ddfb1`

## Behavior

Implemented bounded native PHP EPUB3 package preflight support for OPF
`collection` records in the compact `EpubPackage` API.

- `EpubPackage::collections()` now exposes top-level and nested OPF
  collections with id, role tokens, language/direction, compact metadata,
  links, relation buckets, and diagnostics.
- Collection links preserve local package targets, remote no-fetch targets,
  missing target diagnostics, manifest ids/media types when available, byte
  lengths, and CRCs for existing local records.
- `summary()['wordpressImport']` now includes recursive collection records,
  collection titles, collection link targets, and collection diagnostics so
  WordPress import review can triage EPUB series/set/sample packets without
  executing handlers or fetching remote records.

## Evidence

Baseline focused verification before the test was added:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 115 assertions, 0 failures`

Red-first focused verification after adding the collection test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: failed with `Call to undefined method PortLibs\Pandoc\EpubPackage::collections()`

Final focused verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-preflight.php`
- Result: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 149 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- Result: `epub3 package preflight self-test ok`

Focused delta: +1 PHP PASS line and +34 focused assertions in
`EpubPackageTest.php`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`EpubPackage`, `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, DOM/libxml
NONET XML parsing, focused `EpubPackageTest` coverage, and the existing
WordPress EPUB3 package preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, EPUBCheck, browser renderer, online service,
live provider test, or live-service provider test was executed.

## Non-Overlap

No active `port-pandoc-*.needs-lane-rework.md` rework note existed before
editing.

This does not repeat accepted OCF mimetype/container/rootfile validation,
compact OPF metadata refinement preflight, compact OPF guide/nav-section
preflight, compact media-type bindings, rich `EpubReader` collection role/link
reports, vendor metadata, accessibility metadata, OPF link vocabulary, manifest
property vocabulary, nav/NCX page-list/audio, XHTML/CSS resource scans, media
overlays, remote-resource reconciliation, OCF sidecars, encryption, asset
fallback, or embedded media/object/frame slices. It is restricted to compact
`EpubPackage` OPF collection preflight.

## Follow-Up

Good non-overlapping EPUB3 package follow-ups are package-level OPF link
records in compact preflight, lightweight media-overlay summary parity, or a
remote-resource policy summary in the compact package API.
