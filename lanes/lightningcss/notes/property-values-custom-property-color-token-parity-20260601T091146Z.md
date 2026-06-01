# Custom Property Color Token Parity

Source truth: upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/properties/custom.rs` `TokenList::parse_into` and `try_parse_color_token`, which parse color tokens embedded in custom property token lists before serialization.

Behavior ported:
- Custom property declaration values now run through bounded color token minification, not only the previous `calc()`-inside-color-function special case.
- The path covers regular color tokens used in WordPress theme/style preset variables, including `rgb()`, `hwb()`, `lab()`, `color()`, and hex colors.
- String contents and `url(...)` fragments remain protected, and custom property keyword names are not converted into color keyword shorthands.
- The existing `color-mix()` custom property path is preserved.

Red-first probe before the change:
- `php -r 'require "tools/bootstrap.php"; $class="PortLibs\\LightningCSS\\CssMinifier"; $m=new $class(); foreach ([".foo { --color: rgb(255, 255, 0); }", ".foo { --color: hsl(100 100% 50%); }", ".foo { --color: hwb(194 0% 0%); }"] as $css) echo $m->minify($css), PHP_EOL;'`
- Output before the fix preserved unminified custom property colors: `.foo{--color:rgb(255,255,0)}`, `.foo{--color:hsl(100 100% 50%)}`, and `.foo{--color:hwb(194 0% 0%)}`.

Focused evidence:
- `php -l lanes/lightningcss/src/CssMinifier.php` passed.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` passed.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` passed with 1 test file, 1935 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` passed with 1 test file, 1140 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests` passed with 13 test files, 7081 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php --self-test` passed.
- `git diff --check -- lanes/lightningcss` passed.

WordPress smoke:
- `wordpress-color-value-minifier.php` now includes block-cover custom property color tokens for theme preset variables and verifies native minification to compact hex, normalized `lab()`, and normalized `color(display-p3 ...)` output without Node/WASM at runtime.

Dependency closure: no new support component is needed; this reuses `CssMinifier` color function, hex, custom property, quote, and URL scanning helpers.

Non-overlap: avoids accepted basic color minification, color-mix, relative-color, font oblique/default, grid auto-flow/default shorthand, SVG paint advanced-color fallback, selector stale-prefix pruning, target-prefixing, CSSOM, bundle/import graph, source-map, media-query, and custom-at-rule slices. This slice is limited to upstream-backed custom property color token serialization parity.
