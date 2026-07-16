# LightningCSS CSS Modules Escaped Invalid Composes Parity 2026-06-01T04:27Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T042747Z`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/properties/css_modules.rs::Composes::parse` plus `src/css_modules.rs::CssModule::handle_composes`, where malformed `composes` declarations fall back to ordinary declaration output without adding export references.
- Local pinned NAPI oracle confirmed that malformed escaped CSS Modules compose values are preserved but serialized canonically:
  - `.test { composes: foo \66 rom; color: red }` prints `composes:foo from` and keeps no compose reference.
  - `c\6f mposes: foo \66 rom` prints the property as `composes` and the escaped keyword as `from`.
  - `composes: foo \75rl(bar)` prints `composes:foo url(bar)`.

## Implementation

- `CssModulesTransformer` now splits declaration priority before parsing `composes`, preserving accepted valid-`composes` handling.
- Invalid `composes` fallbacks now serialize as ordinary canonical `composes:` declarations instead of returning the raw statement verbatim.
- The fallback serializer decodes escaped identifier tokens and escaped function names while preserving quoted strings and punctuation, matching the upstream behavior for malformed CSS Modules compose values.
- The WordPress invalid-composes example now covers escaped legacy compose text so PHP-only block CSS delivery keeps invalid declarations visible without creating bogus module dependencies.

## Evidence

- Red-first PHP spot-check before the fix emitted `.EgL3uq_test{composes:foo \66 rom;color:red}` where the pinned upstream NAPI oracle emitted `.EgL3uq_test{composes:foo from;color:red}`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-invalid-composes.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 392 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-invalid-composes.php --self-test` => `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 5954 assertions, 0 failures`.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules evidence moves from `383` to `392` assertions.
- Full LightningCSS PHP evidence moves from `5945` to `5954` pass / `0` fail.
- Conservative mapped coverage remains `2336 / 3532`; this deepens the already represented CSS Modules local/global/composes fallback cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules transformer, CSS escape decoder, declaration scanner, minifier/nesting path, PHP test harness, and existing WordPress example self-test. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted CSS Modules selector-list validation, forgiving local/global selector handling, nested global/local mode precedence, escaped selector identifiers, escaped dependency specifiers, declaration-priority valid `composes`, comment-token compose separators, functional `:local()` composes rejection, pure-mode boundaries, animation/keyframes, counter-style/list-style, grid/container/scope/dashed-ident/view-transition behavior, unused-symbol pruning, bundled CSS Modules options, source maps, CSSOM, media-query, property-value, custom at-rule, or target-prefixing slices. It is limited to escaped-token canonicalization for malformed `composes` declarations that are preserved as ordinary CSS.

## Next Task

Continue CSS Modules parity on non-overlapping selector/value integration or bundle metadata edges, or pivot to the current high-value LightningCSS clusters: bundle/import graph, source maps, CSSOM read/write, media queries, property values, custom at-rules, or target prefixing.
