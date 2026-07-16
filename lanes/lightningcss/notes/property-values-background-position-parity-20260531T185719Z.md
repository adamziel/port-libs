# LightningCSS Background Position Value Parity - 2026-05-31

## Source Truth

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T185719Z`
- Base accepted HEAD: `9992592125363999691e76351c839408179ceff4`
- Upstream: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Focused upstream function: `src/lib.rs::test_background`, the minifier cluster for `background-position` keyword/offset normalization and related `background` shorthand default-position compaction.

## Native Delta

- Added `CssMinifier` background value normalization for `background-position` lists.
- Added bounded `background` shorthand handling for upstream `none`, `transparent`, safe URL, default-position, and repeat ordering cases.
- Preserved accepted behavior for quoted absolute background URLs while still matching upstream relative safe URL unquoting and data URL quote preservation.
- Added `wordpress-background-position-minifier.php` for block cover/background positioning without Node/WASM.

## Red-First Evidence

Before implementation, current-base probes returned unnormalized values:

- `.foo { background-position: center center; }` -> `.foo{background-position:center center}` instead of `.foo{background-position:50%}`
- `.foo { background: transparent }` -> `.foo{background:#0000}` instead of `.foo{background:0 0}`
- `.foo { background: url("img-sprite.png") no-repeat bottom right }` -> `.foo{background:url("img-sprite.png") no-repeat bottom right}` instead of `.foo{background:url(img-sprite.png) 100% 100% no-repeat}`

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
  - `1 test files, 1166 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 3228 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-background-position-minifier.php --self-test`
  - exits `0`
- PHP lint and `git diff --check -- lanes/lightningcss` were run after this note was written; see final handoff for exact command results.

## Coverage Movement

- PHP lane assertions: `3206 -> 3228`
- Conservative mapped denominator: `1721 / 3532 -> 1743 / 3532`
- Newly mapped checks: 22 focused upstream `src/lib.rs::test_background` minifier cases.

## Non-Overlap

This slice avoids accepted color-mix, font/font-face/font-palette/font-feature, grid shorthand/auto-flow/placement, background-clip target-prefixing, background CSSOM read/write/remove, source-map, CSS Modules, custom at-rule, media-query, and bundle/import graph clusters. The stale 2026-05-25 custom-media rework note is unrelated.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `CssMinifier` declaration-value pipeline, top-level token splitters, and URL token normalization helpers.

## Root Harness

Not run - isolated micro-slice.
