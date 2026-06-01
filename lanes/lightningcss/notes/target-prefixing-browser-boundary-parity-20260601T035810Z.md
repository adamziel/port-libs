# Target Prefixing Browser Boundary Parity - Appearance

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T035810Z`

Base accepted HEAD: `bf75a27f708d456a2f08c9c540bce1189ab451a6`

## Upstream Source Truth

Pinned manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Targeted upstream read:

```sh
sed -n '1740,1795p' /home/claude/port-libs/.upstream-cache/lightningcss/src/prefixes.rs
```

`Feature::Appearance` keeps WebKit appearance prefixes for Safari and iOS Safari from `3.1`/`3.2` through `15.2`, then drops the WebKit prefix at `15.3`.

Red-first focused check before the implementation:

```sh
php -r 'require "tools/bootstrap.php"; $p=new PortLibs\LightningCSS\TransitionPrefixer(); echo $p->prefixForTargets(".foo { appearance: none; }", ["safari"=>"15.2"]), "\n"; echo $p->prefixForTargets(".foo { appearance: none; }", ["ios_saf"=>"15.2"]), "\n";'
```

Both Safari 15.2 and iOS Safari 15.2 serialized as `.foo{appearance:none}`, omitting the upstream-required `-webkit-appearance` fallback.

## Implementation

- `TransitionPrefixer::targetOptions()` now treats Safari `15.1`/`15.2` and iOS Safari `3.2`/`15.1`/`15.2` as requiring `-webkit-appearance`.
- `TransitionPrefixerTest.php` adds exact Safari `15.2`/`15.3` and iOS Safari `15.2`/`15.3` browser-boundary assertions.
- `wordpress-target-prefix-boundaries.php` now covers the Safari 15.2/15.3 WordPress block navigation appearance boundary in the existing target-prefix smoke.

## Verification

```sh
php -l lanes/lightningcss/src/TransitionPrefixer.php
php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
php -l lanes/lightningcss/examples/wordpress-target-prefix-boundaries.php
php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
php lanes/lightningcss/examples/wordpress-target-prefix-boundaries.php --self-test
php tools/run-tests.php lanes/lightningcss/tests
php -r 'foreach (["lanes/lightningcss/lane-status.json", "lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'
```

Results:

- PHP lint passed for all changed PHP files.
- Focused `TransitionPrefixerTest.php`: `1 test files, 966 assertions, 0 failures`.
- WordPress target-prefix example self-test passed.
- Full LightningCSS lane: `13 test files, 5861 assertions, 0 failures`.
- JSON status/manifest decode passed.

## Coverage And Handoff Notes

`phpPass` moves from `5855` to `5861`. Conservative mapped coverage remains `2320 / 3532` because this deepens the already represented UI target-prefix browser-boundary cluster rather than adding a new upstream helper denominator row.

Dependency closure: no new support component is needed. This reuses the existing native PHP browser-target table, declaration prefixer, minifier, and example harness.

Non-overlap: the stale May 25 `CustomMediaTransformer` rework note targets an older import-tail conflict and is unrelated to this target-prefixing slice. This patch avoids the recently accepted bundle/import graph, CSS Modules, CSSOM UI direct enum, custom at-rule traversal, case-sensitive media range, advanced color fallback cleanup, fullscreen selector, Android transition, cursor, print-color-adjust, placeholder, animation timeline, and property-value clusters.
