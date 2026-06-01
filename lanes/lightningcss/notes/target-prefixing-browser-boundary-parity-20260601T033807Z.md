# LightningCSS Target Prefixing: Text Compatibility Lower Bound Boundaries

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Evidence command: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | sed -n '900,1280p'`.
- Upstream `Feature::Hyphens` starts the iOS Safari WebKit prefix range at encoded version `262656`, which is iOS Safari `4.2`, and keeps it through `16.5`.
- Upstream `Feature::TabSize` starts the Opera prefix range at encoded version `656896`, which is Opera `10.6`, and keeps it through `12.1`.
- The PHP target table previously started those ranges at iOS Safari `4.1` and Opera `10.0`.

Red-first probe before the change:

```sh
php -r 'require "tools/bootstrap.php"; $p=new PortLibs\LightningCSS\TransitionPrefixer(); foreach (["10.5","10.6"] as $v) { echo "opera $v: ", $p->prefixForTargets(".foo { tab-size: 4; }", ["opera"=>$v]), PHP_EOL; } foreach (["4.1","4.2"] as $v) { echo "ios $v: ", $p->prefixForTargets(".foo { hyphens: manual; }", ["ios_saf"=>$v]), PHP_EOL; }'
```

Observed mismatch before the change:

```text
opera 10.5: .foo{-o-tab-size:4;tab-size:4}
opera 10.6: .foo{-o-tab-size:4;tab-size:4}
ios 4.1: .foo{-webkit-hyphens:manual;hyphens:manual}
ios 4.2: .foo{-webkit-hyphens:manual;hyphens:manual}
```

## Change

- `TransitionPrefixer` now leaves iOS Safari `4.1` hyphen declarations unprefixed while preserving the WebKit prefix from `4.2` through the existing upper boundary.
- `TransitionPrefixer` now leaves Opera `10.5` `tab-size` declarations unprefixed while preserving the `-o-` prefix from `10.6` through `12.1`.
- Focused assertions cover both lower-bound pairs in the existing legacy text browser-boundary test.
- `wordpress-text-compat-prefixer.php` now exercises these lower-bound edges for block-editor typography CSS.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`: `1 test files, 951 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-text-compat-prefixer.php --self-test`: passed with exit code 0.
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 5797 assertions, 0 failures`.

`php -l` and `git diff --check -- lanes/lightningcss` are recorded in the handoff final verification.

## Non-Overlap

This slice only changes text-compat target-prefix lower-bound ranges for iOS Safari hyphens and Opera tab-size. It avoids the accepted Android transition, print-color-adjust, transform, selector, mask, image-set, text-decoration, media-query, source-map, CSS Modules, bundle/import graph, CSSOM, and custom at-rule clusters. The stale May 25 custom-media rework note targets a different file and base and was not replayed here.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `TransitionPrefixer` target table, declaration scanner, focused PHP harness, and WordPress text compatibility example. Full upstream Rust, Node, and WASM runners were not executed for this isolated micro-slice.
