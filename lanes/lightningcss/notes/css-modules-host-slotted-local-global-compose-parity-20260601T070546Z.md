# CSS Modules Host/Slotted Local/Global Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T070546Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Local pinned NAPI oracle from `.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` confirmed:
  - `:host(.foo, .bar)` and `::slotted(.foo, .bar)` reject with `Unexpected token Comma`.
  - `:host(.foo .bar)` and `::slotted(.foo .bar)` reject with `Invalid state`.
  - `:host()` and `::slotted()` reject with `Invalid empty selector`.
  - `:host(.foo||.bar)` rejects with `Unexpected token Delim('|')`.
  - `:host(:global(.foo).bar)` and `::slotted(:local(.foo).bar)` rewrite the local classes and preserve global classes.
  - Descendants produced inside nested `:global()` / `:local()` are allowed, e.g. `:host(:global(.foo .bar))` and `::slotted(:local(.foo .bar))`.

## Implementation

- `CssModulesTransformer` now recognizes CSS Modules selector-path `:host(...)` and `::slotted(...)` functions before the generic raw pseudo-function fallback.
- The transformer validates direct `:host()` / `::slotted()` arguments for upstream empty-selector, selector-list, descendant/combinator, and namespace-column diagnostics.
- Valid arguments are then rewritten through the existing local/global selector fragment path, preserving scoped local exports, global selectors, and compose metadata.
- The WordPress host-context CSS Modules example now includes `::slotted(:local(...))` output plus invalid host/slotted diagnostics.

## Verification

- Red-first PHP spot check before the patch accepted `:host(.foo, .bar)`, `:host(.foo .bar)`, and `::slotted(.foo, .bar)` and emitted scoped CSS instead of upstream diagnostics.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-host-context.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 455 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-host-context.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6668 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `6655 -> 6668`.
- Focused CSS Modules test assertions: `442 -> 455`.
- Conservative mapped coverage is unchanged; this deepens the represented CSS Modules local/global/composes selector cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules selector scanner, selector function parser, local/global rewriting path, export metadata model, minifier pipeline, and WordPress example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted `:host-context()` handling, escaped local/global pseudo handling, selector-valued pseudo canonicalization, single `:is()` unwrapping, nth-child formula minification, language/direction pseudo guards, view-transition guards, nested `composes` rejection, bundled CSS Modules option propagation, source-map, bundle/import graph, media-query, property-value, CSSOM, target-prefixing, or custom at-rule slices. The patch is limited to CSS Modules `:host()` / `::slotted()` argument validation and local/global rewriting.

## Next Task

Continue with non-overlapping CSS Modules selector/composes edges, or pivot to current-priority LightningCSS source-map, bundle/import graph, CSSOM, media-query, target-prefix, property/value, or custom at-rule parity.
