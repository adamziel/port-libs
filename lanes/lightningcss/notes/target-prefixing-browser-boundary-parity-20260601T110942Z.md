# Target Prefixing Display Grid Boundary Parity 2026-06-01T11:09Z

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pristine upstream read: `git show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs`.
- Relevant upstream table: `Feature::DisplayGrid | Feature::InlineGrid` sets `VendorPrefix::Ms` for Edge version codes `786432..=983040` (Edge 12 through 15) and IE version codes `>= 655360` (IE 10+).

## Ported Behavior

- `TransitionPrefixer` now emits `display:-ms-grid` before `display:grid` for IE 10+, IE 11, and Edge 12-15 targets.
- `TransitionPrefixer` now emits `display:-ms-inline-grid` before `display:inline-grid` for those same target boundaries.
- Modern targets such as Edge 16 and Chrome 120 prune stale adjacent `display:-ms-grid` and `display:-ms-inline-grid` declarations when a standard display grid declaration is present.
- Important declarations and overridden prefixed-only tails keep the existing display-prefix behavior model used by flex.

## Red-First Evidence

Before the implementation, the current PHP prefixer returned no MS grid fallback and kept stale prefixed values for modern targets:

```text
.foo{display:grid}
.foo{display:-ms-grid;display:grid}
.foo{display:inline-grid}
```

## Focused Evidence

```text
php -l lanes/lightningcss/src/TransitionPrefixer.php
No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php

php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php

php -l lanes/lightningcss/examples/wordpress-grid-display-prefixer.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-grid-display-prefixer.php

php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
1 test files, 1241 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-grid-display-prefixer.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7505 assertions, 0 failures

php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "JSON OK\n";'
JSON OK

git diff --check -- lanes/lightningcss
exit 0
```

## Manifest/Status

- `lane-status.json` updated from `7496` to `7505` passing PHP assertions based on the full focused lane run.
- Conservative mapped coverage remains `2369 / 3532`; this slice deepens the existing target-prefix generated-prefix-table cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP `TransitionPrefixer` target-version routing, declaration scanner/serializer, rule merge behavior, `CssMinifier` output path, and lane-local example harness.

## Non-Overlap

This slice avoids the accepted text-spacing, overscroll, image-rendering, writing-mode, unicode-bidi, object-fit, mask/clip-path, stale selector-prefix, CSSOM, bundle/import graph, CSS Modules, source-map, custom at-rule, media-query, and advanced color/property-value slices. The only production behavior changed here is target-prefix fallback insertion and pruning for `display:grid` and `display:inline-grid`.

## Next Task

Continue target-prefixing parity with non-overlapping grid longhand `-ms-` fallback edges or other generated-prefix-table properties with direct upstream source evidence.
