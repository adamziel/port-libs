# LightningCSS Bundle CSS Modules Dependency Diagnostic Location Parity

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T181455Z`

Base accepted HEAD: `f239ae84229f0ac8ecc07e38ef32523b43f8024f`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/css_modules.rs` stores a `Location` on parsed `Composes` values.
- `src/bundler.rs` passes that stored CSS Modules dependency location through `add_css_module_dep()` into `load_file()`, so missing dependency reads are reported at the authored dependency declaration location rather than the default entry location.

## Implemented Behavior

`CssBundler` now records CSS Modules dependency specifier locations before CSS Modules transformation. For `composes: ... from "..."` and bounded dashed-ident `var()` / `env()` from-values, dependency resolution and dependency file reads receive the authored source location.

The focused red/green behavior is a missing `composes-from` dependency in `/entry.css`: the old local probe reported the read diagnostic at `/entry.css` `1:1`; the new focused assertion verifies `/entry.css` `6:13`, matching the authored `composes` value location.

The WordPress bundle smoke now covers the same user-visible path for a block stylesheet with a missing shared CSS Modules token file.

## Non-Overlap

This slice does not alter accepted @import ordering, supports/media/layer wrapping, reader/file-backed SourceProvider behavior, escaped import specifier decoding, EOF imports, URL import modifiers, external import diagnostics, custom media import-tail substitution, source-map import graph handling, CSSOM read/write behavior, target-prefixing, media-query validation, or custom at-rule visitors.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP `CssBundler`, `CssModulesTransformer` output, existing source-location helpers, CSS string-token decoding, and existing in-memory/filesystem source providers. No Node, Rust, WASM, parser generator, or external service dependency is introduced.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php && php -l lanes/lightningcss/tests/CssBundlerTest.php && php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - `No syntax errors detected` for all three files.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 166 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 2928 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - exit 0, including `css-modules-missing-dependency-location: rejected`.
- `git diff --check -- lanes/lightningcss`
  - clean.

Root harness: not run - isolated micro-slice.

## Follow-Up

Broader parser-grade CSS Modules location parity remains open for every possible value form and malformed input recovery. This slice only claims the bounded upstream-backed `composes-from` missing dependency diagnostic path plus scanner support for dashed-ident `var()` / `env()` dependency references.
