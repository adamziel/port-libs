# CSS Modules Unknown At-Rule Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T110352Z`

## Source Truth

- Pinned upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream NAPI oracle preserves unknown/custom at-rule bodies as raw custom content during CSS Modules transforms. Selectors inside those bodies are not scoped, and `composes` declarations inside those raw bodies are not exported or rejected.
- Known CSS Modules at-rule bodies such as `@media`, `@supports`, `@scope`, `@nest`, and keyframes-compatible rules continue to be parsed and transformed.
- Oracle samples from the pinned NAPI binary:
  - `@foo { .test { composes: bar; color: red } .bar{color:blue} }` prints `@foo{.test { composes: bar; color: red } .bar{color:blue}}` with no exports.
  - `@foo { composes: bar; color: red; }` prints `@foo{composes: bar; color: red}` with no exports.
  - `@media (min-width:1px) { @foo { .test { composes: bar; color: red } } .bar{color:blue} }` preserves the nested `@foo` body while still scoping `.bar` inside the known media rule.

## Patch

- `CssModulesTransformer` now masks unknown custom at-rule bodies while running the CSS Modules and nesting passes, restores the raw body afterward, and normalizes trailing raw declaration semicolons to match upstream minified output.
- `@nest` is explicitly kept in the parsed at-rule list so existing nested CSS Modules behavior still lowers and scopes normally.
- `CssModulesTransformerTest` adds focused local/global/compose coverage for top-level unknown at-rules, unknown at-rules nested inside known `@media`, and declaration-list-like unknown at-rule bodies.
- `examples/wordpress-css-modules-unknown-at-rule.php` demonstrates a WordPress token at-rule staying raw while adjacent CSS Modules exports and local `composes` still work without Node/WASM at runtime.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` - pass.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` - pass.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-unknown-at-rule.php` - pass.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` - pass, 1 file / 550 assertions / 0 failures.
- `php lanes/lightningcss/examples/wordpress-css-modules-unknown-at-rule.php --self-test` - pass, `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` - pass, 13 files / 7509 assertions / 0 failures.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP CSS Modules transformer, nesting transformer, and example self-test harness. Upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.

## Non-Overlap

This slice avoids the accepted bundle/import graph, source-map rejected-child merge, terminal pseudo-element boundary, composes-before-nested ordering, custom at-rule visitor, media-query, target-prefixing, CSSOM, and property/value clusters. The only new behavior is CSS Modules preservation of unknown custom at-rule bodies around local/global/composes handling.
