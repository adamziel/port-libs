# CSS Modules Single Is Local/Global Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T050419Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source evidence: `src/selector.rs` serializes `Component::Is` by unwrapping `:is()` when `should_unwrap_is()` sees exactly one selector, no type selector, and no combinator.
- Local pinned NAPI oracle from `.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` confirmed:
  - `.test:is(:local(.foo))` serializes as `.EgL3uq_test.EgL3uq_foo`.
  - `.test:is(:global(.foo))` serializes as `.EgL3uq_test.foo`.
  - `.test:is([data-x])` serializes as `.EgL3uq_test[data-x]`.
  - `.test:is(article)` and `.test:is(.wrapper .child)` keep the `:is(...)` wrapper.

## Implementation

- `CssModulesTransformer` now rewrites forgiving selector-list functions into candidate parts before serialization.
- A single simple decoded `:is()` candidate is emitted directly after CSS Modules local/global rewriting.
- Type selectors, explicit universal selectors, and selectors with top-level combinators keep the `:is(...)` wrapper, matching the upstream `should_unwrap_is()` guard.
- Existing forgiving-list recovery remains unchanged for `:where()`, `:has()`, prefixed `any()` functions, multi-candidate `:is()`, and `nth-child()` `of` selector lists.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-single-is-local-global.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 402 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-single-is-local-global.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6116 assertions, 0 failures`.

## Status Delta

- `lane-status.json` `phpPass`: `6110 -> 6116`.
- Conservative mapped coverage remains `2348 / 3532`; this deepens the represented CSS Modules local/global/composes selector cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules selector scanner, forgiving selector-list recovery, export metadata model, minifier pipeline, and WordPress example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not touch the stale May 25 `CustomMediaTransformer.php` rework note, nested `composes` rejection, forgiving invalid local/global selector-list dropping, escaped composes properties, escaped numeric compose, view-transition guards, bundled CSS Modules option propagation, bundle/import graph, source-map, media-query, property-value, CSSOM, target-prefixing, or custom at-rule slices. The patch is limited to single simple `:is()` serialization after CSS Modules local/global selector rewriting while preserving composed export metadata.

## Next Task

Continue with non-overlapping CSS Modules selector/composes edges or pivot to current-priority LightningCSS source-map, bundle/import graph, CSSOM, media-query, target-prefix, property/value, or custom at-rule parity.
