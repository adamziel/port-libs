# pandoc-epub3-package-core-current-base-20260606T143921Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260606T143921Z`
- Accepted base: `c225160401688bd1c3ca993be227a17e71dcecc4`
- Upstream contract: bounded native EPUB3 navigation package handoff for
  auxiliary `nav` sections. No Pandoc, Cabal, Haskell runner, zip/unzip,
  ZipArchive, EPUBCheck, browser renderer, online service, live provider test,
  or live-service provider test was used.

## Behavior

EPUB3 navigation documents can include non-primary navigation sections such as
lists of illustrations (`loi`), tables (`lot`), audio, or video in addition to
`toc`, `landmarks`, and `page-list`. The reader already preserved raw nav
sections, but package review code did not expose a stable auxiliary-nav summary.

`EpubReader` now reports auxiliary navigation metadata under
`nav.auxiliaryNavigation`, with section and flattened-item summaries. The report
also exposes convenience `nav.auxiliarySections` and `nav.auxiliaryItems`
aliases and is passed through `importReport['nav']`. Primary `toc`,
`landmarks`, and `page-list` behavior remains unchanged.

The WordPress EPUB3 package handoff smoke now includes a list-of-illustrations
section and asserts local target resolution plus remote-reference diagnostics
without fetching external resources.

## Verification Evidence

Accepted-base focused baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1470 assertions, 0 failures
```

Red-first after adding the focused case:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL preserves EPUB3 auxiliary navigation sections for package review
1 test files, 1471 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1509 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+39` net focused assertions for the
EPUB3 package support row.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`OpcPackagePath`, `EpubReader`, DOM/libxml parsing, in-memory package fixtures,
and the existing WordPress EPUB3 handoff example.

Full upstream Pandoc runner parity remains unchanged: hydrate the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and Haskell Tasty runner dependencies before attempting
runner parity.

## Non-Overlap

This patch does not repeat accepted EPUB OCF mimetype/container/rootfile
validation, OCF sidecars, OPF metadata/DC/meta extraction, metadata link byte
resolution, metadata refinement subject diagnostics, package vocabulary prefix
resolution, OPF manifest/spine parsing, spine/page-spread diagnostics, fallback
chains, bindings, guide/collections, alternate renditions, toc/landmarks/
page-list parsing, NCX head/navList/pageList support, navigation/spine
reconciliation, raw XHTML spine handoff, content resource scanning,
remote-resource reporting, cover-image provenance, encryption/obfuscated-font
preflight, SMIL media overlays, or EPUB CFI propagation. It owns only auxiliary
EPUB3 nav-section package review summaries.

## Follow-Up

Keep XHTML-to-AST conversion, CSS cascade/media export policy, remote-resource
fetch policy, richer EPUBCheck-style validation, encrypted resource decryption,
active media-overlay playback semantics, and full nav-to-AST rendering beyond
package review summaries as separate bounded EPUB slices.
