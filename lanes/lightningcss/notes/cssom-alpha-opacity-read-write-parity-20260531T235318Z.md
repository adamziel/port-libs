# CSSOM alpha opacity read/write parity - 2026-05-31

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T235318Z`

Upstream source truth:

- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/values/alpha.rs` parses alpha values as numbers or percentages and serializes the stored alpha as a number.
- `src/properties/mod.rs` maps `opacity`, `fill-opacity`, and `stroke-opacity` to `AlphaValue`.
- Upstream minifier coverage in `src/lib.rs::test_opacity` serializes `opacity: 50%` as `.5`, `0%` as `0`, and `100%` as `1`.

Red-first evidence:

- Before the implementation, the local CSSOM direct declaration path returned raw percentages:
  `getProperty("opacity: 50%", "opacity") => "50%"`,
  `setProperty("opacity: 1", "opacity", "25%") => "opacity: 25%"`,
  and `getProperty("fill-opacity: 100%; stroke-opacity: .25", "fill-opacity") => "100%"`.

Implementation:

- Added native DeclarationBlock alpha normalization for direct `opacity`, `fill-opacity`, and `stroke-opacity` declarations.
- Percent alpha tokens now serialize to number form during parse/get/set, matching upstream behavior (`50%` to `.5`, `25%` to `.25`, `100%` to `1`).
- Numeric alpha tokens reuse existing CSS number compaction (`0.2500` to `.25`) while invalid/non-numeric tokens remain unchanged.
- Custom properties such as `--opacity: 50%` remain unnormalized.
- Added `wordpress-alpha-opacity-cssom.php` to cover a WordPress icon/SVG opacity CSSOM workflow without Node/WASM.

Verification:

- `php -l lanes/lightningcss/src/DeclarationBlock.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-alpha-opacity-cssom.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 858 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 4974 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-alpha-opacity-cssom.php --self-test` -> `OK`.
- `git diff --check -- lanes/lightningcss` -> no output.

Coverage/status:

- DeclarationBlock focused coverage moved from `849` to `858` assertions, a `+9` assertion delta.
- Full LightningCSS PHP lane evidence moved from `4965` to `4974` assertions with `0` failures.
- Conservative mapped coverage remains `2212 / 3532` because this deepens the already represented DeclarationBlock CSSOM cluster.

Dependency closure:

- No new support component is needed. This reuses the native declaration parser, direct property CSSOM read/write path, existing number serialization helper, and existing CSSOM priority bucket serializer.

Non-overlap:

- This does not repeat accepted shorthand CSSOM, mask/border/font/grid/transition CSSOM, CSS-wide keyword canonicalization, or border-spacing normalization work.
- The stale `CustomMediaTransformer.php` rework note under the main handoff directory is unrelated to this alpha-value CSSOM slice.

Follow-up:

- Additional value-normalization parity remains possible for other direct declaration value types that upstream parses into typed property values.
