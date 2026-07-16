# LightningCSS CSS Modules Functional Local Compose Parity 2026-05-31T15:10Z

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/css_modules.rs::handle_composes` and `src/lib.rs::test_css_modules`.
- Upstream behavior: `composes` is accepted only when the original selector is a single raw class component. Functional `:local(.test) { composes: foo }` is rejected even though plain `:local(.test)` selectors are otherwise valid for scoping.
- Native upstream spot-check used the local NAPI artifact at the pinned cache: `:local(.foo) { composes: bar; color:red }` throws, while `.foo { composes: bar; color:red }` emits `.EgL3uq_foo{color:red;}` with a local compose reference.

## Native Changes

- `CssModulesTransformer::isSimpleLocalClassSelector()` no longer unwraps functional `:local(...)` when validating `composes` selectors.
- The repeated-composes de-duplication test now uses a plain `.test` selector, preserving valid local/global/dependency metadata behavior.
- Added rejection coverage for `:local(.test) { composes: ... }`, selector-list variants, and top-level conditional-rule variants.
- `wordpress-css-modules-transformer.php` now keeps the valid block title composition on `.cardTitle` and separately verifies that a migration-authored `:local(.card) { composes: reset }` rule is rejected.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php` => all reported no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 76 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1832 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- `git diff --check -- lanes/lightningcss` => passed.

## Status Delta

- Full LightningCSS PHP evidence moves from `1829` to `1832 pass / 0 fail`.
- Conservative mapped coverage remains `1258 / 3532`; this adds stricter behavior inside the already represented CSS Modules local/global/composes cluster.

## Dependency Closure

- No new support component is needed. This reuses the existing native CSS Modules selector scanner, composes parser, and lane-local minifier/nesting pipeline.

## Non-Overlap

- This does not repeat accepted CSS Modules selector-list `:local()` / `:global()` validation, nested-global mode precedence, strict `composes from` delimiter parsing, missing dependency export flattening, view-transition scoping, or bundle import-graph behavior. It only tightens the `composes` selector validity boundary for functional `:local(...)`.
