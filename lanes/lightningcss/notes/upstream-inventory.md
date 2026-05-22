# LightningCSS Upstream Inventory

Source: `.upstream-cache/lightningcss` at upstream commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

The lane denominator is no longer the seed manifest. It is a static upstream inventory plus runner evidence:

- 156 Rust `#[test]` functions counted as behavior checks after replacing `tests/test_cssom.rs` and `src/lib.rs::test_transitions` with focused helper-case inventories.
- 74 inspected CSSOM helper invocations in `tests/test_cssom.rs`.
- 71 inspected transition helper invocations in `src/lib.rs::test_transitions`.
- 81 Node `uvu` tests.
- 8 CSS fixture files tracked separately from the behavior denominator.

Runner evidence already recorded in `UPSTREAM_TEST_MANIFEST.json`: native Node tests, Rust `cargo test --all-features`, TypeScript checks, and WASM Node/browser runners have local passing evidence. The upstream cache currently contains local compatibility edits for the WASM runner (`Cargo.lock` and `src/lib.rs` dirty), so inventory reads should use `git show HEAD:<path>` for pristine upstream content unless the cache is restored.

This run mapped 7 additional `src/lib.rs::test_transitions` helper cases for transition shorthand value minification: default `ease` omission, shortest time units, canonical property/duration/timing/delay ordering, comma-separated layers, and prefixed shorthand time shortening.
