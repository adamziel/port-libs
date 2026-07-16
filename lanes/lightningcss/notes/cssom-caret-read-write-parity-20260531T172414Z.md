# CSSOM Caret Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T172414Z`

## Source Truth

- Upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/ui.rs` defines the `Caret` shorthand with `caret-color` and `caret-shape` longhands.
- Upstream `CaretShape` defaults to `auto` and accepts `auto`, `bar`, `block`, and `underscore`.
- Upstream UI minifier cases cover `caret-color`, `caret-shape`, and shorthand orders including `yellow block`, `block yellow`, `auto auto`, `auto`, `yellow auto`, and `auto block`.

## Lane Delta

- Added `DeclarationBlock` CSSOM get/set/remove support for `caret`, `caret-color`, and `caret-shape`.
- Added focused DeclarationBlock tests for shorthand reads, longhand composition, shorthand order parity, priority mismatch behavior, longhand updates inside shorthand/direct declarations, and longhand/shorthand removals.
- Added `examples/wordpress-caret-cssom.php` to cover a WordPress editor caret color/shape workflow using preset color variables without Node/WASM.
- Updated lane-local status. Conservative mapped coverage remains `1566 / 3532` because this deepens the already represented DeclarationBlock CSSOM cluster.

## Verification Evidence

- Red/absent behavior before implementation:
  - `php -r 'require "tools/bootstrap.php"; $b=new PortLibs\LightningCSS\DeclarationBlock(); var_export([$b->getProperty("caret: yellow block", "caret-color"), $b->getProperty("caret-color: yellow; caret-shape: block", "caret"), $b->setProperty("caret: yellow block", "caret-shape", "underscore"), $b->removeProperty("caret: yellow block", "caret-color")]); echo PHP_EOL;'`
  - Output: `array ( 0 => NULL, 1 => NULL, 2 => 'caret: yellow block; caret-shape: underscore', 3 => 'caret: yellow block', )`
- Baseline focused run before this slice: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed at `1 test files, 480 assertions, 0 failures`.
- After implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed at `1 test files, 501 assertions, 0 failures`.
- Full lane focused run: `php tools/run-tests.php lanes/lightningcss/tests` passed at `13 test files, 2680 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-caret-cssom.php --self-test` printed `OK`.
- PHP lint passed for `lanes/lightningcss/src/DeclarationBlock.php`, `lanes/lightningcss/tests/DeclarationBlockTest.php`, and `lanes/lightningcss/examples/wordpress-caret-cssom.php`.
- `git diff --check -- lanes/lightningcss` passed with no output.

## Dependency Closure

No new support component is needed. The slice reuses the existing `DeclarationBlock` parser, top-level token splitting, priority partitioning, and CSSOM shorthand removal helpers.

## Non-Overlap

This slice does not touch accepted mask, text-emphasis, font, flex, source-map, CSS Modules, target-prefixing, bundler, or media-query behavior. It is scoped to the upstream caret CSSOM declaration cluster.
