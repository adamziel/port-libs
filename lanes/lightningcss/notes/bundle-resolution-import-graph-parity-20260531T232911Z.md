# Bundle Resolution Import Graph Parity - 2026-05-31

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T232911Z`

Base accepted HEAD: `a364d07040190b68b467cd69fb969339b783a7fe`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/parser.rs` parses `@import` sources with `input.expect_url_or_string()`, so CSS comments and whitespace around a `url(...)` token are parser trivia and not part of the resolved import string.
- `src/bundler.rs::load_file()` resolves the parsed `ImportRule.url` through the `SourceProvider` before recursive dependency loading, so the resolver/read graph sees the clean specifier.
- `node/test/bundle.test.mjs` exercises resolver/read boundaries and dependency-before-importer ordering for bundle graphs.

## Native Delta

- `CssBundler::parseImportUrlFunctionSource()` now trims leading and trailing CSS whitespace/comments around unquoted `url(...)` import sources before validation and resolver dispatch.
- Escaped URL text such as `\/` remains part of the specifier and is decoded after validation.
- Interior unescaped comments inside an unquoted URL token are rejected as `Invalid @import source` before the resolver reads dependencies.
- `wordpress-bundle-import-graph.php` now covers the WordPress-facing layered import graph path for unquoted `url(...)` comments.

## Red-First Evidence

Before the patch, the focused probe failed before graph resolution:

```text
PortLibs\LightningCSS\CssBundleException: Invalid @import source
```

Probe CSS:

```css
@import url( /* build */ blocks/card.css /* tail */ ) screen;
.entry { color: red }
```

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 375 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 4887 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` -> exits 0 and prints `unquoted-url-import-trivia: resolved`.
- `git diff --check -- lanes/lightningcss` -> clean.

Focused `CssBundlerTest.php` moved from 365 accepted assertions to 375 local assertions. Full lane PHP evidence is 13 files / 4887 assertions / 0 failures.

## Status Delta

- `lane-status.json` `phpPass`: `4821` -> `4887`, matching the verified current full-lane run.
- Conservative mapped coverage remains `2198 / 3532` because this deepens the already represented bundle/import graph cluster.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `CssBundler`, existing CSS escape/comment scanner helpers, resolver/read callbacks, minifier output, and `CssBundleException` diagnostics. No Node, Rust, WASM, network, or live-service dependency is introduced.

## Non-Overlap

This slice avoids already accepted escaped import specifier decoding, quoted `url()` import parsing, CRLF hex-escaped import sources, escaped `@import` at-keyword parsing, import layer-name validation, media/supports/layer composition, custom media import-tail behavior, source-map remapping, CSS Modules dependency graph work, CSSOM declaration behavior, custom at-rule visitors, and target-prefix/property-value clusters. The stale custom-media import-tail rework note is historical for this base and was not reimplemented here.
