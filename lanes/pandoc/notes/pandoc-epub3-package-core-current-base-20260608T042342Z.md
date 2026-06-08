# pandoc-epub3-package-core-current-base-20260608T042342Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260608T042342Z`
- Base accepted HEAD: `e8c43317726abb932805c171a399c58fb2c01c99`
- Behavior: bounded native EPUB3 XHTML `epub:type` semantic metadata handoff.

## Behavior

`EpubReader` now records content-document EPUB semantic annotations from
XHTML `epub:type` attributes. Each semantic item preserves source element
name, id, classes, type tokens, primary type, language, direction, raw
attributes, optional `href` target metadata, manifest id/media type, and
same-document fragment resolution.

The semantic report is exposed through:

- each XHTML asset in `xhtmlAssets`;
- `xhtmlResourceReport.itemsByPart[*].semantics`;
- grouped `semanticItemsByType` and aggregate semantic counters;
- raw-HTML AST block attributes `contentSemantics`,
  `contentSemanticTypes`, and `contentSemanticDiagnostics`.

Missing same-document semantic fragments remain diagnostics instead of
silently passing just because the XHTML package part exists.

## Evidence

Red-first focused run after adding the test:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL reports EPUB XHTML semantic type annotations for package review
1 test files, 2077 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 2115 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+39` focused assertions. The mapped
native EPUB3 package support cases moved from `5` to `6`, and the lane mapped
count moved from `1959` to `1960`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ZipPackage`, DOM/XML parsing, `EpubReader` package-reference resolution,
existing XHTML content scanning, `AstNode` raw-HTML handoff, focused lane
tests, and the WordPress EPUB3 package handoff example.

Pandoc, Cabal/Haskell runners, EPUBCheck, Word, LibreOffice, `zip`/`unzip`,
ZipArchive, browser renderers, external validators, online services, live
provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat accepted EPUB OCF container/rootfile parsing, OPF
metadata/vendor/identifier/refinement handling, manifest media type policy,
fallback and fallback-style chains, bindings, nav/NCX/page-list parsing,
navigation coverage, XHTML resource/srcset/CSS scans, switch/trigger review,
remote-resource reconciliation, OCF sidecars, encryption/font reports, SMIL
media overlays, CFI propagation, fixed-layout rendition metadata, or cover and
asset reports. It owns only content-document `epub:type` semantic annotation
handoff and same-document semantic `href` fragment diagnostics.

## Follow-Up

Keep parser-level XHTML body-to-AST conversion, nav target media-fragment
policy, CSS cascade/export review metadata, EPUBCheck-style validation,
encrypted resource decryption, remote fetch policy, and media playback as
separate bounded EPUB slices.
