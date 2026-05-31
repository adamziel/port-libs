# CSSOM mask read/write parity - 2026-05-31

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T165538Z`

Upstream source truth:

- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs` `DeclarationBlock::{get,set,remove}` CSSOM behavior.
- `src/properties/masking.rs` `mask` shorthand and longhand set: `mask-image`, `mask-position`, `mask-size`, `mask-repeat`, `mask-origin`, `mask-clip`, `mask-composite`, and `mask-mode`.
- `src/macros.rs` `define_list_shorthand!` list-length write semantics for shorthand-backed longhands.

Implementation:

- Added native PHP `DeclarationBlock` support for unprefixed `mask` shorthand read, shorthand composition from longhands, same-priority longhand writes into existing `mask` shorthands, priority bucket separation, multi-layer list-length checks, and shorthand splitting on removal.
- Added parser support for mask defaults and layer components: image, position/size, repeat, origin/clip boxes, `no-clip`, composite, and mode.
- Fixed slash handling so `url("/wp-content/...")` remains an image token and only top-level `/` separates position from size.
- Added `wordpress-mask-cssom.php` to exercise a WordPress asset-mask CSSOM workflow without Node/WASM.

Verification:

- `php -l lanes/lightningcss/src/DeclarationBlock.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-mask-cssom.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 416 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 2350 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-mask-cssom.php --self-test` -> `OK`.
- `git diff --check -- lanes/lightningcss` -> no output.

Coverage/status:

- DeclarationBlock focused test coverage moved from `387` to `416` assertions, a `+29` assertion delta.
- Full LightningCSS PHP lane evidence moved from `2321` to `2350` assertions with `0` failures.
- Conservative mapped coverage remains `1450 / 3532` because this deepens the already represented DeclarationBlock CSSOM cluster.

Dependency closure:

- No new support component is needed. The slice reuses the native declaration parser, top-level token scanners, URL normalization, shorthand component composition, and CSSOM priority ordering.

Non-overlap:

- This does not repeat accepted mask-border CSSOM, background/border/border-image/text-decoration/transition/outline/font/grid CSSOM, or target-prefix mask/browser-boundary work.
- The stale `CustomMediaTransformer.php` rework note under the main handoff directory is unrelated to this CSSOM mask slice.

Follow-up:

- Prefixed `-webkit-mask` CSSOM read/write parity remains a possible follow-up if the supervisor wants prefixed declaration CSSOM behavior beyond unprefixed source-truth coverage.
