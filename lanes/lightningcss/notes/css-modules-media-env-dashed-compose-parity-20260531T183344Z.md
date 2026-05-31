# CSS Modules Media Env Dashed Compose Parity

- Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T183344Z`.
- Source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream read: `src/lib.rs::test_environment` CSS Modules helper case where `@media (max-width: env(--branding-small))` scopes to `env(--EgL3uq_branding-small)` when `dashed_idents` is enabled, while `.foo { color: env(--brand-color) }` scopes the declaration value and export metadata marks both dashed idents referenced.

## Implementation

- `CssModulesTransformer` now applies the existing dashed `var()` / `env()` reference rewriter to `@media` preludes when `dashedIdents` is enabled.
- The focused test preserves local selector export metadata plus dependency and global `composes` references in the same rule, proving the media prelude rewrite does not disturb CSS Modules composition.
- `wordpress-css-modules-transformer.php` now includes a build-free responsive palette module smoke path where `env(--card-breakpoint)` in `@media` and `env(--card-accent)` in declarations are scoped without Node/WASM.

## Evidence

- Red-first PHP spot-check before the fix: `@media (max-width: env(--branding-small)) { .foo { color: env(--brand-color); composes: base from "tokens.css"; } }` produced `@media (width<=env(--branding-small))` and omitted the `--branding-small` referenced export.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 169 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 3063 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules test evidence moves from `166` to `169` assertions.
- Full LightningCSS PHP evidence moves from `3060` to `3063 pass / 0 fail`.
- Conservative mapped coverage moves from `1684 / 3532` to `1685 / 3532` for the directly targeted upstream CSS Modules `@media env()` helper case.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules transformer, dashed-ident export/reference map, `env()` / `var()` scanner, media-query minifier path, and WordPress example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted CSS Modules local/global selector-list validation, nested global/local mode precedence, escaped identifiers/specifiers, declaration-priority `composes`, functional `:local()` composes rejection, pure-mode no-check/license handling, animation/keyframes scoping, counter-style/list-style scoping, container-name scoping, `@scope` prelude scoping, dashed `@property` / `@font-palette-values` / `font-palette` scoping, view-transition scoping, content-hash/project-root hashing, missing-export bundling, or file-backed import graph resolution. It only closes dashed CSS Modules `env()` references in `@media` conditions while preserving local/global/dependency compose metadata.
