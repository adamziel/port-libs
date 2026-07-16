# CSS Modules Animation Local/Global Compose Parity

- Slice: `lightningcss-css-modules-local-global-compose-parity-20260531T164440Z`.
- Upstream source truth: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_css_modules` animation-name cases plus `src/css_modules.rs::CssModule::handle_composes`.
- Bounded behavior: CSS Modules now scopes animation custom idents and `@keyframes` names, marks referenced animation exports, decodes quoted animation names, preserves `none` and `var()` as non-names, honors `animation => false`, and keeps local/global/dependency `composes` metadata on the owning local class.

## Evidence

- Pre-fix spot check: PHP emitted `.EgL3uq_test{animation:rotate var(--duration) linear infinite}@keyframes rotate...` and only exported `test`; pinned local NAPI emitted `.EgL3uq_test{animation:EgL3uq_rotate var(--duration) linear infinite}@keyframes EgL3uq_rotate...` with `rotate.isReferenced=true`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 120 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2324 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- PHP lint covered changed PHP files.
- `git diff --check -- lanes/lightningcss` passed.

## Coverage Delta

- Focused CSS Modules test evidence moved from `109` to `120` assertions.
- Full LightningCSS PHP evidence moved from `2313` to `2324` pass / `0` fail.
- Conservative mapped upstream coverage moved from `1446 / 3532` to `1451 / 3532` for five direct animation-name CSS Modules helper cases.

## Non-Overlap

This does not repeat accepted CSS Modules hash/content-hash, escaped dependency specifier, escaped local selector, pure-mode selector boundary, functional local composes rejection, view-transition scoping, missing-export bundler, dashed-ident import graph, or local/global selector-list validation slices. It specifically closes the unhandled animation/keyframes custom-ident subset inside the same upstream CSS Modules cluster.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local `CssModulesTransformer`, native CSS identifier/string decoders, selector/export metadata, `NestingTransformer`, and `CssMinifier`; no Node, Rust, WASM, or external parser is required at runtime.
