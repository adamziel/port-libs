# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01 09:54 UTC

## Slice

Ported the upstream bundle/import graph diagnostic ordering for same-file nested CSS Modules `composes ... from` declarations. These declarations are parser errors and must not enter the resolver/read dependency graph.

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source: `src/bundler.rs` collects CSS Modules dependencies from parsed top-level `CssRule::Style` declarations only. If parsing fails for nested `composes`, dependency resolution does not run.
- Local NAPI reproduction against `lightningcss.linux-x64-gnu.node`:
  - `@media screen { .card { composes: token from "./missing.css"; } }`
  - result: `The \`composes\` property cannot be used within nested rules` at `/entry.css` line 3 column 14, with no resolver/read callback for `./missing.css`.
  - `@supports (composes: token from "./missing.css") { .card { composes: token from "./missing.css"; } }` reports the same nested parser diagnostic and ignores the false `from` text inside the supports condition.
  - A stylesheet with an earlier valid top-level `composes` and a later nested `composes from` reports the later nested declaration at line 4 column 14 upstream.

## Implementation

- `CssBundler` now converts `CssModulesTransformer` nested-composes failures directly to `CssBundleException` parser diagnostics at the first real `composes` declaration location.
- Removed the same-file nested dependency preflight path that previously resolved or read `from` specifiers before surfacing the parser error.
- Conditional-import CSS Modules behavior remains unchanged: a top-level `composes` in a conditionally imported stylesheet can still load its dependencies first, then reject the conditional module context.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 670 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7260 assertions, 0 failures`

## Dependency Closure

No new support component is needed. This reuses the existing native PHP CSS Modules transformer, bundler resolver, and source-location helpers.

## Follow-Up

Remaining non-overlapping bundle/import graph parity: nested dashed `env()`/`var()` CSS Modules `from` syntax should match upstream parser diagnostics instead of entering dependency resolution.
