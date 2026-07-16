# LightningCSS Backdrop-Filter Transition Target Prefix Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T185034Z`
Base accepted HEAD: `0c0eec061390da3a2185ec8623476b5865dd4a49`

## Source Truth

- Upstream `parcel-bundler/lightningcss` commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pristine source reads used `git show` from the pinned commit because the shared upstream cache has local edits.
- `src/lib.rs::test_transition` has `prefix_test` helper cases for both `transition-property` and `transition` where Safari 15 expands `backdrop-filter` transition names to `-webkit-backdrop-filter, backdrop-filter` and preserves prefixed-only authored names.
- `src/prefixes.rs::Feature::BackdropFilter` keeps the WebKit prefix through Safari/iOS 17.6 and does not require it for Safari 18+.

## Implementation

- `TransitionPrefixer::prefixedTransitionPropertyExpansion()` now expands `backdrop-filter` transition-property names through the same target boundary used by direct `backdrop-filter` declarations.
- Safari/iOS targets through 17.6 emit `-webkit-backdrop-filter, backdrop-filter` for unprefixed authored transition names.
- Safari 18+ targets drop stale `-webkit-backdrop-filter` transition names back to `backdrop-filter`.
- The WordPress target-boundary example now includes a `transition-property: backdrop-filter` smoke so user-visible output covers declaration and transition-name prefixing together.

## Red-First Evidence

Before the implementation, the new focused test failed on the first Safari 17.6 transition-property assertion:

```text
Failed asserting that two strings are equal.
--- Expected
+++ Actual
@@ @@
-'.foo{transition-property:-webkit-backdrop-filter,backdrop-filter}'
+'.foo{transition-property:backdrop-filter}'

1 test files, 477 assertions, 1 failures
```

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`: `1 test files, 484 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 3149 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/TransitionPrefixer.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-target-prefix-boundaries.php`: no syntax errors.
- `php -r 'foreach (["lanes/lightningcss/lane-status.json", "lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`: both JSON files decode successfully.
- `php lanes/lightningcss/examples/wordpress-target-prefix-boundaries.php`: exits 0 with the updated Safari 17.6 transition-property prefix boundary output.
- `git diff --check -- lanes/lightningcss`: clean.

## Status Delta

- Focused PHP assertion count: `476 -> 484` for `TransitionPrefixerTest.php`, `+8`.
- Lane `phpPass`: `3141 -> 3149`.
- Conservative mapped upstream coverage: `1696 / 3532 -> 1698 / 3532`.
- Newly mapped upstream helpers: 2 `src/lib.rs::test_transition` `prefix_test` cases for `transition-property` and `transition` backdrop-filter name prefixing.

## Non-Overlap

This slice does not repeat accepted direct `backdrop-filter` declaration prefixing, `@supports` prefix handling, mask/clip-path target boundaries, text-decoration-thickness prefix boundaries, display/flex prefixing, or source-map/CSS Modules/custom-at-rule/media-query work. It only adds the missing transition-name boundary behavior for the existing backdrop-filter target logic.

## Dependency Closure

No new support component is needed. The slice reuses existing target option resolution and transition-property name expansion inside the native PHP LightningCSS lane.
