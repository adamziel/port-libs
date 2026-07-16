# pandoc-epub3-package-core-current-base-20260608T152124Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260608T152124Z`
- Accepted base: `71b9b5d33e2e3f2482d3351186b2396df20d9ff5`
- Upstream contract: bounded native EPUB3 package handoff for OPF guide
  reference semantic type metadata. No Pandoc, Cabal/Haskell runner,
  zip/unzip, ZipArchive, EPUBCheck, browser renderer, online service, live
  provider test, or live-service provider test was used.

## Behavior

EPUB packages can carry legacy OPF `guide` references alongside EPUB3
`landmarks`. The reader already resolved guide targets, but WordPress review
metadata could not compare guide references by semantic type.

`EpubReader` now tokenizes OPF guide reference `type` values, preserves the raw
type string, exposes per-item type tokens, reports `itemCount`,
`typedItemCount`, `missingTypeCount`, first-seen `types`, `typeCounts`, and
`itemsByType`, and records `missing-guide-reference-type` diagnostics for
untyped guide references. The guide report continues through
`importReport['guide']` and the document AST metadata.

The WordPress EPUB3 package handoff smoke now asserts guide type summary
metadata in the same native package review path as OCF, OPF, nav/NCX, SMIL,
asset, and XHTML review metadata.

## Verification Evidence

Accepted-base focused baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 2408 assertions, 0 failures
```

Red-first after adding the focused assertions:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL parses OPF guide references and collection review metadata
1 test files, 2369 assertions, 1 failures
```

After implementation and splitting the behavior into a named focused case:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
PASS summarizes OPF guide reference type vocabulary for package review
1 test files, 2443 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+35` net focused assertions for the
EPUB3 package support row.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`OpcPackagePath`, `EpubReader`, DOM/libxml parsing, in-memory EPUB package
fixtures, and the existing WordPress EPUB3 handoff example.

Full upstream Pandoc runner parity remains unchanged: hydrate the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and Haskell Tasty runner dependencies before attempting
runner parity.

## Non-Overlap

This patch does not repeat accepted EPUB OCF mimetype/container/rootfile
validation, OCF sidecars, OPF metadata/DC/meta extraction, metadata link byte
resolution, metadata refinement subject diagnostics, package vocabulary prefix
resolution, OPF manifest/spine parsing, spine/page-spread diagnostics,
fallback chains, bindings, collections, alternate renditions, nav/NCX
toc/landmarks/page-list parsing, auxiliary nav summaries, primary nav target
policy, navigation/spine reconciliation, raw XHTML spine handoff, content
resource scanning, remote-resource reporting, cover-image provenance,
encryption/obfuscated-font preflight, SMIL media overlays, EPUB CFI
propagation, or EPUB XHTML semantic annotations. It owns only OPF guide
reference type vocabulary review metadata.

## Follow-Up

Keep XHTML-to-AST conversion, CSS cascade/media export policy, remote-resource
fetch policy, richer EPUBCheck-style validation, encrypted resource decryption,
active media-overlay playback semantics, and full nav/guide-to-AST rendering
beyond package review summaries as separate bounded EPUB slices.
