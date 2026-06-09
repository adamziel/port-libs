# pandoc-epub3-package-core-current-base-20260609T041436Z

Base accepted HEAD: `23275eb53503ab00485977d04d3ed962a3a95b0f`

## Behavior

Implemented bounded native PHP EPUB3 package preflight support for OPF
metadata `link` records in the compact `EpubPackage` API.

- `EpubPackage::packageLinks()` now exposes direct OPF metadata links with id,
  rel tokens, href, resolved package target, local part, external/missing
  status, media type, manifest id/media type when available, properties,
  title, hreflang, language/direction, refined subject id, byte length, CRC,
  and passive diagnostics.
- `summary()` now includes package link records, rel buckets, rel counts, and
  flattened diagnostics.
- `summary()['metadata']` and `summary()['wordpressImport']` now expose the
  same package-link handoff so WordPress import review can preserve linked
  metadata records, remote no-fetch records, and refined creator resources
  without fetching or executing external resources.

## Evidence

Baseline focused verification before the test was added:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 149 assertions, 0 failures`

Red-first focused verification after adding the package-link test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: failed with `Call to undefined method PortLibs\Pandoc\EpubPackage::packageLinks()`

Final focused verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-preflight.php`
- Result: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 180 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- Result: `epub3 package preflight self-test ok`

Focused delta: +1 PHP PASS line and +31 focused assertions in
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
compact OPF metadata refinements, compact OPF guide/nav-section preflight,
compact media-type bindings, compact OPF collections, rich `EpubReader`
metadata link reports, vendor metadata, accessibility metadata, manifest
property vocabulary, nav/NCX page-list/audio, XHTML/CSS resource scans, media
overlays, remote-resource reconciliation, OCF sidecars, encryption, asset
fallback, or embedded media/object/frame slices. It is restricted to compact
`EpubPackage` OPF metadata link preflight.

## Follow-Up

Good non-overlapping EPUB3 package follow-ups are lightweight media-overlay
summary parity in the compact API, a remote-resource policy summary in the
compact package API, or package-link vocabulary validation as a separate
bounded slice.
