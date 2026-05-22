# LightningCSS Upstream Inventory

Source: `.upstream-cache/lightningcss` at upstream commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

The lane denominator is no longer the seed manifest. It is a static upstream inventory plus runner evidence:

- 156 Rust `#[test]` functions counted as behavior checks after replacing `tests/test_cssom.rs` and `src/lib.rs::test_transitions` with focused helper-case inventories.
- 74 inspected CSSOM helper invocations in `tests/test_cssom.rs`.
- 71 inspected transition helper invocations in `src/lib.rs::test_transitions`.
- 81 Node `uvu` tests.
- 8 CSS fixture files tracked separately from the behavior denominator.

Runner evidence already recorded in `UPSTREAM_TEST_MANIFEST.json`: native Node tests, Rust `cargo test --all-features`, TypeScript checks, and WASM Node/browser runners have local passing evidence. The upstream cache currently contains local compatibility edits for the WASM runner (`Cargo.lock` and `src/lib.rs` dirty), so inventory reads should use `git show HEAD:<path>` for pristine upstream content unless the cache is restored.

Recent focused slices add native `TransitionPrefixer` coverage for 23 focused `src/lib.rs::test_mask` prefix cases: `transition: mask 200ms`, `transition: mask-border 200ms`, `transition-property: mask`, `transition-property: mask-border`, `transition-property: mask-composite, mask-mode`, direct `mask-border` shorthand prefixing, direct `mask-border` LCH gradient fallback layers, direct `mask-border-source` LCH gradient fallback layers, `mask-border-*` longhand composition, prefixed `-webkit-mask-box-image-*` longhand composition, `mask-border-slice: 10 40 10 40` compression, `mask-border-slice: var(--foo)`, `mask-border: linear-gradient(lch(...)) var(--foo)` sRGB base plus lab `@supports` fallback emission, standalone `mask-composite`/`mask-mode` fallbacks, direct `mask-image` gradient/url prefixing, existing paired `-webkit-mask-image` normalization, `mask: url(...) luminance` source-type fallback emission, `mask-image`/position/size/repeat/origin/clip/composite/mode longhand composition, `mask-image` LCH gradient fallback layers, `mask` LCH gradient fallback layers, `mask: linear-gradient(lch(...)) 40px var(--foo)` sRGB base plus lab `@supports` fallback emission, and composed mask longhand LCH gradient fallback layers. The current slice maps 17 focused `src/lib.rs::test_animation` shorthand minification cases for quoted-name disambiguation, default component omission, canonical layer ordering, cubic-bezier alias omission, string-token whitespace preservation, and scroll/view timeline cleanup. Earlier slices mapped animation longhand cases for quoted `animation-name` serialization and `animation-timing-function` cubic-bezier/steps aliases, custom-media boolean-logic cases, transition declaration composition, logical block-axis transition expansion, direction-sensitive inline transition prefixing, and `transition: transform` prefixing.

Current PHP lane evidence: 7 LightningCSS test files, 267 assertions, 0 failures. The required root `php tools/run-tests.php` rerun on 2026-05-22 passes with 106 test files, 7,544 assertions, and 0 failures.
