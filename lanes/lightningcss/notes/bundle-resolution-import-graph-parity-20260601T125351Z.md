# Bundle Resolution Import Graph Parity 2026-06-01T12:53:51Z

## Scope

Ported a bounded LightningCSS bundle/import graph parity check for source-map
source collection when an import is resolved and read but remains preserved in
the final bundle because a statement-form unknown at-rule appears before it.

This deepens the existing unknown statement import coverage without changing
the preservation behavior: the output keeps `@import "pkg:card.css" ...`, while
the source-map table still records both the entry stylesheet and the resolved
dependency.

## Upstream Source Truth

Pinned upstream commit:
`parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.

Relevant source:

- `src/bundler.rs::bundle()` loads all files, orders the graph, inlines, then
  collects `stylesheet.sources` from every loaded stylesheet into the final
  bundle stylesheet.
- `src/bundler.rs::load_file()` adds source-map source content for each loaded
  stylesheet before dependency collection and before final inline decisions.

Observed PHP parity probe before codifying the test:

- entry: `@wp-bundle meta; @import "pkg:card\2e css" layer(theme.blocks) supports(display: grid) screen; .entry { color: red }`
- resolver decoded `pkg:card.css` from `/entry.css` to `/blocks/card.css`
- reader loaded `/entry.css` and `/blocks/card.css`
- output preserved `@import "pkg:card.css" ...`
- source map sources were `entry.css` and `blocks/card.css`

## Changes

- Added `css bundler source map retains resolved sources for preserved unknown
  statement imports` to `lanes/lightningcss/tests/CssBundlerTest.php`.
- Extended `lanes/lightningcss/examples/wordpress-bundle-import-graph.php` with
  the `unknown-statement-source-map: collected` smoke marker.
- Updated `lanes/lightningcss/lane-status.json` to current-base `704eae59...`
  evidence and `phpPass` `7849`.

## Verification

- Pre-edit focused baseline:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  -> `1 test files, 761 assertions, 0 failures`.
- Focused after:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  -> `1 test files, 767 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  -> passed, including `unknown-statement-source-map: collected`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  -> `13 test files, 7849 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
bundle resolver, reader source provider, and `SourceMap` table implementation.

## Non-Overlap

This does not repeat recent CSS Modules dependency source-map remapping,
malformed inline source-map suppression, malformed `@layer` diagnostics, or
the existing preserved unknown statement import serialization coverage. It adds
source-map source table parity for that preserved import graph edge.

## Next

Adjacent bundle work should target generated source-map offsets through final
bundle printing or resolver diagnostic ordering edges.
