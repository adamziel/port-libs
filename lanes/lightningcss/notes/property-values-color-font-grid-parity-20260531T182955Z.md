# LightningCSS Dynamic Grid Auto-Flow Parity - 2026-05-31

## Source Truth

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T182955Z`
- Base accepted HEAD: `1d7de15e4e85a2b8dbfd1c80922d2921091d0371`
- Upstream: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Focused upstream function: `src/lib.rs::test_grid`, the pretty-printer case where `grid-template-rows`, `grid-template-columns`, and `grid-template-areas` compose to `grid-template` while `grid-auto-flow: var(--auto-flow)` stays before `grid-auto-rows` and `grid-auto-columns`.

## Native Delta

- Tightened `CssMinifier::moveGridAutoFlowAfterAutoTracks()` so it only reorders `grid-auto-flow` behind auto tracks when the flow value is a canonical static `row`, `column`, or `dense` combination.
- Dynamic/custom-property-backed `grid-auto-flow` values are no longer treated as canonical auto-flow values, matching the upstream declaration ordering after template longhand composition.
- Extended the WordPress grid minifier smoke with a masonry-style query block fallback that uses a custom-property-backed grid auto-flow token.

## Red-First Evidence

- Probe before implementation:
  - Input: `.foo { grid-template-rows: auto 1fr auto; grid-template-columns: none; grid-template-areas: none; grid-auto-flow: var(--auto-flow); grid-auto-rows: auto; grid-auto-columns: 1fr; }`
  - Before: `.foo{grid-template:auto 1fr auto/none;grid-auto-rows:auto;grid-auto-columns:1fr;grid-auto-flow:var(--auto-flow)}`
  - Expected/upstream-aligned compact form: `.foo{grid-template:auto 1fr auto/none;grid-auto-flow:var(--auto-flow);grid-auto-rows:auto;grid-auto-columns:1fr}`

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
  - `1 test files, 1131 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 3061 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-grid-value-minifier.php --self-test`
  - exits `0`
- `php -l lanes/lightningcss/src/CssMinifier.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/CssMinifierTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-grid-value-minifier.php`
  - no syntax errors
- `php -r '<json validation one-liner>'`
  - `UPSTREAM_TEST_MANIFEST.json: OK`
  - `lane-status.json: OK`
- `git diff --check -- lanes/lightningcss`
  - passed

## Coverage Movement

- PHP lane assertions: `3060 -> 3061`
- Conservative mapped denominator: `1684 / 3532 -> 1685 / 3532`
- Newly mapped checks: 1 focused upstream `src/lib.rs::test_grid` dynamic `grid-auto-flow` ordering behavior.

## Non-Overlap

- Avoided accepted radial/conic gradient minifier, border-image prefix boundary, logical border CSSOM, CSS Modules grid custom-ident, escaped URL import graph, custom visitor supports-rule, source-map buffer, and prior grid auto-flow/placement composition clusters.
- The stale 2026-05-25 custom-media rework note is unrelated to this current-base property-values slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded `CssMinifier` declaration composer, grid shorthand serializer, auto-flow canonicalizer, and top-level declaration parsing helpers.

## Next

Continue with non-overlapping LightningCSS property-value parity, especially remaining color/font/grid minifier edges, CSSOM shorthand gaps, bundler/source-map graph behavior, and target-prefix browser boundaries.
