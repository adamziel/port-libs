# CSS Regions Target Prefixing Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T090641Z`

Base accepted HEAD: `a1d76e2d4acf71d2e476e518418e51e1353c7eb0`

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Source read: `src/prefixes.rs::Feature::FlowInto | Feature::FlowFrom | Feature::RegionFragment`
- Upstream target routing: Chrome 15-18, Safari 6.1-11, and iOS Safari 7-11 need `-webkit-`; Edge 12-18 and IE >= 10 need `-ms-`.
- No direct `src/lib.rs` helper row was found for these property names, so the denominator mapping stays conservative.

## Red-First Probe

Before this patch, the native PHP prefixer preserved CSS Regions properties without target prefixes:

`php -r 'require "tools/bootstrap.php"; $class="PortLibs\\\\LightningCSS\\\\TransitionPrefixer"; $p=new $class(); echo $p->prefixForTargets(".foo { flow-into: article; flow-from: article; region-fragment: break; }", ["chrome"=>18,"ie"=>10]), "\\n";'`

Output before implementation:

`.foo{flow-into:article;flow-from:article;region-fragment:break}`

## Implemented

- Added `cssRegionsNeedsWebkit` and `cssRegionsNeedsMs` target flags from the pinned upstream browser boundaries.
- Added declaration prefixing for `flow-into`, `flow-from`, and `region-fragment`.
- Reused the existing vendor-prefixed declaration group rewriter so stale matching prefixed declarations are dropped when modern targets no longer need them.
- Added `wordpress-css-regions-prefixer.php` for a block-query source/frame smoke covering legacy editor targets and modern frontend targets.

## Evidence

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-css-regions-prefixer.php`
  - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1152 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-css-regions-prefixer.php --self-test`
  - passed
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7087 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - passed

## Dependency Closure

No new support component is needed. This reuses `TransitionPrefixer` target normalization, declaration scanning, serializer output, and the existing vendor-prefixed declaration group helper.

## Non-Overlap

This slice avoids accepted filter/supports, unicode-bidi, writing-mode, scroll-snap, flex, object-fit, background-clip, mask, selector, media-query, CSSOM, SourceMap, CSS Modules, custom-at-rule, and bundle/import graph clusters. It is limited to direct CSS Regions declaration prefixing.

## Follow-Up

If future upstream evidence requires it, handle CSS Regions inside `@supports` declaration conditions separately. This patch only closes direct declaration prefix parity.
