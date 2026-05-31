# Bundle Import Prelude Escaped Minifier Parity - 2026-05-31T231434Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T231434Z`

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream behavior anchor: `src/lib.rs::test_import` canonicalizes import sources and `supports()` preludes, with parser-backed CSS identifier/function token handling.
- Native upstream probe:
  - Input: `@import u\72l(foo.css) l\61yer(theme.blocks) s\75pports(display: grid) screen;`
  - Output: `@import "foo.css" layer(theme.blocks) supports(display:grid) screen;`

## Implementation

- `CssMinifier` now preserves required whitespace before valid CSS escape-started identifier/function tokens during its first whitespace pass. This prevents `@import \75 rl(...)` from becoming a single escaped at-keyword token.
- Direct import minification now reads CSS identifier tokens with escape decoding for `url()`, `layer` / `layer()`, and `supports()`, then serializes the same canonical prelude form as upstream.
- `wordpress-import-supports.php` now includes an escaped import prelude for a block stylesheet gated by layer, supports, and media conditions.

## Evidence

- Red probe before implementation:
  - `php -r 'require "tools/bootstrap.php"; $css="@import u\\72l(foo.css) l\\61yer(theme.blocks) s\\75pports(display: grid) screen;"; echo (new PortLibs\LightningCSS\CssMinifier())->minify($css), PHP_EOL;'`
  - Output before fix: `@import u\72l(foo.css) l\61yer(theme.blocks) s\75pports(display:grid) screen;`
- `php -l lanes/lightningcss/src/CssMinifier.php` passed.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-import-supports.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` passed: `1 test files, 1600 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 4792 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-import-supports.php` passed and emitted canonical escaped-prelude output.
- `git diff --check -- lanes/lightningcss` passed.

## Status Delta

- Full LightningCSS lane moved from `4790` to `4792` passing assertions.
- Mapped manifest denominator unchanged; this deepens the existing upstream import/minifier cluster rather than claiming a new upstream inventory unit.
- Dependency closure: no new support component is needed. The slice reuses existing native PHP CSS token/string helpers and the existing media/supports/layer minifiers.

## Non-overlap

- Does not repeat the accepted bundle graph escaped specifier/modifier work in `CssBundler`; this ports the direct `CssMinifier` import-prelude path.
- Does not attempt full escaped top-level `@\69mport` parity in direct minifier output. That is a broader at-keyword tokenization pass and remains a separate follow-up if required.
