# CSS Modules Dashed Property Palette Compose Parity

- Slice: `lightningcss-css-modules-local-global-compose-parity-20260531T180148Z`.
- Source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/lib.rs::test_css_modules` dashed-ident helper case plus `src/css_modules.rs` export/reference handling.
- Upstream oracle: direct local NAPI call to `lightningcss.linux-x64-gnu.node` showed `dashedIdents: true` scopes `@property --foo`, `@font-palette-values --Cooler`, and `font-palette: --Cooler` to `--EgL3uq_*` exports while preserving `.foo { composes: base from "tokens.css" }` dependency metadata.

## Implementation

- `CssModulesTransformer` now scopes dashed custom-ident names in `@property` and `@font-palette-values` preludes when `dashedIdents` is enabled.
- `font-palette` declaration values that are single dashed custom identifiers now become referenced dashed CSS Module exports.
- Existing local/global/dependency `composes` parsing and selector validation remain unchanged; the new focused test proves dependency `composes` metadata survives alongside the dashed-ident rewrites.
- `wordpress-css-modules-transformer.php` now includes a build-free registered design-token and color-font palette module smoke path using dependency `composes`.

## Evidence

- Red-first PHP spot-check before the implementation left `@property --foo`, `@font-palette-values --Cooler`, and `font-palette: --Cooler` unscoped while only `--foo` declaration/`var()` references were scoped.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 153 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2832 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules transformer, CSS identifier scanner, dashed-ident export/reference map, minifier, and WordPress example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted CSS Modules local/global selector-list validation, functional `:local()` composes rejection, escaped selector/composes identifiers, declaration-priority `composes`, animation/keyframes scoping, counter-style/list-style scoping, container-query name scoping, dashed-ident bundle graph behavior, content-hash/project-root hashing, missing-export bundling, or file-backed CSS Modules bundle resolution. It closes the unhandled dashed `@property` / `@font-palette-values` / `font-palette` subset inside the upstream CSS Modules cluster.
