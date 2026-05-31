# CSS Modules Content-Hash Compose Parity

- Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T161425Z`.
- Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files: `src/css_modules.rs::Pattern`, `src/css_modules.rs::hash`, `src/lib.rs::test_css_modules`, and `src/bundler.rs::test_css_module`.

## Behavior

- `CssModulesTransformer` now computes Rust-compatible CSS Modules hashes using the same string hashing shape as upstream `DefaultHasher`/SipHasher13, including the Rust string delimiter byte and LightningCSS's custom base64 alphabet.
- `[hash]`, `[content-hash]`, `[local]`, and `[name]` pattern segments are substituted natively.
- Filename hashes are stable relative to `projectRoot`, matching upstream vectors such as `test.css => EgL3uq` and `baz/test.css => xLEkNW`.
- `[content-hash]-[local]` composes exports now match the upstream `_5h2kwG-test` fixture, and bundled imports preserve separate content hashes for dependency and entry files (`do5n2W-a` before `pP97eq-a`).
- `wordpress-css-modules-transformer.php` now smokes a content-hashed block module class with dependency `composes` metadata.

## Evidence

- Red-first spot-check before implementation: `[content-hash]-[local]` emitted `.\[content-hash\]-test` and exported `[content-hash]-test`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/tests/CssBundlerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` passed: `1 test files, 106 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` passed: `1 test files, 107 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` passed: `OK`.
- Full lane verification: `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 2103 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence moves from `2092` to `2103 pass / 0 fail`.
- Conservative mapped coverage moves from `1349` to `1352 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the native CSS Modules transformer, bundler import graph, minifier, path normalization, and an in-lane PHP implementation of the upstream CSS Modules string hash.

## Non-Overlap

This avoids accepted SourceProvider reads, escaped identifier composes handling, pure-mode selector boundaries, functional `:local()` composes rejection, dashed-ident bundle graphs, missing dependency export flattening, view-transition scoping, env/var custom at-rule visitors, text-decoration CSSOM, unknown media ranges, and SourceMap project-root normalization.
