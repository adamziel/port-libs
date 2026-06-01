# LightningCSS bundle resolution import graph parity - 2026-06-01T155425Z

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T155425Z`
- Accepted base: `57d8e6e255e0f04075a11bb6231bd0b9bffc3ac4`
- Upstream source truth: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Behavior

Pinned upstream `src/bundler.rs::test_bundle` includes a custom source provider that only resolves specifiers with a `foo:` prefix. The PHP parity test now covers both halves of that behavior:

- `@import "foo:/b.css"` calls the custom resolver with the importing file and inlines the stripped path.
- `@import "/b.css"` is rejected by the resolver and reports the diagnostic at the original import location.

This deepens the existing bundle/import graph mapping without claiming a new manifest denominator row, because the source behavior is already part of the mapped `src/bundler.rs::test_bundle` cluster.

## WordPress Smoke

`examples/wordpress-bundle-import-graph.php` now includes a block-theme package resolver smoke that accepts `wp:`-prefixed imports and rejects non-prefixed block package imports with the original `@import` source location.

## Verification

- Pre-edit focused baseline: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 809 assertions, 0 failures`
- `php -l lanes/lightningcss/tests/CssBundlerTest.php && php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 817 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> emitted `custom-prefix-resolver: resolved` and `custom-prefix-resolver: rejected`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8558 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` -> passed
- Root harness: not run - isolated micro-slice

## Status Delta

- Focused `CssBundlerTest.php` moved `809 -> 817` assertions.
- Full LightningCSS lane moved `8550 -> 8558` assertions.
- Manifest mapped coverage remains `2398 / 3532`; this is a same-cluster upstream parity deepening.

## Dependency Closure

No new support component is needed. The existing native PHP `CssBundler` resolver callback path is reused.

## Non-Overlap

This avoids the accepted source-map offset, duplicate fragment, CSS Modules, CSSOM read/write, custom media, layer merge, and parser recovery clusters. It is specifically scoped to upstream custom source provider import resolution policy and resolver-error location preservation.
