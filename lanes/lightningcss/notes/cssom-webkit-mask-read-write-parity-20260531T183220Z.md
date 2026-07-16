# CSSOM -webkit-mask read/write parity - 2026-05-31

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T183220Z`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` declares `mask` as a shorthand with a WebKit-prefixed shorthand form.
- `src/properties/masking.rs` defines list-shorthand behavior for mask image, position, size, repeat, origin, and clip components.
- `src/declaration.rs` applies CSSOM `getPropertyValue`, `setProperty`, and `removeProperty` behavior over the declaration block.

## Red-First Probe

Before the patch, the accepted PHP port handled the unprefixed `mask` CSSOM group but not the prefixed `-webkit-mask` group:

- Reading `-webkit-mask-image` from `-webkit-mask: url("mask.svg") ...` returned `NULL`.
- Setting `-webkit-mask-repeat` appended a separate longhand instead of updating the `-webkit-mask` shorthand.
- Removing `-webkit-mask-image` from a `-webkit-mask` shorthand left the shorthand unchanged.

## Implementation

- Added the prefixed `-webkit-mask` CSSOM shorthand group to `DeclarationBlock`.
- Reads now expose `-webkit-mask-image`, `-webkit-mask-position`, `-webkit-mask-size`, `-webkit-mask-repeat`, `-webkit-mask-origin`, and `-webkit-mask-clip` from a `-webkit-mask` shorthand.
- Same-priority prefixed longhand writes update an existing `-webkit-mask` shorthand when layer counts match, while priority mismatches and list-length mismatches still preserve separate declarations.
- Removing a prefixed longhand now splits `-webkit-mask` into remaining prefixed longhands; removing the shorthand removes both the shorthand and directly coupled prefixed longhands.
- The unprefixed `mask` and prefixed `-webkit-mask` CSSOM groups remain isolated.
- Extended `examples/wordpress-mask-cssom.php` so the existing WordPress mask asset workflow covers prefixed read/write/remove behavior without Node/WASM.

## Verification

- `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php`
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php`
- `php -l lanes/lightningcss/examples/wordpress-mask-cssom.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-mask-cssom.php`
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `1 test files, 577 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 3078 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-mask-cssom.php --self-test`
  - `OK`
- `git diff --check -- lanes/lightningcss`
  - clean

## Status Delta

- DeclarationBlock focused test evidence increased from `559` to `577` assertions, a `+18` assertion delta.
- Full LightningCSS PHP lane evidence increased from `3060` to `3078` assertions with `0` failures.
- Conservative mapped coverage remains `1684 / 3532` because this deepens the already represented DeclarationBlock CSSOM cluster.

## Non-Overlap

- This does not repeat accepted unprefixed `mask` CSSOM behavior, `mask-border` CSSOM behavior, transition prefixing for mask properties, or accepted logical border/background/border-image/font/grid/list-style/outline/text CSSOM clusters.
- The stale main-repo `CustomMediaTransformer.php` rework note is historical for this micro-slice and unrelated to prefixed declaration CSSOM behavior.

## Dependency Closure

No new support component is needed. This reuses the native declaration parser, mask layer parser/composer, priority bucket handling, shorthand split/remove helpers, and serializer already present in the LightningCSS lane.

## Root Harness

Not run; this is an isolated lane micro-slice.
