# Bundle Resolution Import Graph Parity - 2026-06-01T073650Z

Status: ready for isolated lane handoff.

Source truth:
- Pinned upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Native NAPI probe for `@import "b.css" {} .entry{color:red}` reports `Unexpected token CurlyBracketBlock` in `/entry.css` at line 1, column 18.
- Native NAPI probe for `@import "b.css" layer(foo) {} .entry{color:red}` reports `Unexpected token CurlyBracketBlock` in `/entry.css` at line 1, column 29.
- The pre-change PHP bundler reported `@import rules cannot contain blocks` at line 1, column 1 for both valid import preludes. This patch moves valid block-form import diagnostics to the upstream curly-block token while preserving earlier invalid-layer-name diagnostics before graph resolution.

Implementation:
- `CssBundler::topLevelItems()` now parses the import prelude before rejecting a block-form `@import`, then reports `parser-error` / `Unexpected token CurlyBracketBlock` at the closing curly token location.
- `CssBundlerTest.php` adds reader-backed coverage for valid block-form imports with and without `layer(...)`, asserting no child import read occurs before the diagnostic.
- `wordpress-bundle-import-graph.php --self-test` now covers a block-theme style import with a valid layer name and verifies upstream-style rejection before token CSS is read.

Verification:
- `php -l lanes/lightningcss/src/CssBundler.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`: 1 test file, 633 assertions, 0 failures. Baseline before the source change was 621 assertions, so this slice adds 12 focused assertions.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`: passed, including `valid-import-block: rejected-at-curly-block`.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 test files, 6779 assertions, 0 failures.
- `git diff --check -- lanes/lightningcss`: passed.

Dependency closure:
- No new support component is needed. The slice reuses the existing PHP import tokenizer, resolver callback, exception location, and example self-test infrastructure.

Non-overlap:
- This is limited to valid block-form `@import` diagnostic parity. It does not alter accepted invalid import layer diagnostics, resolver result shape handling, source map import/remap behavior, CSS Modules dependency graph behavior, media-query lowering, CSSOM, property minification, custom at-rules, or target-prefixing clusters.

Root harness:
- Not run - isolated micro-slice.
