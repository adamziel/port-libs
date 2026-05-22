# LightningCSS Upstream Inventory

Source: `.upstream-cache/lightningcss` at upstream commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

The lane denominator is no longer the seed manifest. It is a static upstream inventory plus runner evidence:

- 156 Rust `#[test]` functions counted as behavior checks after replacing `tests/test_cssom.rs` and `src/lib.rs::test_transitions` with focused helper-case inventories.
- 74 inspected CSSOM helper invocations in `tests/test_cssom.rs`.
- 71 inspected transition helper invocations in `src/lib.rs::test_transitions`.
- 81 Node `uvu` tests.
- 8 CSS fixture files tracked separately from the behavior denominator.

Runner evidence already recorded in `UPSTREAM_TEST_MANIFEST.json`: native Node tests, Rust `cargo test --all-features`, TypeScript checks, and WASM Node/browser runners have local passing evidence. The upstream cache currently contains local compatibility edits for the WASM runner (`Cargo.lock` and `src/lib.rs` dirty), so inventory reads should use `git show HEAD:<path>` for pristine upstream content unless the cache is restored.

Recent focused slices add native `TransitionPrefixer` coverage for 11 focused `src/lib.rs::test_mask` prefix cases: `transition: mask 200ms`, `transition: mask-border 200ms`, `transition-property: mask`, `transition-property: mask-border`, `transition-property: mask-composite, mask-mode`, direct `mask-border` shorthand prefixing, `mask-border-*` longhand composition, prefixed `-webkit-mask-box-image-*` longhand composition, `mask-border-slice: 10 40 10 40` compression, `mask-border-slice: var(--foo)`, and standalone `mask-composite`/`mask-mode` fallbacks. Earlier slices mapped custom-media boolean-logic cases, transition declaration composition, logical block-axis transition expansion, direction-sensitive inline transition prefixing, and `transition: transform` prefixing.

Current PHP lane evidence: 7 LightningCSS test files, 231 assertions, 0 failures. The required root `php tools/run-tests.php` was run after the lane update and passes: 94 test files, 6,277 assertions, 0 failures.
