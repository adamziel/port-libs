# LightningCSS Target Prefixing: Android Transition Boundaries

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Evidence command: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | sed -n '250,405p'`.
- Upstream `Feature::Transition`, `TransitionProperty`, `TransitionDuration`, `TransitionDelay`, and `TransitionTimingFunction` use WebKit prefixing for Android browser versions from `2.1` through `4.2`.
- The PHP target table previously stopped the Android transition WebKit range at `4.0.4`.

Red-first probe before the change:

```sh
php -r 'require "tools/bootstrap.php"; $p=new PortLibs\LightningCSS\TransitionPrefixer(); echo $p->prefixForTargets(".foo { transition: opacity 200ms; }", ["android"=>"4.2"]), PHP_EOL; echo $p->prefixForTargets(".foo { transition: opacity 200ms; }", ["android"=>"4.3"]), PHP_EOL;'
```

Output before the change:

```text
.foo{transition:opacity .2s}
.foo{transition:opacity .2s}
```

## Change

- `TransitionPrefixer` now keeps Android `4.2` inside the WebKit transition range and leaves Android `4.3` modern/unprefixed.
- Focused assertions cover both `transition` shorthand and `transition-property` at the Android `4.2` / `4.3` boundary.
- The WordPress transition target-prefixer example now exercises the Android boundary alongside Safari, Firefox, Opera, and modern Chrome targets.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-transition-target-prefixer.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`: `1 test files, 934 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 5718 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-transition-target-prefixer.php --self-test`: passed with exit code 0.

`git diff --check -- lanes/lightningcss` is recorded in the handoff final verification.

## Non-Overlap

This slice only changes the Android transition target-prefix browser boundary. It avoids the accepted CSSOM direct enum, print-color-adjust, media range layer, keyframes, animation timeline, selector, source-map, CSS Modules, bundle/import graph, and custom at-rule clusters. The stale May 25 custom-media rework note targets a different file and base and was not replayed here.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `TransitionPrefixer` target table and focused PHP harness. Full upstream Rust, Node, and WASM runners were not executed for this isolated micro-slice.
