# CSS Modules nth-child Formula Local/Global Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T052151Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant upstream areas: `src/selector.rs` selector serialization, `src/lib.rs::test_css_modules` `:nth-child(... of .foo)` CSS Modules cases, and `src/css_modules.rs::CssModule::handle_composes`.
- Local pinned NAPI oracle from `.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` confirmed:
  - `.card:nth-child(2n + 1 of :global(.legacy) + .child)` serializes as `.EgL3uq_card:nth-child(odd of .legacy+.EgL3uq_child)`.
  - `.card:nth-last-child(even of :local(.slot), :global(.legacy))` serializes the formula as `2n`.
  - `.card:nth-child(0n + 3 of .item)` serializes the formula as `3`.

## Implementation

- `CssModulesTransformer` now minifies `nth-child()` and `nth-last-child()` An+B formulas in the CSS Modules selector path.
- Formula minification runs after finding the `of` selector-list boundary, preserving existing local/global selector rewriting and forgiving selector-list behavior.
- The same helper handles no-`of` `nth-child()` formulas, so `2n + 1` becomes `odd` in scoped CSS Modules selectors.
- Existing `composes` metadata remains attached to simple local class selectors in the same module.

## Verification

- Red-first PHP spot check before the patch emitted `.EgL3uq_card:nth-child(2n+1 of .legacy+.EgL3uq_child)` where upstream emits `odd`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-nth-child-formula.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> 1 test file, 413 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-css-modules-nth-child-formula.php --self-test` -> OK.
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 6183 assertions, 0 failures.

## Status Delta

- `lane-status.json` `phpPass`: `6179 -> 6183`.
- Conservative mapped coverage remains `2356 / 3532`; this deepens the existing CSS Modules local/global/composes selector cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules transformer, selector scanner, An+B formula serialization helper, export metadata model, minifier pipeline, and WordPress example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not touch the stale May 25 `CustomMediaTransformer.php` rework note, CSS Modules selector-comment guards, invalid escaped selector-newline guards, escaped local/global pseudos, escaped/commented `composes` property parsing, forgiving invalid selector-list dropping, single `:is()` unwrapping, host-context behavior, view-transition guards, bundled CSS Modules option propagation, bundle/import graph, source-map, media-query, property-value, CSSOM, target-prefixing, or custom at-rule slices.

## Next Task

Continue with non-overlapping CSS Modules selector/composes edges, or pivot to current-priority LightningCSS source-map, bundle/import graph, CSSOM, media-query, target-prefix, property/value, or custom at-rule parity.
