# pandoc-epub3-nav-item-types-current-base-20260610T193536Z

Slice: EPUB3 navigation item type metadata handoff.

Base accepted HEAD after rebase: `a0ec4e4b0`

## Source Truth

- EPUB3 navigation documents can carry `epub:type` semantics on individual
  navigation list items and their direct `a` or `span` labels.
- Landmark and page-list entries use those item-level tokens for review
  classification, separate from section-level `toc`, `landmarks`, `page-list`,
  and auxiliary navigation section types.

## Behavior

- `EpubPackage` navigation entries now preserve list-item ids, label element
  names, label ids, `epub:type` tokens from the `li`, `epub:type` tokens from
  the direct label element, and a de-duplicated merged `types` list.
- The existing `landmarkTargets`, `pageListTargets`, and auxiliary navigation
  summaries inherit that item-level provenance without changing target
  resolution or external-resource policy.
- NCX fallback entries remain unchanged; this slice is scoped to XHTML EPUB3
  navigation documents.

## Evidence

Syntax:

```text
php -l lanes/pandoc/src/EpubPackage.php
No syntax errors detected in lanes/pandoc/src/EpubPackage.php

php -l lanes/pandoc/tests/EpubPackageTest.php
No syntax errors detected in lanes/pandoc/tests/EpubPackageTest.php
```

Focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php
1 test files, 852 assertions, 0 failures
```

Examples:

```text
php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test
epub3 package preflight self-test ok

php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Full Pandoc PHP gate:

```text
php tools/run-tests.php lanes/pandoc/tests
44 test files, 61106 assertions, 0 failures
```

Focused delta: `+1` PHP PASS case and `+25` focused assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP DOM parsing,
existing EPUB path resolution, `ZipPackage` fixtures, and the existing
`EpubPackage` summary surfaces.

No Pandoc, Cabal/Haskell runner, EPUBCheck, office suite, zip/unzip,
ZipArchive, browser renderer, JavaScript/media execution, external validator,
online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat OCF/container validation, OPF metadata/refinements,
manifest/spine parsing, rootfile diagnostics, nav target reconciliation,
nav structure diagnostics, NCX binding diagnostics, page-list parsing,
auxiliary section summaries, guide/collection links, fallback chains,
media overlays, encryption exposure policy, or XHTML resource scans. It owns
only item-level EPUB3 navigation type provenance on XHTML nav entries.
