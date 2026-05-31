# CSS Modules Nested Unused Export Pruning Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T215424Z`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/lib.rs::test_unused_symbols`, CSS nesting lowering, and CSS Modules export metadata after local/global selector rewriting.
- Native NAPI spot-check at the pinned cache confirmed that when `unusedSymbols` removes a nested parent rule such as `.x { &.y { ... } }`, the emitted CSS omits `.y` and the CSS Modules `exports` map also omits the stale child local. A surviving negative selector such as `:not(.foo)` keeps the `.foo` export because the scoped class remains in emitted CSS.

## Implementation

- `CssModulesTransformer::pruneUnusedExports()` now removes stale non-referenced exports whose scoped class no longer appears in the surviving CSS after unused-symbol pruning.
- Referenced custom idents such as scoped animation/counter-style/dashed identifiers remain preserved even when their scoped export name is not directly emitted, matching the existing upstream metadata contract.
- The focused test proves nested unused child classes (`bar`, `y`) do not leak into exports after their parent rules are pruned, while a surviving `.survivor { composes: reset }` export still keeps local compose metadata.
- `wordpress-css-modules-unused-symbols.php` now includes a nested legacy wrapper branch so stale nested block-module exports are caught by the example smoke.

## Verification

- Red-first PHP spot-check before the fix emitted the right CSS for the upstream nested unused-symbol case but left stale `bar` and `y` entries in `exports`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-unused-symbols.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 276 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 4517 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-unused-symbols.php --self-test` => `OK`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules assertions move from `273` to `276`.
- Full LightningCSS PHP evidence moves from `4514` to `4517 pass / 0 fail`.
- Conservative mapped coverage remains `2152 / 3532`; this deepens the already represented CSS Modules unusedSymbols/local-global-compose cluster rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the lane-local `CssModulesTransformer`, existing `NestingTransformer`, selector scanner, export metadata model, and WordPress example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted CSS Modules local/global selector-list validation, nested global/local precedence, escaped identifiers/specifiers, declaration-priority `composes`, functional `:local()` composes rejection, pure no-check/license handling, animation/keyframes scoping, counter-style/list-style scoping, grid, container, scope, dashed-ident, view-transition, content-hash/project-root hashing, dependency flattening, bundled repeated source-index composes, global unused-symbol preservation, host-context, attribute selector serialization, or pseudo-element boundaries. It only closes stale export pruning for nested local selectors removed by `unusedSymbols` while preserving surviving local `composes` metadata.
