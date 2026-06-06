# pandoc-epub3-package-core-current-base-20260606T164432Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260606T164432Z`
- Accepted base: `beefb9c61faa06047be0268dd66c6d5afebefc6c`
- Upstream contract: bounded native EPUB3 package-review handoff for OPF
  media-type bindings used as XHTML fallback handlers. No Pandoc, Cabal,
  Haskell runner, Word, LibreOffice, zip/unzip, external EPUB validator,
  browser renderer, online service, live provider test, or live-service
  provider test was used.

## Behavior

OPF `<bindings>` records already reported custom media-type handlers, but a
custom spine item without an explicit manifest `fallback` could still drop out
of the reviewable XHTML handoff. `EpubReader` now uses the matching binding
handler as a bounded XHTML fallback only for the original custom spine item and
only when the handler manifest item exists, is not encrypted, can expose bytes,
and is `application/xhtml+xml`.

The resolved spine entry now carries:

- `contentId`, `contentPart`, and `contentMediaType` for the XHTML handler;
- `contentIsFallback = true`;
- a `fallbackChain` item with `source = binding-handler`,
  `bindingMediaType`, and `bindingHandlerId`;
- existing OPF binding metadata on the spine item and AST fallback block.

Invalid binding handlers remain diagnostics rather than renderer execution.

## Verification Evidence

Accepted-base focused baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1539 assertions, 0 failures
```

Red-first after adding the focused case:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL uses OPF bindings as XHTML fallback handlers for custom spine media
1 test files, 1548 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1567 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+28` net focused assertions for the
EPUB3 package support row.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`OpcPackagePath`, `EpubReader`, DOM/libxml parsing, in-memory EPUB fixtures,
and the existing WordPress EPUB3 handoff example.

Full upstream Pandoc runner parity remains unchanged: hydrate the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and Haskell Tasty runner dependencies before attempting
runner parity.

## Non-Overlap

This patch does not repeat accepted EPUB OCF mimetype/container/rootfile
validation, OCF sidecars, OPF metadata/DC/meta extraction, metadata link byte
resolution, metadata refinement subject diagnostics, package vocabulary prefix
resolution, OPF manifest/spine parsing, spine page-spread diagnostics,
explicit manifest fallback chains, binding inventory reporting, guide/
collections, alternate renditions, EPUB3 nav section and auxiliary-navigation
summaries, NCX head/navList/pageList/navMap provenance, navigation/spine
reconciliation, raw XHTML spine handoff, content resource scanning,
remote-resource reporting, cover-image provenance, encryption/obfuscated-font
preflight, SMIL media overlays, or EPUB CFI propagation. It owns only
binding-handler fallback resolution for custom spine media without an explicit
manifest fallback.

## Follow-Up

Keep active media-handler execution, XHTML-to-AST conversion beyond raw review
blocks, CSS cascade/media export policy, remote-resource fetch policy,
EPUBCheck-style validation, encrypted-resource decryption, and full nav-to-AST
rendering as separate bounded EPUB slices.
