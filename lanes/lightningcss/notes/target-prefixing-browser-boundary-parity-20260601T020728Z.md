# Target Prefixing Browser Boundary Parity - Font Typography

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T020728Z`

Base accepted HEAD: `dc8bb5cac377111467dc403c9b9c75704db62cd4`

## Upstream Source Truth

Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Evidence command:

```sh
git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | nl -ba | sed -n '824,878p'
```

Relevant rows:

- `Feature::FontFeatureSettings | Feature::FontVariantLigatures | Feature::FontLanguageOverride`: WebKit for Android `4.4` through `4.4.3`, Chrome `16` through `47`, Opera `15` through `34`, and Samsung `<= 4`; Moz for Firefox `4` through `33`.
- `Feature::FontKerning`: WebKit for Android `<= 4.4`, Chrome `29` through `32`, iOS Safari `8` through `11.3`, Opera `16` through `19`, and Safari `7` through `9`.

## Native Delta

`TransitionPrefixer` now emits or removes stale vendor declarations for:

- `font-feature-settings`
- `font-variant-ligatures`
- `font-language-override`
- `font-kerning`

The focused test covers exact before/after browser boundaries and stale-prefix removal. `wordpress-font-typography-prefixer.php` models a block title display-font rule for legacy editor targets versus modern frontend targets.

Red-first probe before the implementation returned only unprefixed declarations for the legacy target cases.

## Verification

```sh
php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
# 1 test files, 903 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests
# 13 test files, 5469 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-font-typography-prefixer.php --self-test
# OK

php -l lanes/lightningcss/src/TransitionPrefixer.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-font-typography-prefixer.php
# No syntax errors detected in all changed PHP files

jq empty lanes/lightningcss/lane-status.json lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json
# OK

git diff --check -- lanes/lightningcss
# OK
```

## Status Delta

- Focused assertion delta: `+18` in `TransitionPrefixerTest.php`.
- Full lane assertion delta: `5451 -> 5469`.
- Conservative mapped coverage delta: `2297 -> 2301 / 3532`.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.

## Non-Overlap

This slice does not overlap the stale LightningCSS rework note for custom media import-tail conflicts or the latest accepted bundle/import graph, custom at-rule ratio visitor, color-mix, and cursor-prefix batch. It is limited to font typography target-prefix browser boundaries from `src/prefixes.rs`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `TransitionPrefixer`, declaration parser/serializer, and target-version helpers.
