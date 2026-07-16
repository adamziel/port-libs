# LightningCSS Bundle Resolution Import Graph Parity 2026-05-31T18:51Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T185132Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream read: `src/bundler.rs::add_css_module_dep()` receives both `style.loc` and the parsed dependency location. Resolver errors and `ResolveResult::External` for CSS Modules `from` references are wrapped with `style.loc`, while successful file results call `load_file()` with the dependency value location for later read failures.
- Targeted upstream read: `src/properties/css_modules.rs::Composes::parse()` stores `loc` from `input.current_source_location()` before parsing composed names, so missing dependency reads stay anchored to the authored `composes` value rather than the containing selector.

## Native Delta

- `CssBundler` now records separate CSS Modules dependency locations for resolution/external diagnostics and dependency reads.
- `composes: ... from "..."` dependencies report resolver callback failures and external module rejections at the containing style rule location, matching upstream `style.loc`.
- Missing dependency reads continue to report the existing `composes` value location, preserving the accepted diagnostic path from the earlier dependency-location slice.
- Bounded dashed-ident `var()` / `env()` dependency references use the style-rule location for both resolution and read diagnostics, matching upstream's current `style.loc` TODO boundary for variable references.
- `wordpress-bundle-import-graph.php` now smokes a block CSS Module that composes from an external stylesheet and verifies the style-rule diagnostic location.

## Evidence

- Red-first local probe before the patch: external CSS Modules `from "https://..."` in a second style rule reported `/entry.css` `1:1`.
- Red-first local probe before the patch: a resolver callback exception for `composes ... from "pkg:tokens.css"` inside `@media` reported the `composes` value at `/entry.css` `3:13` instead of the style rule at `3:3`.
- `php -l lanes/lightningcss/src/CssBundler.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 194 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 3151 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `css-modules-external-style-location: rejected`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused `CssBundlerTest.php` evidence moves from `184` to `194` assertions.
- Full LightningCSS PHP evidence moves from `3141` to `3151` assertions.
- Conservative mapped coverage remains `1696 / 3532`; this deepens the already mapped `src/bundler.rs` CSS Modules dependency graph/diagnostic cluster rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. This reuses native `CssBundler`, `CssModulesTransformer`, CSS source-location scanning helpers, existing resolver callbacks, in-memory/filesystem source providers, and the existing bundle exception model. No Node, Rust, WASM, browser service, external resolver package, parser generator, or filesystem crawler is introduced.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative resolution, filesystem source-provider reads, escaped specifier decoding, escaped URL delimiter scanning, resolver-result shape diagnostics, EOF import handling, import-prelude barriers, external `@import` ordering errors, import modifier order parsing, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, CSS Modules missing-read dependency locations, CSS Modules content-hash/project-root/file-backed import graph behavior, SourceMap import graph/VLQ/offset mechanics, CSSOM work, target prefixing, media-query validation, and custom at-rule visitor slices. The stale 2026-05-25 `CustomMediaTransformer` rework note is historical for this source path and was not touched.
