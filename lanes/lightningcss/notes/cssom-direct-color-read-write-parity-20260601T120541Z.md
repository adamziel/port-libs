# CSSOM Direct Color Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T120541Z`

Base accepted HEAD: `5b3a92fac14e00372ad9ece599226a1c8024ea79`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/lightningcss/tests/test_cssom.rs`
- `/home/claude/port-libs/.upstream-cache/lightningcss/src/properties/mod.rs`
- `/home/claude/port-libs/.upstream-cache/lightningcss/src/values/color.rs`
- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`

Implemented behavior:

- Expanded CSSOM direct declaration canonicalization for upstream typed `CssColor` and `ColorOrAuto` declarations:
  `color`, `background-color`, physical and logical border color longhands, `outline-color`,
  `text-decoration-color`, prefixed text-decoration color longhands, `text-emphasis-color`,
  `-webkit-text-emphasis-color`, and `caret-color`.
- Direct CSSOM `getPropertyValue()`, `setProperty()`, and `removeProperty()` paths now serialize parseable direct color values through the existing minified declaration serializer, matching upstream color canonicalization such as `#FF0000` to `red`, `Yellow` to `#ff0`, `blue` to `#00f`, `white` to `#fff`, `black` to `#000`, and transparent alpha colors to `#0000`.
- `currentcolor` normalizes to upstream `currentColor` for direct color declarations, while `caret-color: auto` remains `auto`.
- Custom properties and unresolved `var()` / `env()` values remain unparsed so WordPress design token declarations preserve authored custom-property names and values.

Red-first evidence:

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
- Result before implementation after adding the focused test: `1 test files, 1189 assertions, 1 failures`
- Failure showed authored color values were preserved instead of canonicalized for direct typed color declarations.

Verification:

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 1205 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 7694 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-direct-color-cssom.php --self-test`
  - Result: `OK`
- Changed PHP files were linted with `php -l`.
- `git diff --check -- lanes/lightningcss` passed.

Status delta:

- `lane-status.json` `phpPass`: `7677` to `7694`
- `phpFail`: unchanged at `0`
- Conservative mapped coverage unchanged at `2374 / 3532`; this deepens the existing CSSOM declaration-block inventory rather than claiming a new upstream denominator unit.

Dependency closure:

- No new support component is needed. The slice reuses the existing `DeclarationBlock` tokenizer, declaration serializer, and minifier/color canonicalization pipeline.

Non-overlap:

- This slice does not repeat accepted accent-color-only CSSOM coverage, SVG paint/read-write coverage, shadow/filter/transform canonicalization, shorthand expansion, CSS Modules, target-prefixing, or bundle/source-map work. It is bounded to upstream direct color longhand read/write parity.
