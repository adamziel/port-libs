# LightningCSS Bundle Resolver Result Shape Parity 2026-05-31T15:30Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T153025Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `node/test/bundle.test.mjs` `resolve return non-string` expects malformed resolver results to throw `data did not match any variant of untagged enum ResolveResult` at the originating import location.
  - `napi/src/lib.rs` parses custom resolver returns through `Env::from_js_value::<ResolveResult>`, so non-string file/external resolver values are rejected before reaching the bundle graph.
  - `src/bundler.rs` wraps resolver errors in bundle diagnostics with the import rule source location.

## Native Delta

- `CssBundler::resolveImport()` now requires custom resolver results to be either a string file path or an array with a string `file` or `external` value.
- Scalar resolver returns, arrays without a supported shape, and non-string `file` / `external` values now throw `CssBundleException` kind `resolver-error` with the upstream Node ResolveResult message and the importing source location.
- `wordpress-bundle-import-graph.php` now smokes a malformed block-theme resolver result and verifies the same diagnostic without Node.

## Evidence

- Red-first focused run after adding the assertions: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 75 assertions, 1 failures`; failure showed the stale message `Resolver returned an unsupported value`.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 88 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1933 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `resolver-shape: rejected`.
- PHP lint: `php -l lanes/lightningcss/src/CssBundler.php`, `php -l lanes/lightningcss/tests/CssBundlerTest.php`, and `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` all passed.
- Lane metadata JSON decode passed for `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json`.
- `git diff --check -- lanes/lightningcss` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `1918` to `1933` assertions.
- Conservative mapped coverage: `1311 / 3532` to `1312 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the lane-local native `CssBundler`, resolver callback boundary, in-memory file map, and existing bundle exception model; no Node, Rust, browser service, parser generator, or external resolver library is introduced.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative resolution, import prelude diagnostics, external import ordering, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, CSS Modules composes/dashed-ident dependency graphs, missing-export behavior, source-map offsets, CSSOM shorthand work, target prefixing, media-range, and custom at-rule visitor slices.
