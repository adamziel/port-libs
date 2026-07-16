# Pandoc JSON/native single-wrapped block tuple constructors

Date: 2026-06-15

Scope: bounded native PHP Pandoc JSON/native AST constructor completeness under `lanes/pandoc`.

Implemented one focused coverage slice for single-wrapped tuple payloads on block and table helper constructors. `PandocJsonReader` and `NativeReader` now accept the existing reader tuple-unwrapping path for `Header`, `CodeBlock`, `RawBlock`, `OrderedList`, `DefinitionList` items, `Div`, `Figure`, `Table`, table column specs, and `TableHead`/`TableBody`/`Row`/`Cell` helpers. `PandocJsonWriter` and `NativeWriter` preserve unchanged wrapped table helper payloads when table wrappers are rebuilt from normalized AST nodes.

The focused test case in `PandocJsonNativeAstTest.php` reads the same packet through `PandocJsonReader` and `NativeReader`, checks normalized block/table attributes, verifies complete wrapped block payload preservation through both writers, and verifies rebuilt table output keeps wrapped helper payloads for head/body/foot/row/cell.

Accounting updates:

- `phpPass`: 3702 -> 3703
- `phpFail`: 0
- upstream mapped cases: 3726 -> 3727
- `mappedJsonNativeConstructorCompletenessCases`: 46 -> 47 in lane status; 44 -> 45 in the upstream manifest
- `jsonNativeConstructorCompletenessAssertions`: 1091 -> 1149 in lane status; 1048 -> 1106 in the upstream manifest
- `mappedJsonNativeSingleWrappedBlockTupleCases`: 1 -> 2
- `jsonNativeSingleWrappedBlockTupleAssertions`: 86 -> 144

Verification performed after rebase onto current main `ca72d60b88`:

- `php -l` for touched Pandoc reader/writer/test PHP files
- focused `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - Result: `1 test files, 5082 assertions, 0 failures`
- full `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `46 test files, 87670 assertions, 0 failures`
- PHP JSON manifest/status validation
- `git diff --check`
- conflict-marker scan

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

## plib-kwpsv rebase slice

Bead: plib-kwpsv

Implemented an additional bounded JSON/native AST constructor completeness matrix case for single-wrapped tuple payloads on `Header`, `CodeBlock`, `RawBlock`, `OrderedList`, `Div`, `Figure`, and `Table`. The merged test keeps the already-landed block/table helper coverage and adds the source slice's table-body/edit assertions under a distinct test name.

Accounting updates after rebase onto current main `f4a2c4886a`:

- `phpPass`: 3732 -> 3733
- `phpFail`: 0
- upstream mapped cases: 3750 -> 3751
- `mappedJsonNativeConstructorMatrixCases`: lane/root 19 -> 20; upstream 18 -> 19
- `jsonNativeConstructorMatrixAssertions`: lane/root 331 -> 429; upstream 306 -> 404
- `mappedJsonNativeConstructorCompletenessCases`: lane 58 -> 59; upstream/root 54 -> 55
- `jsonNativeConstructorCompletenessAssertions`: lane 1512 -> 1610; upstream/root 1377 -> 1475
- `mappedJsonNativeSingleWrappedBlockTupleCases`: lane 3 -> 4; upstream/root 2 -> 3
- `jsonNativeSingleWrappedBlockTupleAssertions`: lane 208 -> 306; upstream/root 144 -> 242

Verification passed after rebase onto current main `f4a2c4886a`:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- focused `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - Result: `1 file, 5564 assertions, 0 failures`
- full `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `46 files, 88664 assertions, 0 failures`
- PHP JSON manifest/status validation
- `git diff --check`
- conflict-marker scan
