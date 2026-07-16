# pandoc-epub3-package-core-current-base-20260606T111929Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260606T111929Z`
- Accepted base: `c8b7f20f2fd086ce67b2aa94b2b1421611b99f67`
- Upstream contract: bounded native EPUB3 OPF metadata link handoff for
  `link refines="#..."` subject associations. No Pandoc, Cabal, Haskell
  runner, Word, LibreOffice, zip/unzip, ZipArchive, EPUBCheck, browser
  renderer, online service, live provider test, or live-service provider test
  was used.

## Behavior

OPF metadata `link` records can refine package metadata subjects such as DC
metadata ids, the package id, manifest item ids, and spine itemref ids. The
reader already resolved those linked resources, but review consumers could only
scan a flat metadata link list.

`EpubReader` now reports:

- per-link `subjectId` derived from `refines`;
- `metadata.linksByRefinedId`, `metadata.linkedResourcesById`, and
  `metadata.linkedResourceSummary`;
- `linkedResources` attached to matching DC metadata entries, title/creator/
  contributor detail rows, unique identifier entries, the package record,
  manifest items, spine itemrefs, and AST spine children;
- the same subject-indexed metadata map through `importReport['metadata']` and
  document metadata.

The WordPress EPUB3 package handoff smoke now asserts the existing
`creator-voicing` metadata link remains visible as a creator linked resource in
both metadata and AST review handoff data.

## Verification Evidence

Accepted pre-slice focused baseline from the previous EPUB3 package slice:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1421 assertions, 0 failures
```

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL attaches OPF metadata link refines records to package review subjects
1 test files, 1422 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1444 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+23` net focused assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`OpcPackagePath`, `EpubReader`, DOM/libxml parsing, in-memory package fixtures,
and the existing focused PHP test harness. Full upstream Pandoc runner parity
remains gated on hydrating the pinned Pandoc checkout plus Cabal project/package
files and Haskell Tasty runner dependencies.

## Non-Overlap

This patch does not repeat accepted EPUB OCF mimetype/container/rootfile
validation, OCF metadata/manifest/rights/signature sidecars, OPF metadata/
manifest/spine parsing, OPF package prefix vocabulary resolution, metadata link
byte resolution, raw XHTML spine handoff, EPUB nav XHTML parsing, NCX `navMap`,
NCX `pageList`, NCX `navList`, guide/collection links, alternate renditions,
fallback chains, bindings, media overlays, trigger/switch review flags,
remote-resource reconciliation, cover-image candidates, encryption/obfuscated
font preflight, or EPUB CFI propagation. It owns only associating already
resolved OPF metadata links with the package subjects named by `refines`.

## Follow-Up

Keep fuller XHTML-to-AST conversion, CSS cascade/media export policy,
remote-resource policy, multiple rendition selection, richer media-overlay
playback semantics, EPUBCheck validation, encrypted-resource decryption, and
full Haskell/Pandoc runner comparison as separate bounded slices.
