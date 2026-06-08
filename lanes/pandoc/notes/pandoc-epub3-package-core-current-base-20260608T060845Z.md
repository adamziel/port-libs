# pandoc-epub3-package-core-current-base-20260608T060845Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260608T060845Z`
- Base accepted HEAD: `c000a2c6e88c31cb43d41a8d298fca54b32ce3da`
- Upstream/package contract: EPUB 3.3 package rendering vocabulary allows
  local spine `itemref` overrides for fixed-layout properties. This slice
  exposes bounded native handoff metadata for `rendition:layout-*`,
  `rendition:orientation-*`, and `rendition:spread-*` itemref properties.
  It did not run Pandoc, Cabal/Haskell runners, zip/unzip, ZipArchive,
  EPUBCheck, browser renderers, online services, live provider tests, or
  live-service provider tests.

## Behavior

`EpubReader` already reported spine page-spread, flow, align-x, and package-level
rendition metadata. It now also reports per-spine fixed-layout overrides:

- `layout`, `layoutProperties`, and `spineItemProperties.layout`
- `orientation`, `orientationProperties`, and
  `spineItemProperties.orientation`
- `spread`, `spreadProperties`, and `spineItemProperties.spread`
- conflict diagnostics for multiple local layout, orientation, or spread
  values on one `itemref`

The WordPress handoff AST now carries the same values on each raw HTML spine
block so import reviewers can see that an EPUB chapter was locally marked as
pre-paginated, landscape, or no-spread without invoking external EPUB tools.

## Red-First Evidence

Before the implementation, an inline local EPUB package inspection showed that
`rendition:layout-pre-paginated`, `rendition:orientation-landscape`, and
`rendition:spread-none` were absent from `spineItemProperties`; only existing
page-spread, flow, align-x, and linear metadata were present.

## Verification

Baseline focused EPUB test before the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 2160 assertions, 0 failures
```

Focused EPUB test after the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 2202 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Additional checks run before handoff:

```text
php -l lanes/pandoc/src/EpubReader.php
php -l lanes/pandoc/tests/EpubReaderTest.php
php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
git diff --check -- lanes/pandoc
```

## Delta

- Focused PHP PASS cases: `+1`
- Focused assertions: `2160 -> 2202` (`+42`)
- `lane-status.json` `phpPass`: `1548 -> 1549`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1969 -> 1970`
- EPUB3 package core cases: `5 -> 6`
- EPUB3 package core assertions: `78 -> 120`

## Dependency Closure

No new support component is needed. This reuses native `ZipPackage`,
`EpubReader` OPF/spine parsing, `AstNode` handoff metadata, the focused
`EpubReaderTest.php` suite, and the lane-local WordPress EPUB3 package example.

## Non-Overlap

This does not repeat accepted EPUB OCF mimetype/container/rootfile validation,
OCF sidecars, OPF metadata/DC/meta extraction, metadata links, vendor metadata,
OPF manifest/spine order, page-progression direction, page-spread placement,
flow/align-x itemref metadata, nav/NCX targets, guide/collections, alternate
renditions, fallback chains, bindings, remote-resource reconciliation,
encryption/obfuscated-font preflight, SMIL media overlays, EPUB CFI fragments,
XHTML content resource scanning, CSS resource scanning, cover/asset reports,
or auxiliary navigation summaries.

## Follow-Up

Keep richer XHTML-to-AST conversion, CSS cascade/resource export policy,
EPUBCheck-style validation, encrypted resource decryption policy, active
media-overlay playback semantics, and full nav-to-AST rendering as separate
bounded EPUB slices.
