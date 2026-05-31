# Sparse Checkout Backslash Byte Pathspec Parity - 2026-05-31

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260531T211121Z`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-glob/tests/wildmatch/mod.rs` includes byte-oriented wildmatch cases where escaped `\\` matches a literal backslash byte and `[[-\\]]` matches `[`, `\\`, and `]` but not `-`.
- `gix-glob/src/wildmatch.rs` operates on `BStr` byte slices; path matching treats `/` specially, but a backslash is an ordinary path byte unless it is an escape in the pattern.

## Native Delta

- `SparseCheckoutSpec::includesPath()` now preserves backslash bytes in Git tree paths instead of converting them into `/` path separators.
- `SparseCheckoutTest.php` adds sparse pathspec checks for escaped backslash literals and bracket-range matching under `:(glob)`.
- `examples/wordpress-sparse-checkout.php` records the WordPress deployment case where a plugin directory containing a backslash byte is selected, while the slash-separated path remains excluded.

## Red-First Probe

- Before the implementation, `SparseCheckoutSpec::fromPathspecs([':(glob)wp-content/plugins/f\\\\oo/block.json'])->includesPath('wp-content/plugins/f\\oo/block.json', false)` returned `false` because the candidate path was normalized to `f/oo`.
- The same normalization made the upstream `[[-\\]]` backslash-byte range case unreachable for sparse checkout tree paths.

## Verification

- Focused before/after assertion count: `SparseCheckoutTest.php` moved from `219` to `225` assertions.
- Focused test:
  - `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `1 test files, 225 assertions, 0 failures`.
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `39 test files, 5761 assertions, 0 failures`.
- PHP lint, example smoke, JSON validation, and diff-check passed in the worker handoff.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP sparse checkout/pathspec matcher and PHP byte-string regex handling; it does not require upstream binaries, live Git providers, credential stores, or shared support-library activation.

## Non-Overlap

This extends accepted sparse checkout/pathspec work without repeating cone rules, non-cone pattern-file ordering, directory-only excludes, POSIX class boundaries, default search modes, absolute-root normalization, absolute wildcard prefix behavior, tree pathspec walking, attributes/pathspec filters, protocol, transport, pack/index, reference, or merge behavior. The new mapped behavior is limited to Gitoxide's byte-oriented backslash handling inside sparse checkout pathspec wildmatch.
