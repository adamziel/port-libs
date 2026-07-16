# pandoc-epub3-package-core-current-base-20260606T115449Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260606T115449Z`
- Accepted base: `9f43fcc1a47b08850d5cb210982f3f518404def8`
- Upstream contract: bounded native EPUB3 OPF metadata refinement subject
  diagnostics. No Pandoc, Cabal, Haskell runner, zip/unzip, ZipArchive,
  EPUBCheck, browser renderer, online service, live provider test, or
  live-service provider test was used.

## Behavior

OPF metadata `meta refines="#..."` records can target package metadata ids,
the package id, manifest ids, the spine id, itemref ids, collection ids, or
other metadata items. Those refinements were already parsed, but dangling
subjects were only visible as raw grouped metadata.

`EpubReader` now adds `metadata.refinementSubjectSummary`, also available in
`importReport['metadata']` and document metadata. The report records subject
order, refinement counts, known subject kinds, and dangling-subject diagnostics
without rejecting the package or dropping the original refinement values.

The WordPress EPUB3 package handoff smoke now includes a dangling OPF metadata
refinement and asserts that the review diagnostic is exposed through the import
report.

## Verification Evidence

Red-first after adding the focused case:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL reports dangling OPF metadata refinement subjects for package review
1 test files, 1445 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1470 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+25` net focused assertions for the
EPUB3 package support row.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`OpcPackagePath`, `EpubReader`, DOM/libxml parsing, in-memory package fixtures,
and the existing WordPress EPUB3 handoff example.

The full upstream Pandoc runner blocker remains unchanged: hydrate the pinned
Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and Haskell Tasty runner dependencies before attempting
runner parity.

## Non-Overlap

This patch does not repeat accepted EPUB OCF mimetype/container/rootfile
validation, OCF sidecars, OPF metadata/DC/meta extraction, metadata link byte
resolution, metadata link `refines` subject association, package vocabulary
prefix resolution, OPF manifest/spine parsing, spine/page-spread diagnostics,
fallback chains, bindings, guide/collections, alternate renditions, nav/NCX
parsing, NCX head/navList/pageList support, navigation/spine reconciliation,
raw XHTML spine handoff, content resource scanning, remote-resource reporting,
cover-image provenance, encryption/obfuscated-font preflight, SMIL media
overlays, or EPUB CFI propagation. It owns only OPF `meta` refinement subject
summary and dangling-subject diagnostics.

## Follow-Up

Keep XHTML-to-AST conversion, CSS cascade/media export policy, remote-resource
fetch policy, richer EPUBCheck-style validation, encrypted resource decryption,
and active media-overlay playback semantics as separate bounded EPUB slices.
