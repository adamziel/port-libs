# CSS Modules Container Query Name Parity

- Slice: `lightningcss-css-modules-local-global-compose-parity-20260531T172358Z`.
- Source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/lib.rs::test_css_modules` `@container main (width >= 0)` cases plus `src/rules/container.rs::ContainerName::to_css`, where CSS Modules scopes container names only when `config.container` and `custom_idents` are enabled.

## Implementation

- `CssModulesTransformer` now supports a `container` option, defaulting to upstream-enabled behavior.
- `CssBundler::bundleCssModules()` now passes the same `container` option through to per-file CSS Module transforms.
- Valid `@container` names are scoped through the existing CSS Modules name pattern and exported as custom identifiers.
- `container => false` preserves `@container` names while still exporting local classes in the rule body.
- `style(...)`, `scroll-state(...)`, `not`, and reserved container keywords remain condition syntax rather than scoped names.
- Nested at-rule preludes now pass through the same CSS Modules prelude rewrite path before `NestingTransformer` lowers the rule.
- Existing local/global/dependency `composes` metadata is preserved for the owning class.

## Evidence

- Pre-fix PHP spot check emitted `@container main (width>=0){.EgL3uq_box2{...}}` and exported only `box2`; upstream scopes `main` to `EgL3uq_main` by default.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` => no syntax errors.
- `php -l lanes/lightningcss/src/CssBundler.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 146 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 137 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2670 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules test evidence moves from `139` to `146` assertions.
- Focused CSS bundler test evidence moves from `133` to `137` assertions for the option pass-through guard.
- Full LightningCSS PHP evidence moves from `2659` to `2670 pass / 0 fail`.
- Conservative mapped upstream coverage moves from `1566 / 3532` to `1568 / 3532` for the two direct `src/lib.rs::test_css_modules` container-name helper cases.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules selector/declaration scanner, CSS identifier decoder/escaper, export metadata model, and existing nesting/minification pipeline. No Node, Rust, WASM, browser service, or external CSS parser is required at runtime.

## Non-Overlap

This does not repeat accepted CSS Modules selector-list validation, nested global/local mode precedence, escaped local identifiers, escaped dependency specifiers, functional `:local()` composes rejection, pure-mode selector boundaries, animation/keyframes scoping, counter-style/list-style export scoping, view-transition scoping, content-hash patterning, dashed-ident dependency graphs, missing-export bundle flattening, or bundler import graph behavior. It only closes CSS Modules `@container` rule-name scoping and the disabled-container option boundary while preserving composes exports.
