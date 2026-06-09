# pandoc-epub3-package-core-current-base-20260609T120426Z

Base accepted HEAD: `e01d0106d0e5222c23a21bcfd0a6b70a04cfac0d`

## Behavior

Implemented compact native PHP EPUB3 package validation diagnostics in
`EpubPackage`.

- Added `EpubPackage::validationReport()` for reviewable, non-fatal package
  diagnostics without changing existing hard rejection for malformed packages.
- The report classifies core OPF metadata presence, EPUB3 `dcterms:modified`,
  invalid or missing EPUB3 nav manifest items, multiple usable nav documents,
  duplicate manifest ids that point to the same package part, non-content
  document spine entries, and missing/external navigation targets.
- `summary()` and `summary()['wordpressImport']` now expose
  `validation`, `packageValidation`, and flattened
  `packageValidationDiagnostics` for WordPress preflight queues.
- The WordPress EPUB package preflight example now asserts the validation
  packet for a valid import package.

## Evidence

Baseline focused verification before adding the new test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 547 assertions, 0 failures`

Red-first focused verification after adding the test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: failed because `EpubPackage::validationReport()` was absent; run
  reached `1 test files, 547 assertions, 1 failures`.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 591 assertions, 0 failures`
- Focused delta: +1 PHP PASS case and +44 focused assertions.

Example smoke:

- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- Result: `epub3 package preflight self-test ok`

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. This slice reuses `EpubPackage`,
`ZipPackage`, `OpcPackagePath`, DOM/libxml NONET parsing, existing OPF
metadata/manifest/spine/nav handoff structures, focused PHP tests, and the
existing WordPress EPUB package preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, ZipArchive, EPUBCheck, browser renderer, external converter, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

No active `port-pandoc-*.needs-lane-rework.md` note existed before editing.

This does not repeat accepted EPUB OCF mimetype/container/rootfile validation,
OPF metadata refinements, identifier/source/bibliographic metadata, metadata
links, package/collection remote-resource policy, guide/collection handling,
bindings, media overlays, fallback chains, resource-property vocabulary,
rendition layout metadata, encryption exposure policy, OCF sidecars, rich
`EpubReader` XHTML/CSS/media/CFI scans, nav outline rendering, or ZIP package
integrity work. It is restricted to compact package validation diagnostics for
review handoff.

## Follow-Up

Good non-overlapping EPUB3 package follow-ups are XHTML-to-AST conversion
boundaries, asset export policy, or additional static package validation that
does not repeat compact metadata/nav/spine/target diagnostics.
