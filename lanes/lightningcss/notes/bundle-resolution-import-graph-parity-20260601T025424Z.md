# LightningCSS Bundle Import Graph Parity - Escaped CSS Modules `from`

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T025424Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/css_modules.rs` parses CSS Modules `composes ... from` with `expect_ident_matching("from")`, so escaped identifier spellings such as `fr\6fm` are accepted.
- `src/values/ident.rs` parses dashed-ident references with the same `expect_ident_matching("from")` behavior.
- `src/bundler.rs` reports CSS Modules dependency read/resolve diagnostics using the composes value or style-rule source location rather than a fallback `1:1` location.

## Native Delta

- `CssBundler` now scans decoded CSS identifier tokens for CSS Modules dependency-location collection, instead of literal regex matches for `composes`, `from`, `var`, and `env`.
- `CssModulesTransformer` now decodes escaped `from` and `global` keywords in dashed-ident `var()` / `env()` references before deciding whether to create dependency references.
- Added focused bundle tests for escaped `composes`, escaped `from`, and escaped dashed-ident `from` diagnostic locations.
- Updated `wordpress-bundle-import-graph.php` with a CSS Modules smoke proving escaped dependency syntax still reports resolver errors at the upstream style location.

## Red-First Evidence

Before this patch, this probe resolved the dependency but reported the missing file at `/entry.css:1:1`:

```sh
php -r 'require "tools/bootstrap.php"; try { (new \PortLibs\LightningCSS\CssBundler())->bundleCssModules("/entry.css", ["/entry.css" => ".intro { color: red; }\n\n.card {\n  composes: token fr\\6fm \"./missing.css\";\n  color: blue;\n}\n"]); } catch (\PortLibs\LightningCSS\CssBundleException $e) { var_export([$e->kind, $e->getMessage(), $e->sourceFile, $e->sourceLine, $e->sourceColumn]); }'
```

The same behavior is now covered by `CssBundlerTest.php` with expected read and resolver locations.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` - passed.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` - passed.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - passed.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 512 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 5684 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - exited 0 and printed `css-modules-escaped-from-location: rejected`.

## Coverage And Closure

- Focused assertion growth: `CssBundlerTest.php` moved from 497 to 512 assertions (`+15`).
- Full lane assertion growth: latest local full lane evidence is 5684 assertions.
- Mapped denominator remains `2314 / 3532`; this deepens the already represented CSS Modules bundle/import graph dependency-location cluster.
- Dependency closure: no new support component is needed. The slice reuses the native CSS token scanner, CSS Modules transformer, and bundler resolver/read path.

## Non-Overlap

This avoids the accepted source-map raw VLQ table-dedupe slice, import-layer validation, visible/hidden JSON-style constraint analogs from unrelated lanes, target-prefixing, CSSOM, and property-value work. Remaining bundle/import graph follow-up should target a different upstream-backed behavior, such as remaining CSS Modules parser edge cases or source-map graph propagation not already represented.
