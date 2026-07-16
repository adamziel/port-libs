# CSSOM box spacing length normalization parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T031606Z`

Source truth:
- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/margin_padding.rs` parses margin, padding, scroll-margin, scroll-padding, and inset physical/logical values as `LengthPercentageOrAuto`.
- `src/values/length.rs` serializes zero dimensions without units and fractional non-zero dimensions without a leading zero.
- `src/values/percentage.rs` serializes fractional percentages without a leading zero.

Behavior added:
- `DeclarationBlock` now canonicalizes simple `LengthPercentageOrAuto` tokens for physical and logical box-spacing CSSOM declarations during parse, get, set, and remove flows.
- Covered properties are margin, padding, scroll-margin, scroll-padding, inset, and their existing logical axis longhands/shorthands.
- Functional tokens such as `var()` and `calc()` stay in the existing raw-token path.
- The WordPress scroll-snap CSSOM smoke now verifies `scroll-padding: 0px 2rem` and a write of `0.500rem` serialize as upstream-style `0 2rem 0 .5rem`.

Verification:
- Red-first focused run before implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` failed because `margin: 0px 0.500rem` read back as `0px 0.500rem` instead of `0 .5rem`.
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed `1 test files, 943 assertions, 0 failures`.
- Full lane after implementation: `php tools/run-tests.php lanes/lightningcss/tests` passed `13 test files, 5741 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-scroll-snap-cssom.php --self-test` passed with `OK`.

Non-overlap:
- This patch only touches CSSOM declaration read/write serialization for box-spacing property groups.
- It does not touch bundle/import graph, source maps, CSS Modules, media query parsing, custom at-rules, target prefixing, color/property minification, selectors, or parser recovery.
- The old `CustomMediaTransformer.php` rework note remains historical for this slice.

Dependency closure:
- No new support component is needed. The patch reuses the existing `DeclarationBlock` parser, top-level whitespace splitter, and CSS number serializer.
