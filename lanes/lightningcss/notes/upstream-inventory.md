# LightningCSS Upstream Inventory

Source: `.upstream-cache/lightningcss` at upstream commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

The lane denominator is no longer the seed manifest. It is a static upstream inventory plus runner evidence:

- 156 Rust `#[test]` functions counted as behavior checks after replacing `tests/test_cssom.rs` and `src/lib.rs::test_transitions` with focused helper-case inventories.
- 74 inspected CSSOM helper invocations in `tests/test_cssom.rs`.
- 71 inspected transition helper invocations in `src/lib.rs::test_transitions`.
- 81 Node `uvu` tests.
- 8 CSS fixture files tracked separately from the behavior denominator.

Runner evidence already recorded in `UPSTREAM_TEST_MANIFEST.json`: native Node tests, Rust `cargo test --all-features`, TypeScript checks, and WASM Node/browser runners have local passing evidence. The upstream cache currently contains local compatibility edits for the WASM runner (`Cargo.lock` and `src/lib.rs` dirty), so inventory reads should use `git show HEAD:<path>` for pristine upstream content unless the cache is restored.

This run mapped 5 additional `src/lib.rs::test_custom_media` boolean-logic cases: `not print and (--not-print-color)` deduplication, `screen and (--color-print)` rejection, incompatible `(--color-print) or (--color-screen)` rejection, incompatible `(--print) and (--screen)` rejection, and mixed screen/print branches inside one alias rejection. The previous uncommitted slice also mapped 7 `src/lib.rs::test_transitions` declaration-composition cases: transition-property/duration/timing-function/delay longhands folding into shorthand, shorthand plus later delay/timing longhands, `var(--ease)` timing fallback preservation, later shorthand reset behavior, list cycling, and independent `-webkit-`/`-moz-`/unprefixed transition groups. The latest slice maps 2 more focused `src/lib.rs::test_transitions` logical block-axis prefix cases: `transition-property: margin-block` expands to `margin-top, margin-bottom`, and `transition: margin-block-start 2s` expands to `margin-top 2s`.

Current PHP lane evidence: 6 LightningCSS test files, 210 assertions, 0 failures. The required root `php tools/run-tests.php` also passes in the current shared tree: 81 test files, 5,599 assertions, 0 failures.
