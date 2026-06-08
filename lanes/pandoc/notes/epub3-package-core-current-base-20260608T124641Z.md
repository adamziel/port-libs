# EPUB3 Package XHTML Viewport Handoff

Slice: `pandoc-epub3-package-core-current-base-20260608T124641Z`
Base accepted HEAD: `bcafca302a458fe3d8a05b35a98c1763065f1b98`

## Behavior

This slice adds native EPUB3 package handoff support for XHTML content-document
viewport metadata used by fixed-layout review paths. `EpubReader` now inspects
each XHTML asset head, preserves the document title and meta counts, parses
`meta name="viewport"` content into ordered parameters, validates positive
integer `width` and `height`, and reports duplicate, empty, invalid, or unknown
viewport parameters without invoking Pandoc or browser/layout engines.

The per-asset `xhtmlResourceReport` now carries `metadata`,
`contentViewport`, `contentViewports`, and viewport diagnostics. Raw XHTML spine
blocks in the Pandoc-like AST carry the same metadata as `contentMetadata`,
`contentViewport`, and `contentViewports`, so WordPress review handoff can keep
fixed-layout dimensions visible while still rendering the XHTML as inert raw
HTML.

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 2390 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - `epub3 package handoff self-test ok`

The new focused case adds one mapped EPUB3 package PHP PASS case and 35 direct
assertions around valid and invalid XHTML viewport metadata handoff.

## Dependency Closure

No new native support component is needed. The implementation reuses
`EpubReader`, in-memory `ZipPackage` fixtures, existing DOM/libxml NONET XHTML
parsing, `AstNode` metadata handoff, and the existing WordPress EPUB package
example. No Pandoc executable, Cabal/Haskell runner, browser renderer, zip or
unzip command, external converter, online service, live provider test, or
live-service provider test was run.

## Non-Overlap And Follow-Up

This does not repeat OPF `rendition:viewport` metadata, OCF sidecars, media
overlays, CFI/nav/NCX parsing, XHTML resource scans, CSS/switch/trigger
handoffs, or table/math/citation/ODF/DOCX support rows. A later bounded slice
can consume `contentViewport` in a specific fixed-layout downgrade/importer
path; full CSS cascade, pagination, browser layout, and media playback remain
out of scope.
