# Bundle Resolution Import Graph Parity - 2026-06-01T07:18Z

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T071857Z`

Source truth:

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Native addon oracle: `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`
- Direct probes confirmed `@import "x.css" supports((unknown))` and `@import "x.css" supports((--wp-theme-variant))` serialize as `supports((unknown))` / `supports((--wp-theme-variant))`, while `supports((display: flex))`, `supports((selector(.a)))`, and `layer(theme) supports((display: flex))` unwrap to normal declaration/function supports forms.

Behavior added:

- `CssMinifier` now treats parenthesized bare unknown import supports conditions as CSS general-enclosed conditions and preserves one inner wrapper inside `supports(...)`.
- Normal supports declarations and functions still unwrap like upstream, including `supports((display: flex))`, `supports((selector(.a)))`, and layer-prefixed import modifiers.
- Bundler external imports now preserve resolver-marked feature-flagged block/theme import conditions such as `supports((--wp-theme-variant))`.
- Custom media expectations that flow through import minification were rebased to the same pinned native addon behavior.

Evidence:

- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 612 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
  - `1 test files, 1905 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/CustomMediaTransformerTest.php`
  - `1 test files, 41 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6706 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - `external-supports-unknown: preserved`
- `php -l lanes/lightningcss/src/CssMinifier.php`
  - `No syntax errors detected in lanes/lightningcss/src/CssMinifier.php`
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CssBundlerTest.php`
- `php -l lanes/lightningcss/tests/CssMinifierTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CssMinifierTest.php`
- `php -l lanes/lightningcss/tests/CustomMediaTransformerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CustomMediaTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
- `git diff --check -- lanes/lightningcss`
  - Passed with no output.

Dependency closure:

- No new support component is needed. This reuses the existing PHP import parser, CSS minifier, resolver-marked external import serialization, and WordPress bundle import-graph smoke path.

Non-overlap:

- This does not repeat accepted CSS Modules, CSSOM, source-map, media range, or target-prefixing slices.
- The slice is limited to import supports serialization after bundle resolution and minification; mapped coverage remains `2360 / 3532`.

Follow-up:

- Broader bundle/import graph parity can still cover additional source-map and CSS Modules dependency-order edge cases from the pinned upstream corpus.
