# CSS Modules Host Context Local/Global Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T220823Z`

Accepted base: `6f5231cf32a6827b588751d49dba711af77e658b`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant upstream areas: `src/lib.rs::test_css_modules` and `src/css_modules.rs::CssModule::handle_composes`.
- Native oracle: `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` preserves descendant whitespace in raw `:host-context(.public-theme :global(.legacy-scope))` output while keeping local CSS Modules exports and composes metadata.

## Behavior

PHP previously minified raw host-context arguments like `.public-theme :global(.legacy-scope)` and `.public-theme :local(.legacy-local)` into attached pseudo-class selectors. That changed descendant semantics before CSS Modules could preserve the raw pseudo-function boundaries.

This patch teaches `CssMinifier::startsDescendantPseudoClass()` that CSS Modules functional pseudo-classes `:global(` and `:local(` need the same descendant-space preservation as `:is(`, `:where(`, `:not(`, and `:has(`. `CssModulesTransformer` now keeps upstream-compatible raw host-context selectors while still scoping `.card`, composing `base`, and exporting `card => EgL3uq_card EgL3uq_base`.

## Files

- `lanes/lightningcss/src/CssMinifier.php`
- `lanes/lightningcss/tests/CssModulesTransformerTest.php`
- `lanes/lightningcss/tests/CssMinifierTest.php`
- `lanes/lightningcss/examples/wordpress-css-modules-host-context.php`
- `lanes/lightningcss/lane-status.json`
- `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`

## Evidence

- `php -l lanes/lightningcss/src/CssMinifier.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-host-context.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => 1 test file / 297 assertions / 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` => 1 test file / 1577 assertions / 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests` => 13 test files / 4622 assertions / 0 failures.
- `php lanes/lightningcss/examples/wordpress-css-modules-host-context.php --self-test` => OK.
- `php -r '$j=json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true); if (json_last_error()) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "OK\n";'` => OK.
- `php -r '$j=json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true); if (json_last_error()) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "OK\n";'` => OK.
- `git diff --check -- lanes/lightningcss` => passed.

## Status Delta

- `phpPass`: 4617 -> 4622.
- Conservative mapped coverage remains `2163 / 3532` because this deepens the already represented CSS Modules local/global/composes cluster.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `CssMinifier`, `CssModulesTransformer`, and existing test/example harnesses.

## Non-Overlap

This does not repeat accepted host-context public argument scoping, selector-list validation, escaped identifiers, invalid composes fallback, pseudo-elements, unusedSymbols, animation/grid/container/scope/dashed/view-transition/content-hash, or bundle dependency behavior. The bounded behavior is raw host-context descendant-space preservation before CSS Modules `:global()` and `:local()` pseudo-functions while preserving local composes exports.
