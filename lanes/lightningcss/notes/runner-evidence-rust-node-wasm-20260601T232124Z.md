# LightningCSS Rust/Node/WASM runner evidence - 2026-06-01 23:21 UTC

Source truth: upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Observed upstream cache:

- `/home/claude/port-libs/.upstream-cache/lightningcss` is at the pinned commit.
- The cache is dirty in `src/lib.rs` and `Cargo.lock`; the source diff is a `#[cfg(target_arch = "wasm32")]` `__getrandom_v03_custom` shim plus lockfile dependency drift. Native Rust lib test execution below is therefore bounded dirty-cache evidence, with pinned source truth still coming from `git show`.

Runner evidence:

- Rust bounded runner passed:
  `cargo test --manifest-path /home/claude/port-libs/.upstream-cache/lightningcss/Cargo.toml --lib tests::test_media -- --exact`
  -> `1 passed; 0 failed; 118 filtered out`.
- Rust discovery passed:
  `cargo test --manifest-path /home/claude/port-libs/.upstream-cache/lightningcss/Cargo.toml --lib test_media -- --list`
  -> listed `tests::test_media`.
- Rust CSSOM discovery passed:
  `cargo test --manifest-path /home/claude/port-libs/.upstream-cache/lightningcss/Cargo.toml --test test_cssom -- --list`
  -> listed `test_get`, `test_remove`, and `test_set`.
- Native Node addon direct smoke passed:
  direct `require('/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node')` transform of `.foo { color: red; }` produced `.foo{color:red}`.
- Full Node uvu runner is blocked before LightningCSS tests execute:
  `node /home/claude/port-libs/.upstream-cache/lightningcss/node/test/transform.test.mjs`
  -> `ERR_MODULE_NOT_FOUND`, missing package `uvu`.
- Node package entrypoint is blocked before the fallback native addon load:
  `require('/home/claude/port-libs/.upstream-cache/lightningcss/node/index.js')`
  -> `MODULE_NOT_FOUND`, missing module `detect-libc`.
- WASM runtime smoke is blocked:
  `require('/home/claude/port-libs/.upstream-cache/lightningcss/wasm/wasm-node.cjs')`
  -> missing module `napi-wasm`.
- WASM build preflight is blocked:
  `wasm-opt --version`
  -> `wasm-opt: command not found`.

PHP evidence:

- Added `UpstreamRunnerEvidence`, a non-executing lane-local classifier for captured runner output.
- Added `UpstreamRunnerEvidenceTest.php` covering Rust pass parsing, direct native Node smoke pass classification, Node/WASM missing dependency blockers, missing `wasm-opt`, and partial runner-closure summary.

Non-overlap:

This slice does not add or repeat CSS parser/minifier/bundler behavior. It only replaces the generic Rust/Node/WASM "not executed" blocker with bounded Rust/native-addon evidence plus exact Node/WASM dependency blockers. Conservative mapped behavior coverage remains `2439 / 3532`.

Dependency closure:

No new PHP support component is required. Full upstream runner closure needs upstream dependency installation for `uvu`, `detect-libc`, `napi-wasm`, plus `wasm-opt` for WASM build evidence. The checked-in native Node addon can be used for bounded oracle smokes without installing Node dependencies.
