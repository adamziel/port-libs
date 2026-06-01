# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01 10:35 UTC

## Slice

Ported the upstream CSS Modules bundle/import graph behavior for dashed custom-ident `env(... from "...")` syntax. Upstream treats this as a parser diagnostic, not as a CSS Modules dependency reference, so the PHP bundler must fail before resolver or read callbacks run.

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Local NAPI reproduction against the cached native binding confirmed:
  - `.card{margin:env(--gap from "x",1rem)}` rejects with `Unexpected token Ident("from")` and reports the `from` token location.
  - A nested block containing `margin: env(--wp-card-gap from "./missing.css", 1rem);` rejects with the same parser message and does not call resolver/read hooks.
  - Escaped `fr\6fm` is decoded to `from` and receives the same parser diagnostic.
  - `var(--wp-card-gap from "pkg:tokens.css", var(--fallback-gap from "pkg:fallback.css"))` remains a dependency-capable CSS Modules reference.

## Implementation

- `CssModulesTransformer` now rejects dependency `from` syntax inside dashed-ident `env()` arguments while preserving `var()` dependency reference rewriting.
- `CssBundler` now preflights CSS Modules source for invalid `env(... from ...)` before import/dependency graph traversal, returning a `parser-error` `CssBundleException` with upstream-style source location.
- `wordpress-bundle-import-graph.php` now proves the WordPress-facing bundle path rejects `env(... from ...)` before package resolution while still resolving the equivalent `var(... from ...)` import graph.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php`
  - no syntax errors
- `php -l lanes/lightningcss/src/CssModulesTransformer.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 696 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `1 test files, 521 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7409 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - passed, including `css-modules-env-from: rejected-before-resolve` and `css-modules-var-dependency: resolved`

## Status Delta

- Full lane focused evidence moved from `7402` to `7409` passing assertions.
- `lane-status.json` keeps `phpFail` at `0`.
- Mapped upstream inventory stays at `2369 / 3532`; this slice deepens the already mapped CSS Modules bundle/import graph dependency cluster instead of adding a new manifest denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP `CssBundler`, `CssModulesTransformer`, resolver boundary, CSS identifier decoding, and source-location helpers. No Node, Rust, or WASM runtime dependency is introduced.

## Non-Overlap

This does not repeat accepted import source tokenization, nested `composes` diagnostics, resolver result shape, source-map remapping, media-query fallback, target-prefixing, CSSOM, custom at-rule, or property/value parity slices.

## Follow-Up

Upstream nested `var(... from "...")` currently returns a CSS Modules reference without resolver reads through the Node transform path. The PHP bundler does not yet expose that reference surface, so that should be handled as a separate bounded API/parity slice rather than folded into this parser diagnostic fix.
