# EPUB3 Spine Page Progression Preflight

Slice: `pandoc-epub3-spine-page-progression-current-base-20260610T144528Z`
Base accepted HEAD: `d3547bbec`

## Behavior

Implemented bounded native PHP EPUB3 package ingestion support for the OPF
`spine` `page-progression-direction` attribute.

- `EpubPackage` now captures the package-level spine page progression direction
  and exposes it through `spinePageProgressionDirection()`.
- `summary()` carries the value at top level and in
  `wordpressImport.spinePageProgressionDirection` so review queues can preserve
  right-to-left or default progression hints without reading raw OPF.
- Package validation now reports `pageProgressionDirection`,
  `pageProgressionDirectionSpecified`, and `pageProgressionDirectionValid`, and
  emits `invalid-spine-page-progression-direction` when the attribute is not
  `default`, `ltr`, or `rtl`.

## Evidence

Syntax checks:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- Result: both passed with no syntax errors.

Focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 765 assertions, 0 failures`

Full lane verification:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `44 test files, 60353 assertions, 0 failures`

Focused delta: +1 PHP PASS case and +13 focused assertions in
`EpubPackageTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`EpubPackage`, `ZipPackage`, DOM/libxml NONET XML parsing, existing OPF package
fixture construction, focused PHP tests, and the full Pandoc lane harness.

No Pandoc, EPUBCheck, Cabal solver/build/test command, Haskell runner,
zip/unzip, browser renderer, external validator, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted EPUB OCF container/rootfile parsing, OPF manifest
parsing, spine itemref ordering, NCX toc binding diagnostics, navigation
diagnostics, language metadata, XHTML output controls, media overlays,
encryption, bindings, collections, guide references, resource properties,
manifest fallbacks, remote-resource policy, or shared ZIP/OPC preflight work.
The new surface is restricted to the OPF spine page progression direction and
its compact package validation handoff.
