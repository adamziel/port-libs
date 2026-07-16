# EPUB3 Package XHTML Link Resource Policy

Slice: `pandoc-epub3-package-core-current-base-20260608T205602Z`
Base accepted HEAD: `65a6df3ab5094e251e3a86a2aa20ace8a8f50ea8`

## Behavior

This slice adds bounded native EPUB3 package handoff for XHTML content-document
`<link>` resource policy. `EpubReader` now preserves structured link records
while scanning XHTML assets:

- `rel`, primary relation, policy (`stylesheet`, `preload`, `modulepreload`,
  `prefetch`, `icon`, `canonical`, `alternate`, `metadata`, or `untyped`);
- target/package resolution, fragment metadata, manifest id/media type,
  local byte length, CRC32, and SHA-256 when bytes can be exposed;
- declared `type`, `media`, `hreflang`, `title`, `as`, `sizes`, `color`,
  `crossorigin`, `integrity`, `referrerpolicy`, language, direction, and raw
  attributes;
- diagnostics for active stylesheet/preload-style resources, missing
  `href`, missing `rel`, missing preload `as`, remote references, missing
  package parts, encrypted targets, and non-CSS stylesheet type declarations.

The aggregate `xhtmlResourceReport` now includes link counts, active/passive
counts, review-required counts, flattened link items, and link diagnostics.
WordPress raw HTML handoff blocks receive `contentLinks` and
`contentLinkDiagnostics`, and active link resources remain inert.

## Evidence

- Rework-note check: no `port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 2711 assertions, 0 failures`
- Red-first after adding the XHTML link-policy test:
  - `1 test files, 2712 assertions, 1 failures`
  - Failure: missing `linkAssetCount` on `xhtmlResourceReport`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 2778 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - `epub3 package handoff self-test ok`

Focused delta: `+1` PHP PASS case and `+67` focused EPUB reader assertions.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `EpubReader`
DOM/libxml NONET XHTML scanning, `ZipPackage`, `OpcPackagePath`,
package-reference resolution, `AstNode` raw HTML metadata handoff, and the
existing WordPress EPUB package example.

No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip,
ZipArchive, EPUBCheck, browser renderer, JavaScript/media execution, external
converter, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap

This does not repeat accepted EPUB OCF container/rootfile parsing, OPF metadata
and metadata links, manifest/spine/fallback/bindings, nav/NCX/page-list
targets, primary navigation policy, guide/collections, alternate renditions,
XHTML language/direction/viewport/semantic/switch/trigger/script scans, CSS
resource reports, remote-resource reconciliation, OCF sidecars, encryption or
obfuscated font preflight, SMIL media overlays, EPUB CFI preservation, cover
image provenance, or ZIP package integrity work. The new surface is only
XHTML content-document `<link>` resource-policy handoff.

## Follow-Up

Keep richer CSS cascade/export review metadata, EPUB media-fragment validation,
XHTML-to-AST conversion, nav target rendering, remote fetch policy, active
resource execution, and full Pandoc/Haskell runner comparison as separate
bounded slices.
