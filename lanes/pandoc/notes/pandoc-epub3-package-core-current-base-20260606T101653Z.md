# pandoc-epub3-package-core-current-base-20260606T101653Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260606T101653Z`
- Accepted base: `e1661ddde6bf69323245293250d294a721f7503c`
- Upstream contract: bounded native EPUB3 package/NCX handoff for legacy NCX
  `navList` / `navTarget` review references. No Pandoc, Cabal, Haskell runner,
  zip/unzip, ZipArchive, EPUBCheck, browser renderer, online service, or media
  playback execution was used.

## Behavior

Legacy NCX files may carry custom `navList` sections outside the primary
`navMap` TOC and `pageList`. WordPress import review should preserve those
references, but they should not inflate main TOC/spine navigation coverage.

`EpubReader` now reports:

- `ncx.navListCount`, `ncx.navLists`, and `ncx.navListDiagnostics`;
- per-list id/class/language/direction/title/item count and aggregate
  diagnostics;
- per-`navTarget` id/class/playOrder/title, package-local target resolution,
  fragments/EPUB CFI metadata, byte length/CRC when local bytes exist, and
  diagnostics for remote or missing targets;
- the same NCX navList report through `importReport['ncx']`.

The WordPress EPUB3 package handoff smoke now includes a legacy NCX reviewer
reference list with one package-local target and one remote target, proving the
local target stays addressable while the remote target remains unfetched.

## Verification Evidence

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1382 assertions, 0 failures
```

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL reports NCX navList targets for legacy package review
1 test files, 1383 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1421 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+39` net focused assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`OpcPackagePath`, `EpubReader`, DOM/libxml parsing, and the existing focused
PHP test harness. Full upstream Pandoc runner parity remains gated on hydrating
the pinned Pandoc checkout plus Cabal project/package files and Haskell Tasty
runner dependencies.

## Non-Overlap

This patch does not repeat accepted EPUB OCF mimetype/container/rootfile
validation, OCF metadata/manifest/rights/signature sidecars, OPF metadata/
manifest/spine parsing, OPF package prefix vocabulary resolution, raw XHTML
spine handoff, EPUB nav XHTML parsing, NCX `navMap` TOC parsing, NCX
`pageList` page-break parsing, NCX head/docTitle/docAuthor metadata, guide/
collection links, alternate renditions, fallback chains, bindings, media
overlays, trigger/switch review flags, remote-resource reconciliation,
encryption/obfuscated-font preflight, or EPUB CFI propagation. It owns only
legacy NCX `navList` / `navTarget` review-reference handoff.

## Follow-Up

Keep fuller NCX validation, optional navList role/code-list labeling,
XHTML-to-AST conversion, CSS cascade/media export policy, active media
playback, EPUBCheck validation, remote-resource policy, and full Haskell/Pandoc
runner comparison as separate bounded slices.
