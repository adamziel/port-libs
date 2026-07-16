# LightningCSS property values color/font/grid parity 2026-06-01T11:53:32Z

Status: ready for integration.

## Scope

This isolated property-value slice maps the upstream `src/lib.rs::test_custom_properties` minifier token-stream cases at pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

The focused upstream cases cover:

- empty custom-property values serializing as `--test: ` instead of disappearing after the colon;
- ordinary custom-property token trimming and escaped custom-property names;
- `var()` fallback comma compaction inside custom properties and color fallbacks;
- top-level transition list spacing between unresolved `var()` fallback layers;
- unresolved `calc(var(...) / 2)` division spacing;
- unresolved RGB channel variables preserving comma-space channel separation.

Pre-fix focused probes on this worktree showed the gaps:

- `.foo { --test: ; }` -> `.foo{--test:}`
- `.foo { transition: var(--foo, 20px), var(--bar, 40px); }` -> `.foo{transition:var(--foo,20px),var(--bar,40px)}`
- `.foo { height: calc(var(--spectrum-global-dimension-size-300) / 2); }` -> `.foo{height:calc(var(--spectrum-global-dimension-size-300)/2)}`
- `.foo { color: var(--color, rgb(var(--red), var(--green), 0)); }` -> `.foo{color:var(--color,rgb(var(--red),var(--green),0))}`

## Implementation

- `CssMinifier` now preserves a single empty token for empty custom-property values.
- Transition shorthand serialization inserts a space after top-level commas only when adjacent layers contain unresolved `var()` or `env()` references.
- `calc()` fallback compaction preserves spaces around unresolved division with `var()`/`env()` while continuing to compact unresolved multiplication, preserving accepted custom-at-rule output.
- Unresolved comma-syntax RGB color functions with variable color channels now serialize channel separators as `, ` while alpha-only variable fallbacks stay compact.
- Added `wordpress-custom-property-var-fallback-minifier.php` to cover block-theme empty design tokens, motion transition fallback lists, RGB channel variables, and spacing calc fallback CSS without Node/WASM.

## Evidence

- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-custom-property-var-fallback-minifier.php` -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> 1 test file, 1976 assertions, 0 failures
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> 1 test file, 389 assertions, 0 failures
- `php lanes/lightningcss/examples/wordpress-custom-property-var-fallback-minifier.php --self-test` -> OK
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 7685 assertions, 0 failures
- `git diff --check -- lanes/lightningcss` -> pass

## Status Delta

- `lane-status.json` `phpPass`: 7667 -> 7685
- `UPSTREAM_TEST_MANIFEST.json` conservative mapped coverage: 2374 -> 2392 / 3532
- `phpFail`: 0

## Non-overlap

This slice is limited to custom-property declaration-value token streams from upstream `test_custom_properties`. It does not repeat accepted advanced-color target fallbacks, keyframes custom-property fallbacks, font-stretch/property-value minification, grid property values, source maps, bundle/import graph, CSS Modules, media-query recovery, or target-prefix place-alignment work.

## Dependency Closure

No new support component is needed. The existing native PHP `CssMinifier` covers the upstream behavior, and the WordPress example exercises the user-visible path without Node/WASM.

## Next

Continue with non-overlapping property-value parity gaps, preferably color/font/grid cases that still have unmapped upstream helper rows or CSSOM-visible behavior.
