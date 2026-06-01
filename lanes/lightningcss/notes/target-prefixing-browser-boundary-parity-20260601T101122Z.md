# Text Spacing And Overscroll MS Browser-Boundary Parity

Source truth: upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/prefixes.rs` `Feature::TextSpacing` and `Feature::OverscrollBehavior`.

Behavior ported:
- `text-spacing` now emits `-ms-text-spacing` for Edge 12-18 and IE 8+.
- `overscroll-behavior` now emits `-ms-overscroll-behavior` for Edge 12-17 and IE 10+.
- Edge 19+ and Edge 18+ respectively prune stale `-ms-` declarations back to the unprefixed forms.
- `@supports` declaration conditions add or prune the matching `-ms-` alternatives using the same target windows.

Red-first evidence:
- Before the source change, `php -r 'require "tools/bootstrap.php"; $p=new PortLibs\LightningCSS\TransitionPrefixer(); foreach ([".foo { text-spacing: trim-start; }"=>["ie"=>8], ".foo { overscroll-behavior: contain; }"=>["ie"=>10], ".foo { -ms-text-spacing: trim-start; text-spacing: trim-start; -ms-overscroll-behavior: contain; overscroll-behavior: contain; }"=>["edge"=>19]] as $css=>$targets) { echo $p->prefixForTargets($css, $targets), "\n"; }'` emitted only unprefixed IE 8 / IE 10 declarations and preserved stale `-ms-` declarations for Edge 19.

Focused evidence:
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` passed with 1 test file, 1203 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests` passed with 13 test files, 7345 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-text-compat-prefixer.php --self-test` passed.
- PHP lint passed for `TransitionPrefixer.php`, `TransitionPrefixerTest.php`, and `wordpress-text-compat-prefixer.php`.
- `git diff --check -- lanes/lightningcss` passed.

WordPress smoke:
- `wordpress-text-compat-prefixer.php` now models root typography and scroll-containment CSS so legacy IE/Edge editor targets get MS-compatible `text-spacing` and `overscroll-behavior` declarations without Node/WASM while modern targets prune stale prefixes.

Dependency closure: no new support component is needed; this reuses the native PHP `TransitionPrefixer`, target-version routing, declaration prefix group rewriting, supports-declaration prefix rewriting, minifier output, and lane-local example coverage.

Non-overlap: avoids accepted shape, viewport, mask, clip-path, CSS Regions, unicode-bidi, selector stale-prefix, SVG paint, media-query, CSSOM, SourceMap, CSS Modules, custom-at-rule, and bundle/import graph slices. This slice is limited to MS target-prefix browser-boundary parity for `text-spacing` and `overscroll-behavior` from upstream `src/prefixes.rs`.

Next task: remaining target-prefix gaps include `image-rendering` / `pixelated` and any grid `-ms-` boundary parity not already covered by property-value minification slices.
