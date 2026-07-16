# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01

## Slice

- Lane: `lightningcss`
- Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T051644Z`
- Base accepted HEAD: `018b45ef9c6dbc5953c310812969453e7fb8e5dd`
- Source truth: upstream `parcel-bundler/lightningcss` manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

Used the pinned native LightningCSS NAPI artifact from `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` with `bundleAsync({ minify: true })` to verify a repeated import graph edge:

- Entry imports `card.css`, then a resolver-marked external reset, then `gallery.css`, then `card.css` again.
- `card.css` imports `tokens.css`.
- Upstream output preserves the external import before bundled rules and emits `gallery.css` before the repeated `card.css` at its last browser-evaluated import position, with `tokens.css` immediately before `card.css`.

The relevant upstream source behavior is also visible in `src/bundler.rs::order()` and `inline()`: normal `@import` dependencies preserve the last instance, while external imports remain in the output stream unless they appear after an already emitted bundled import.

## Native Delta

- Added focused `CssBundlerTest.php` coverage for repeated imported files around an external sibling import and a bundled sibling import.
- The assertion intentionally does not depend on resolver callback order because upstream collects dependencies in parallel; it pins the observable bundled CSS order and the resolved graph members instead.
- Extended `wordpress-bundle-import-graph.php` with a block-theme smoke where a repeated card stylesheet moves after an external editor reset and a gallery stylesheet, carrying its token dependency with it.
- No production source edit was required; the current native PHP graph ordering already matches this upstream edge.

## Verification

- `php -l lanes/lightningcss/tests/CssBundlerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 558 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` -> exits 0 and prints `repeated-import-position: preserved`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6137 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Status Delta

- Full LightningCSS lane evidence moves from `6135` to `6137` assertions.
- Conservative mapped upstream coverage remains `2353 / 3532`; this deepens the already represented `src/bundler.rs::test_bundle` / `node/test/bundle.test.mjs` import graph cluster.

## Dependency Closure

No new support component is needed. This reuses the native PHP `CssBundler` resolver boundary, recursive import graph ordering, external import serializer, `CssMinifier`, and existing WordPress bundle smoke.

## Non-Overlap

This does not repeat accepted custom-media import-tail rework, CSS Modules conditional composes validation, empty import source handling, import source token validation, escaped import modifiers, source-map import graph behavior, media/layer parser work, or target-prefix/property-value/CSSOM clusters. The old current-rebase note for `CustomMediaTransformer.php` is already represented by accepted custom-media scanner notes and is not touched here.

## Follow-Up

Remaining bundle/import graph parity areas include source-map remapping through CSS Modules dependency imports, additional resolver diagnostic ordering edges that do not depend on upstream parallel callback order, and custom at-rule parser integration during bundling.
