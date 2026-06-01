# CSS Modules Local/Global Compose Parity - 2026-06-01T04:45Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T044522Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant source areas: `src/lib.rs::test_css_modules`, `src/css_modules.rs::CssModule::handle_composes`, and selector parsing before CSS Modules local/global export collection.
- Local pinned NAPI oracle (`.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`) rejects selector comments that split identifier fragments, including `.card/*marker*/Title`, `:local(.card/*marker*/Title)`, `:global(.wp-block/*marker*/button) .card`, and the same shape inside `@media` before `composes` metadata is trusted.

## Implementation

- `CssModulesTransformer::stripComments()` now marks selector/prelude comments that would otherwise join adjacent CSS identifier fragments.
- `rewriteSelectorList()` rejects those marked selector boundaries with `CSS comments cannot split selector identifiers` before local/global selector rewriting, pure checks, or `composes` export mutation.
- Valid comments between selector tokens, such as `.card/*marker*/.is-wide` and `:global(.wp-block/*marker*/.legacy)`, still serialize to upstream-compatible compound selectors.
- Added `wordpress-css-modules-commented-selectors.php` to prove WordPress block classes are not silently joined when build tools insert comments into CSS Module selectors.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-commented-selectors.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> 1 test file, 394 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-css-modules-commented-selectors.php --self-test` -> OK.
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 6008 assertions, 0 failures.

## Status Delta

- `lane-status.json` `phpPass`: `6001 -> 6008`.
- Conservative mapped coverage remains `2340 / 3532`; this deepens the represented CSS Modules local/global/composes selector cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules transformer, selector scanner, comment scanner, export metadata model, and existing PHP example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted invalid escaped selector-newline guards, escaped `:local()` / `:global()` pseudo handling, escaped selector identifiers, escaped/commented `composes` property parsing, invalid `composes` fallback, pseudo-element boundaries, host-context behavior, forgiving selector filters, unused-symbol pruning, nested `@nest` handling, bundled CSS Modules option propagation, source-map, bundle/import graph, media-query, property-value, CSSOM, custom-at-rule, or target-prefixing slices. The stale May 25 `CustomMediaTransformer.php` rework note was inspected and is unrelated to this current CSS Modules selector-comment boundary behavior.

## Next Task

Continue with non-overlapping CSS Modules selector/composes edges, bundle dependency graph behavior, source maps, media queries, CSSOM, custom at-rules, target prefixing, or property/value parity on current base.
