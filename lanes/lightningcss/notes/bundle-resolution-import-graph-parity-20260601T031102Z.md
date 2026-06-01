# Bundle SourceProvider Object Diagnostic Parity - 2026-06-01T03:11Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T031102Z`

Source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/test/bundle.test.mjs` covers `read return non-string` and expects `expect String, got: Number` from the NAPI SourceProvider boundary.
- `napi/src/lib.rs` converts resolver `read()` output with `JsString::try_from(...)`, so object-like JavaScript values are reported by JS/NAPI type names rather than implementation class names.

Implemented behavior:

- `CssBundler::readerTypeName()` now maps PHP object returns from reader callbacks to upstream-style `Object`.
- Added import-graph coverage where `foo.css` imports `bar.css`, the imported reader returns an object, and the resulting `resolver-error` reports `foo.css` line 1 column 1.
- Updated the WordPress bundle import-graph example to smoke the same imported block stylesheet diagnostic without Node/WASM.

Evidence:

- Red-first focused check after adding the assertion: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` failed with expected `Object` and actual `stdClass`.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 502 assertions, 0 failures`.
- Full lane after fix: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 5719 assertions, 0 failures`.
- PHP lint passed for `lanes/lightningcss/src/CssBundler.php`, `lanes/lightningcss/tests/CssBundlerTest.php`, and `lanes/lightningcss/examples/wordpress-bundle-import-graph.php`.
- Example smoke passed: `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`, including `reader-object-diagnostic: rejected`.
- JSON validation passed for `lanes/lightningcss/lane-status.json` and `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/lightningcss` passed.

Status delta:

- Native PHP full-lane evidence moves `5714 -> 5719` assertions.
- Conservative mapped coverage remains `2315 / 3532` because this deepens the represented bundle SourceProvider diagnostic cluster.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.

Dependency closure:

- No new support component is needed. This reuses the lane-local native `CssBundler`, reader callback boundary, and `CssBundleException` import-location diagnostics.

Non-overlap:

- This avoids the stale CustomMediaTransformer rework note and does not touch source-map VLQ behavior, CSS Modules import/dependency graphs, nested import rejection, import condition validation, repeated import merging, media-query, CSSOM, custom at-rule visitor, property-value, or target-prefixing slices.
