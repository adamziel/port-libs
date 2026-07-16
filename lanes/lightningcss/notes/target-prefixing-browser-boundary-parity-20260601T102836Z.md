# Target Prefixing Image Rendering Boundary Parity 2026-06-01T10:28Z

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pristine upstream read: `git show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs`.
- Relevant upstream table:
  - `Feature::Pixelated`: Firefox `3.6` through `64` uses `-moz-`, iOS Safari `5` through `6` uses `-webkit-`, Opera `11.6` through `12.1` uses `-o-`, and Safari `<= 6` uses `-webkit-`.
  - `Feature::ImageRendering`: IE `>= 7` uses `-ms-`.

## Ported Behavior

- `TransitionPrefixer` now emits `image-rendering: pixelated` fallbacks at those browser boundaries:
  - `-ms-interpolation-mode: nearest-neighbor`
  - `image-rendering: -webkit-optimize-contrast`
  - `image-rendering: -moz-crisp-edges`
  - `image-rendering: -o-pixelated`
- Modern targets prune stale prefixed fallbacks when the unprefixed `pixelated` declaration is present.
- Existing needed fallbacks are preserved without duplication.
- `@supports (image-rendering: pixelated)` now gains needed prefixed declaration alternatives, and stale prefixed `or` branches are removed for modern targets.

## Red-First Evidence

Before the implementation, the current PHP prefixer returned no fallback for the pinned boundaries:

```text
safari6: .foo{image-rendering:pixelated}
firefox64: .foo{image-rendering:pixelated}
ie11: .foo{image-rendering:pixelated}
firefox65 stale: .foo{image-rendering:-moz-crisp-edges;image-rendering:pixelated}
```

## Focused Evidence

```text
php -l lanes/lightningcss/src/TransitionPrefixer.php
No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php

php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php

php -l lanes/lightningcss/examples/wordpress-image-rendering-prefixer.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-image-rendering-prefixer.php

php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
1 test files, 1230 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-image-rendering-prefixer.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7417 assertions, 0 failures

php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "JSON OK\n";'
JSON OK

git diff --check -- lanes/lightningcss
exit 0
```

## Manifest/Status

- `lane-status.json` updated from `7402` to `7417` passing PHP assertions based on the full focused lane run.
- Conservative mapped coverage remains `2369 / 3532`; this slice deepens the existing target-prefix/generated-prefix-table cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP `TransitionPrefixer` target-version routing, declaration scanner/serializer, `@supports` declaration condition scanner, `CssMinifier` output path, and lane-local example harness.

## Non-Overlap

This slice avoids the accepted text-spacing, overscroll, writing-mode, unicode-bidi, object-fit, mask/clip-path, stale selector-prefix, CSSOM image-rendering read/write, bundle/import graph, CSS Modules, source-map, custom at-rule, media-query, and advanced color/property-value slices. The only production behavior changed here is target-prefix fallback insertion/pruning for `image-rendering: pixelated` declarations and matching `@supports` conditions.

## Next Task

Continue target-prefixing parity with non-overlapping generated-prefix-table boundaries, especially grid `-ms-` behavior or remaining image/property fallback edges with direct upstream source evidence.
