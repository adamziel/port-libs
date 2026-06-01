# LightningCSS CSS Modules Container Name Parity 2026-06-01T16:26Z

## Source Truth

- Pinned upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream files consulted:
  - `src/css_modules.rs`
  - `src/rules/container.rs`
  - `src/properties/contain.rs`
  - `src/values/ident.rs`
- Pinned native NAPI oracle confirmed `.card { container-name: layout card; container-type: inline-size } @container layout (...)` serializes with `container:EgL3uq_layout EgL3uq_card/inline-size`, exports both `layout` and `card`, and leaves container names public when either `container: false` or `customIdents: false` is configured.

## Implementation

- `CssModulesTransformer` now scopes CSS Modules `container-name` custom identifiers when `container` and `customIdents` are enabled.
- The `container` shorthand now scopes its name-list side before an optional slash-delimited container type.
- The new logic preserves local/dependency `composes` metadata and respects the existing `container` and `customIdents` options.
- Added `wordpress-css-modules-container-name.php` to model build-free block CSS where container query names and runtime class lists must stay aligned.

## Verification

- Red-first PHP spot-check before the fix emitted `container:layout card/inline-size` and exported no `layout` container-name symbol while the pinned upstream oracle emitted scoped names.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-container-name.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 677 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-container-name.php --self-test` => `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 8645 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` => passed.

## Status Delta

- `phpPass` moves `8635 -> 8645`.
- Conservative mapped coverage remains `2398 / 3532`; this deepens the already represented CSS Modules custom-ident/local-global/composes cluster instead of claiming a new denominator row.
- Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules declaration rewriter, CSS identifier decoder/escaper, container at-rule scoping, minifier, and PHP example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted CSS Modules `@container` prelude scoping, animation timeline dashed idents, transition-property public identifiers, counter-style/list-style scoping, grid area scoping, `@scope` preludes, page composes descriptors, dangling local/global selector recovery, bundle/import graph, source-map, CSSOM, media-query, custom-at-rule visitor, target-prefix, or property-value slices. The bounded behavior is property-level `container-name` and `container` shorthand custom-ident scoping.
