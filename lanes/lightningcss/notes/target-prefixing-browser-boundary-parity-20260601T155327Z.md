# LightningCSS target-prefixing browser-boundary parity - Android selector boundaries

## Source truth

- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/prefixes.rs` at the pinned commit maps `Feature::AnyPseudo` to a WebKit prefix for Android 4.4 through Android 87.
- `src/compat.rs` at the pinned commit treats `Feature::DirSelector` as unsupported for Android below 145 and Samsung Internet below 25.
- Native binding probes matched those boundaries:
  - Android 4.3 keeps `:is()` native, Android 4.4 and 87 emit `:-webkit-any()` plus native `:is()`, and Android 88 keeps `:is()` native.
  - Android 4.4 lowers `:dir(rtl)` through the WebKit `:-webkit-any(:lang(...))` selector plus native `:is(:lang(...))`.
  - Android 144 and Samsung 24 lower `:dir(rtl)` to the native `:is(:lang(...))` language fallback.
  - Android 145 and Samsung 25 keep native `:dir(rtl)`.

## Patch

- Added Android 4.4-87 to the PHP AnyPseudo WebKit prefix browser boundary.
- Extended the Android `:dir()` language-fallback boundary through Android 144 and added Samsung Internet 0-24.
- Added focused `TransitionPrefixerTest` assertions for Android 4.3/4.4/87/88 `:is()` behavior and Android 4.4/144/145 plus Samsung 24/25 `:dir()` behavior.
- Extended `wordpress-selector-target-prefixer.php` with Android and Samsung selector-delivery paths for WordPress navigation and comment-author RTL selectors.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` - pass
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-selector-target-prefixer.php` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` - `1 test files, 1377 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-selector-target-prefixer.php --self-test` - pass
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 8559 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` - pass

Full upstream Rust/Node/WASM runners were not run for this isolated micro-slice.

## Non-overlap

This slice is limited to Android AnyPseudo browser boundaries and Android/Samsung `:dir()` language-fallback boundaries in selector target prefixing. It does not touch accepted CSS Regions, pseudo-selector, placeholder, logical fallback, media-query, property-value, CSSOM, source-map, bundle/import graph, or CSS Modules clusters.

## Dependency closure

No new support component is needed. The existing PHP target encoder, selector serializer, and language-fallback helpers are reused.
