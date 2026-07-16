# Target Prefixing Browser Boundary Parity - Safari Intrinsic Sizing

## Scope

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T114630Z`

This slice fixes the WebKit intrinsic sizing keyword lower browser boundary for Safari. Upstream LightningCSS at pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9` starts the Safari WebKit prefix window for:

- `Feature::MaxContent | Feature::MinContent` at encoded version `393472` (`6.1.0`) through `655616` (`10.1.0`).
- `Feature::FitContent` at encoded version `393472` (`6.1.0`) through `655616` (`10.1.0`).
- `Feature::Stretch` at encoded version `393472` (`6.1.0`) and later.

Source truth:

- `/home/claude/port-libs/.upstream-cache/lightningcss/src/prefixes.rs`, `Feature::MaxContent | Feature::MinContent`
- `/home/claude/port-libs/.upstream-cache/lightningcss/src/prefixes.rs`, `Feature::FitContent`
- `/home/claude/port-libs/.upstream-cache/lightningcss/src/prefixes.rs`, `Feature::Stretch`

Local native oracle spot-check:

- Safari 6.0: `.foo{width:min-content;min-width:fit-content;height:stretch}`
- Safari 6.1: `.foo{width:-webkit-min-content;width:min-content;min-width:-webkit-fit-content;min-width:fit-content;height:-webkit-fill-available;height:stretch}`

## Implementation

- Changed `TransitionPrefixer::targetOptions()` so `sizingMinMaxNeedsWebkit`, the derived `sizingFitContentNeedsWebkit`, and `sizingStretchNeedsWebkit` start at Safari `6.1` instead of Safari `6.0`.
- Added focused assertions for Safari 6.0 versus 6.1 across `min-content`, `max-content`, `fit-content`, and `stretch`.
- Extended `wordpress-intrinsic-size-prefixer.php --self-test` with Safari 6.0 and Safari 6.1 WordPress block layout outputs.

## Verification

```text
php -l lanes/lightningcss/src/TransitionPrefixer.php
No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php

php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php

php -l lanes/lightningcss/examples/wordpress-intrinsic-size-prefixer.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-intrinsic-size-prefixer.php

php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
1 test files, 1254 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-intrinsic-size-prefixer.php --self-test
OK

git diff --check -- lanes/lightningcss
No output.

php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json OK\n";'
lane-status json OK
```

## Status Delta

- Added 8 focused PHP assertions.
- Expected lane PHP assertion total moves from `7615` to `7623` once the full lane is rerun by the integrator.
- Root harness: not run - isolated micro-slice.
- Full upstream Rust/Node/WASM runners: not run.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP target version encoder, target range helper, declaration prefixer, CSS minifier, and WordPress intrinsic sizing example.

## Non-Overlap

This slice does not repeat accepted display grid MS prefixing, image-rendering pixelated, text spacing/overscroll, unicode-bidi, writing-mode, selector stale-prefix pruning, flex/display, mask, filter/backdrop-filter, background-clip, clip-path, print-color-adjust, UI, keyframes, or grid property-value work. It intentionally avoided adding `-ms-grid-*` longhand fallbacks because the pinned native oracle preserves modern grid longhands without emitting MS grid longhand declarations.

## Next Task

Continue target-prefixing boundary parity with another upstream-backed value/property range, preferably one that has a concrete native oracle mismatch and does not overlap the recently accepted target-prefix clusters.
