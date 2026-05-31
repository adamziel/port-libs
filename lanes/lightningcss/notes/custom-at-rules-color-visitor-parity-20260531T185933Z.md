# Custom At-Rules Color Visitor Parity 2026-05-31T18:59Z

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream cases:
  - `node/test/composeVisitors.test.mjs` `different types`, where composed `Length` and `Color` visitors both run in one transform.
  - `node/test/composeVisitors.test.mjs` `same properties`, where a returned RGB color from one `Color` visitor is passed to the next `Color` visitor.
- `node/index.d.ts` exposes `Visitor.Color?(color: CssColor): CssColor | void`.

## Implementation

- `CustomAtRuleTransformer::composeVisitors()` now composes `Color` visitors in sequence.
- `CustomAtRuleTransformer` configures a direct `Color` visitor and applies it to simple parsed color declarations such as `color: red`.
- Simple named, hex, transparent, and `currentColor` values are parsed into upstream-shaped color arrays for visitor dispatch while unresolved values are preserved.
- The WordPress custom at-rule smoke now uses the same visitor pipeline to rewrite a block viewport color without Node, Rust, WASM, or browser services.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: `1 test files, 93 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 3213 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  - Result: `OK`.

## Status Delta

- Focused custom at-rule assertions: `86 -> 93`.
- Full LightningCSS lane assertions/pass count: `3206 -> 3213`.
- Conservative mapped coverage: `1721 -> 1723 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the bounded native `CustomAtRuleTransformer`, `DeclarationBlock`, `CssMinifier`, and existing visitor composition/scanner helpers. No external parser, browser, Node, Rust, WASM, network, or live-service dependency is introduced.

## Non-Overlap

This avoids accepted custom at-rule parser basics, returned media/style/supports rule serialization, native media visitors, Token at-keyword and dimension visitors, Function/FunctionExit, EnvironmentVariable, Variable, Length, Selector, Url, DashedIdent, CustomIdent, StyleSheet, Declaration visitor clusters, CSS Modules, source-map, bundler/import graph, CSSOM, media-query, property-value color minifier, and target-prefix slices. The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this Color visitor slice.

Root harness status: not run - isolated micro-slice.
