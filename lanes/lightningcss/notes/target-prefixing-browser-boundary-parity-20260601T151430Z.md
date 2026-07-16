# LightningCSS target-prefixing browser-boundary parity - CSS Regions

## Source truth

- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` at the pinned commit has no generated vendor-prefix property entries for `flow-into`, `flow-from`, or `region-fragment`; the earlier PHP behavior had inferred prefixes from `src/prefixes.rs` feature rows alone.
- Native oracle from the pinned local binding:
  - `.foo { flow-into: article; flow-from: article; region-fragment: break; }` stays `.foo{flow-into:article;flow-from:article;region-fragment:break}` for legacy `chrome 18 + ie 10` and modern `chrome 120` targets.
  - Authored stale `-webkit-` and `-ms-` CSS Regions declarations are preserved, not pruned, for the same legacy and modern target sets.

## Patch

- Removed the PHP CSS Regions declaration prefix rewrite path from `TransitionPrefixer`.
- Removed now-unused CSS Regions target flags.
- Updated the focused CSS Regions boundary test to assert upstream behavior: no generated prefixes for unprefixed declarations, and preservation of authored stale prefixes.
- Updated `wordpress-css-regions-prefixer.php` expected output so legacy editor/Safari and modern frontend paths all preserve unprefixed CSS Regions declarations.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` - pass
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-css-regions-prefixer.php` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` - `1 test files, 1368 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-css-regions-prefixer.php --self-test` - pass

Full LightningCSS lane and upstream Rust/Node/WASM runners were not run for this isolated micro-slice.

## Non-overlap

This slice corrects only the CSS Regions `flow-into` / `flow-from` / `region-fragment` target-prefixing behavior. It does not touch the accepted CSSOM list-style/counter, color-adjust, print-color-adjust, writing-mode, scroll-snap, grid-display, flex, shape, or supports-declaration target-prefixing clusters.

## Dependency closure

No new support component is needed. The existing PHP declaration parser/serializer is reused, and the change removes an over-eager prefixing helper instead of adding a new dependency.
