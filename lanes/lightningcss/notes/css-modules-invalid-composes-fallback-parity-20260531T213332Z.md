# CSS Modules Invalid Composes Fallback Parity

- Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T213332Z`.
- Upstream source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, especially `src/properties/css_modules.rs::Composes::parse`, `src/css_modules.rs::CssModule::handle_composes`, and native NAPI spot-checks from `.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
- Upstream behavior: malformed `composes` values such as `from global`, `foo from`, `foo from bar`, `foo from "foo.css" bar`, CSS-wide keywords, quoted names, and function tokens are preserved as ordinary declarations and do not add CSS Modules export references. Valid local/global/dependency `composes` declarations still disappear from emitted CSS and update export metadata; valid `composes` inside nested rules still errors.

## Implementation

- `CssModulesTransformer::rewriteDeclarationStatement()` now attempts to parse `composes` values before applying nested-rule errors.
- Invalid `composes` values fall back to the ordinary declaration path, so they are minified and emitted without mutating `exports` or `references`.
- The nested-rule guard still applies after a value parses as a real CSS Modules `composes` declaration, preserving accepted error behavior for valid nested composition.
- Added `wordpress-css-modules-invalid-composes.php` to model legacy block CSS where malformed `composes` text is preserved while a valid local composed class still flattens for PHP-only WordPress delivery.

## Evidence

- Red-first PHP spot-check before the change: `.test { composes: from global; color: red }` threw `Invalid CSS Modules composes declaration`; pinned upstream native output was `.EgL3uq_test{composes:from global;color:red}` with only the `test` export.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 288 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 4473 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-invalid-composes.php --self-test` => `OK`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php`, `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`, and `php -l lanes/lightningcss/examples/wordpress-css-modules-invalid-composes.php` passed.
- `git diff --check -- lanes/lightningcss` passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules evidence is now `1 file / 288 assertions / 0 failures`.
- Full LightningCSS PHP evidence moves from `4453` to `4473 pass / 0 fail`.
- Conservative mapped coverage remains `2145 / 3532`; this deepens the already represented CSS Modules local/global/composes cluster instead of claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules transformer, declaration scanner, selector/export metadata model, `NestingTransformer`, and `CssMinifier`. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted CSS Modules selector-list validation, escaped selector/composes identifiers, functional `:local()` composes rejection, pseudo-element boundaries, host-context behavior, pure no-check/license handling, unusedSymbols/global handling, animation/keyframes, counter-style/list-style, grid/scope/container/dashed-ident/view-transition, content-hash/project-root hashing, source-index compose flattening, or bundle dependency diagnostics. The older LightningCSS rework note for `CustomMediaTransformer.php` is unrelated to this CSS Modules fallback behavior.
