# CSSOM Animation Timeline Function Read/Write Parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260601T014543Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream files: `src/properties/animation.rs`
- Relevant behavior: `AnimationTimeline` serializes `scroll()` with default `nearest block` arguments omitted, and serializes `view()` with default `block auto auto` arguments omitted. `scroll()` accepts scroller and axis in either order; `view()` accepts axis and inset in either order.

Implemented behavior:

- `DeclarationBlock` now normalizes `animation-timeline` longhand values during parse and `setProperty()`.
- Animation shorthand parsing normalizes `scroll(...)` and `view(...)` timeline tokens before CSSOM reads or longhand writes.
- `scroll(root block)` becomes `scroll(root)`, `scroll(nearest block)` becomes `scroll()`, `scroll(nearest inline)` becomes `scroll(inline)`, and `scroll(y self)` becomes `scroll(self y)`.
- `view(block auto auto)` becomes `view()`, `view(block 10% 10%)` becomes `view(10%)`, and `view(auto 20% inline)` becomes `view(inline auto 20%)`.

Focused evidence:

- Baseline before edit: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed with `1 test files, 886 assertions, 0 failures`.
- Post edit: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed with `1 test files, 890 assertions, 0 failures`.
- Lane gate: `php tools/run-tests.php lanes/lightningcss/tests` passed with `13 test files, 5406 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-animation-cssom.php --self-test` passed with `OK`.

Non-overlap:

- This avoids accepted CSSOM custom-property, transform/shadow, animation longhand, animation-range, shorthand-priority/removal, and declaration enumeration clusters. The change is limited to animation timeline function canonicalization for declaration read/write parity.

Dependency closure:

- No new support component is needed. The implementation reuses the existing native declaration parser utilities for top-level comma and whitespace token splitting.
