# LightningCSS CSS Modules Grid Local/Global Compose Parity 2026-05-31T18:16Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T181653Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/lib.rs::test_css_modules` grid helper cases and `src/properties/grid.rs` CSS Modules grid line-name guard.
- Pinned local NAPI oracle confirmed minified CSS Modules output for `grid: [header-top] "a a a" ...`, `grid-template-areas`, `grid-area`, `grid-column-start`, `grid: false`, and the invalid `pattern: "test-[local]-[hash]"` grid-line-name error.

Implementation:

- `CssModulesTransformer` now scopes CSS Modules grid area and line names in `grid`, `grid-template`, `grid-template-rows`, `grid-template-columns`, `grid-template-areas`, `grid-area`, `grid-row`, and `grid-column` declarations.
- The new `grid` option defaults to upstream enabled behavior and disables grid-name rewriting/exporting when set to `false`.
- Grid line-name scoping now enforces the upstream pattern rule that CSS Modules patterns used with grid line names must end in `[local]`.
- Existing local/global/dependency `composes` metadata remains attached to the owning local class while grid area/line exports are added.
- `CssBundler` forwards the `grid` CSS Modules option through bundled import graphs.
- `wordpress-css-modules-transformer.php` now smokes a block card grid layout with scoped area names, line names, and local `composes` metadata.

Evidence:

- Red-first PHP spot-check before the implementation emitted public grid names such as `grid-area:a`, produced no grid-name exports, and ignored the upstream invalid grid pattern boundary.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php`
- `php -l lanes/lightningcss/src/CssBundler.php`
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `2 test files, 338 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2936 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

Coverage delta:

- Focused CSS Modules transformer evidence moves from `164` to `173` assertions.
- Full LightningCSS PHP evidence moves from `2923` to `2936` pass / `0` fail.
- Conservative mapped upstream coverage moves from `1645 / 3532` to `1648 / 3532` for three direct CSS Modules grid helper cases. The bundler `grid` option forwarding test deepens the existing CSS Modules bundle/import-graph cluster and is not counted as a new denominator row.

Dependency closure:

- No new support component is needed. This reuses the lane-local CSS Modules transformer, bundler option plumbing, CSS identifier scanner, minifier/nesting pipeline, and WordPress example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

Non-overlap:

- This does not repeat accepted CSS Modules local/global selector-list validation, functional `:local()` composes rejection, escaped selector/composes identifiers, declaration-priority `composes`, animation/keyframes scoping, counter-style/list-style scoping, container-query name scoping, `@scope` prelude scoping, dashed-ident `@property` / `@font-palette-values` scoping, view-transition scoping, content-hash/project-root hashing, missing-export bundling, or file-backed CSS Modules bundle resolution. It only closes CSS Modules grid area/line-name scoping and option-boundary behavior while preserving `composes` exports.

Next task:

- Continue CSS Modules parity on non-overlapping pseudo replacement, unused symbol, dependency flattening, or selector-valued option boundaries not covered by accepted local/global/composes, grid, container, scope, view-transition, animation, counter-style/list-style, dashed-ident, and content-hash slices.
