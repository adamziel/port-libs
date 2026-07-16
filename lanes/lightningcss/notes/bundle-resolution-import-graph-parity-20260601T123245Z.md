# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01 12:32 UTC

## Slice

Ported the upstream CSS Modules bundle/import graph boundary for dashed
custom-ident `var(... from "...")` references in conditional or nested rules.
Upstream treats dependency references as graph edges only for direct root style
rules. The same syntax inside an `@media` block or a nested style rule scopes
the dashed ident locally and must not call resolver or reader callbacks for the
`from` specifier.

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss`
  `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Local cached native `bundleAsync` reproduction:
  `@media screen { .card { color: var(--gap from "./missing.css", red); } }`
  with `cssModules.dashedIdents` reads only `/entry.css`, calls no resolver,
  and serializes `var(--scoped-gap,red)`.
- Upstream `src/bundler.rs` collects CSS Modules dependencies by iterating
  only direct `CssRule::Style` entries in `stylesheet.rules`; conditional
  `@media` / `@supports` rule bodies are not dependency graph sources.

## Implementation

- `CssModulesTransformer` now carries a `recordDependencyReferences` flag
  through parsed rule bodies.
- Direct root style declarations still record dashed `var(... from "...")`
  dependency placeholders for the bundler.
- Conditional and nested declaration bodies now scope quoted `from`
  references as local dashed idents instead of recording dependency
  references. `env(... from ...)` remains a parser diagnostic as before.
- `wordpress-bundle-import-graph.php` now proves the WordPress-facing bundle
  path scopes conditional `var(... from "pkg:missing.css")` without resolver
  or reader side effects.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `2 test files, 1328 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7778 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - passed, including
    `css-modules-conditional-var-dependency: scoped-without-resolve`
- `git diff --check -- lanes/lightningcss`
  - passed

Root harness: not run - isolated micro-slice.

## Status Delta

- Full lane focused evidence moves from `7771` to `7778` passing assertions.
- `lane-status.json` keeps `phpFail` at `0`.
- Mapped upstream inventory remains `2392 / 3532`; this deepens the already
  mapped CSS Modules bundle/import graph cluster rather than adding a new
  manifest denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`CssModulesTransformer`, `CssBundler`, reader/resolver boundary, and existing
CSS Modules dashed-ident export metadata. No Node, Rust, WASM, or external
service runtime is introduced.

## Non-Overlap

This does not repeat accepted import source tokenization, malformed `@layer`
statement diagnostics, `env(... from ...)` parser diagnostics, nested
`composes` diagnostics, resolver result shape validation, source-map remap,
media-query, CSSOM, custom at-rule, property/value, or target-prefixing
parity slices.

## Follow-Up

One useful follow-up is auditing duplicate-specifier diagnostic locations when
a conditional `var(... from "...")` and a direct root style `var(... from
"...")` reference share the same specifier. The current patch fixes traversal
and output parity for conditional references without changing existing
top-level dependency diagnostics.
