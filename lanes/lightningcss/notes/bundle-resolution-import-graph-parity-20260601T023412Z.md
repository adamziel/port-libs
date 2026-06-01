# Bundle Resolver Error Location Parity - 2026-06-01T023412Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T023412Z`

Upstream source truth: pinned LightningCSS commit `22bdda3d190f1cd321d98026225cfc964af64ad9`. The upstream Node bundler `resolve throw` case in `node/test/bundle.test.mjs` reports the throwing resolver message and the source file/location of the `@import` that requested the failed specifier.

Implemented behavior:

- Added focused `CssBundlerTest` coverage for a nested import graph where the entry stylesheet imports a block stylesheet and the block stylesheet's package resolver throws.
- Verified the exception remains a `resolver-error`, preserves the upstream resolver message, and reports the imported block stylesheet plus the exact `@import` line/column rather than the entry stylesheet.
- Updated the WordPress bundle import-graph smoke with the same nested package resolver failure path.

Verification:

- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - passed
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - passed
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - passed, 1 file / 492 assertions / 0 failures
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - passed
- `php tools/run-tests.php lanes/lightningcss/tests` - passed, 13 files / 5527 assertions / 0 failures
- `git diff --check -- lanes/lightningcss` - passed

Status delta:

- Native PHP pass evidence: 5521 -> 5527 assertions in the full LightningCSS lane
- Conservative mapped coverage remains 2303 / 3532 because this deepens the existing bundle resolver diagnostic cluster.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.

Dependency closure:

- No new support component is needed. This reuses the native PHP `CssBundler` resolver and `CssBundleException` diagnostic path.

Non-overlap:

- This avoids the stale CustomMediaTransformer import-tail rework note and does not touch source-map skipped-index handling, source-map comment-boundary parsing, CSS Modules dependency graphs, media range import dedupe, CSSOM, target-prefixing, custom at-rule token visitors, or property-value fallback slices.
