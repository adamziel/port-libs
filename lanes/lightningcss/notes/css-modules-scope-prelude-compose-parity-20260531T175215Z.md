# LightningCSS CSS Modules Scope Prelude Compose Parity 2026-05-31T17:52Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T175215Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `src/lib.rs` around `pure_css_module_options` @scope cases: three `minify_error_test_with_options` cases for impure scope roots/limits/body selectors and one accepted `@scope (.a) to (.b) { .foo { ... } }` case.
  - `src/rules/scope.rs`, where pure mode validates `scope_start` and `scope_end` with `is_pure_css_modules_selector` before printing selector lists through the CSS Modules selector context.

Implementation:

- `CssModulesTransformer` now rewrites `@scope` root and limit selector lists through the existing CSS Modules selector rewriter.
- Pure mode now validates `@scope` roots and limits, so `@scope (div)` and `@scope (.a) to (div)` fail before output, matching upstream pure selector boundaries.
- Scoped rule bodies still preserve local/global/dependency `composes` metadata on their owning local class.
- `wordpress-css-modules-transformer.php` now models a block module whose scoped CSS starts at a local `.cardScope`, stops before public `.wp-block-buttons`, and composes the scoped card class from the local card export.

Evidence:

- Pre-fix PHP spot-check accepted `@scope (div) { .foo { color: red } }` in pure mode and left `@scope (.a) to (.b)` preludes unscoped while only scoping the body.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 157 assertions, 0 failures`.
- Full lane after fix: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2805 assertions, 0 failures`.
- Example smoke after fix: `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- Conservative mapped coverage moves from `1601 / 3532` to `1605 / 3532`.
- Root harness status: not run - isolated micro-slice.

Dependency closure:

- No new support component is needed. This reuses the lane-local CSS Modules selector scanner, pure-mode selector validation, `NestingTransformer`, and `CssMinifier`.

Non-overlap:

- This does not repeat accepted CSS Modules selector-list validation, nested global/local mode precedence, escaped local identifiers, escaped dependency specifiers, functional `:local()` composes rejection, pure-mode top-level selector boundaries, animation/keyframes scoping, counter-style/list-style export scoping, container-query name scoping, view-transition scoping, content-hash patterning, dashed-ident dependency graphs, missing-export bundle flattening, or bundler import graph behavior. It only closes CSS Modules `@scope` prelude selector scoping and pure boundary validation while preserving scoped-body `composes` exports.

Next task:

- Continue CSS Modules parity on a non-overlapping cluster such as remaining selector-valued rule preludes, dependency graph flattening, or option-boundary behavior not already covered by accepted local/global/composes, `@scope`, container-name, view-transition, animation, counter-style/list-style, dashed-ident, and content-hash slices.
