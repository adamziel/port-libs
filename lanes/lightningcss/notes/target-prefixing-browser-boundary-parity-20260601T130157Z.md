# LightningCSS Target Prefixing Browser Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T130157Z`

## Source truth

- Upstream: `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source file: `src/prefixes.rs`, `Feature::TextDecoration`.
- Upstream encodes the WebKit text-decoration shorthand prefix range for `safari` and `ios_saf` from `8.0` through `26.1` inclusive.
- Direct native oracle with `lightningcss.linux-x64-gnu.node`:
  - `safari 26.0` and `safari 26.1` emit `-webkit-text-decoration` plus canonical `text-decoration`.
  - `safari 26.2` and `safari 27.0` emit unprefixed `text-decoration` only.
  - `ios_saf 26.0` and `ios_saf 26.1` emit `-webkit-text-decoration` plus canonical `text-decoration`.
  - `ios_saf 26.2` emits unprefixed `text-decoration` only.

## Red-first evidence

Before this patch, the PHP prefixer stopped shorthand WebKit text-decoration at major `26`, so `26.1` was already treated as unprefixed:

```text
safari 26.0 .foo{-webkit-text-decoration:underline double;text-decoration:underline double}
safari 26.1 .foo{text-decoration:double underline}
safari 26.2 .foo{text-decoration:double underline}
ios_saf 26.0 .foo{-webkit-text-decoration:underline double;text-decoration:underline double}
ios_saf 26.1 .foo{text-decoration:double underline}
ios_saf 26.2 .foo{text-decoration:double underline}
```

## Change

- Extended `TransitionPrefixer` `textDecorationNeedsWebkit` target range through `[26, 1]` for `safari` and `ios_saf`.
- Added four focused browser-boundary assertions:
  - Safari `26.1` keeps the WebKit shorthand prefix.
  - Safari `26.2` drops the WebKit shorthand prefix.
  - iOS Safari `26.1` keeps the WebKit shorthand prefix.
  - iOS Safari `26.2` drops the WebKit shorthand prefix.
- Updated the WordPress text-decoration example smoke to cover Safari/iOS `26.1` and `26.2` block-style delivery.

## Verification

```text
php -l lanes/lightningcss/src/TransitionPrefixer.php
No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php

php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php

php -l lanes/lightningcss/examples/wordpress-text-decoration-prefixer.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-text-decoration-prefixer.php

php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
1 test files, 1298 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7931 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-text-decoration-prefixer.php --self-test
exit 0, expected Safari/iOS 26.1 and 26.2 entries matched

git diff --check -- lanes/lightningcss
exit 0
```

## Non-overlap

This slice does not rework the accepted text-decoration longhand boundary slice. It only corrects the shorthand `Feature::TextDecoration` WebKit target range at the Safari/iOS `26.1` to `26.2` boundary.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP target parser, prefixer, test harness, and WordPress example path.
