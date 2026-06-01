# LightningCSS runner dependency closure - 2026-06-01 23:43 UTC

Source truth: upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Current-base runner probes:

- Rust bounded runner passed:
  `cargo test --manifest-path /home/claude/port-libs/.upstream-cache/lightningcss/Cargo.toml --lib tests::test_media -- --exact`
  -> `1 passed; 0 failed; 118 filtered out`.
- Native Node addon direct smoke passed:
  direct `require('/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node')` transform of `.foo { color: red; }` produced `.foo{color:red}`.
- Full Node uvu runner remains blocked before LightningCSS tests execute:
  `node /home/claude/port-libs/.upstream-cache/lightningcss/node/test/transform.test.mjs`
  -> missing package `uvu`.
- Node package entrypoint remains blocked before native addon fallback:
  `node -e "require('/home/claude/port-libs/.upstream-cache/lightningcss/node/index.js')"`
  -> missing module `detect-libc`.
- WASM runtime smoke remains blocked:
  `node -e "require('/home/claude/port-libs/.upstream-cache/lightningcss/wasm/wasm-node.cjs')"`
  -> missing module `napi-wasm`.
- WASM build preflight remains blocked:
  `wasm-opt --version`
  -> `wasm-opt: command not found`.

Pinned package manifest closure:

- `detect-libc` is declared in upstream `package.json` `dependencies` at `^2.0.3`.
- `uvu` is declared in upstream `package.json` `devDependencies` at `^0.5.6`.
- `napi-wasm` is declared in upstream `package.json` `devDependencies` at `^1.0.1`.
- `wasm-opt` is not an npm package dependency. It is referenced by upstream `wasm:build` and `wasm:build-release` scripts and should be satisfied by an external Binaryen install that puts `wasm-opt` on `PATH`.

PHP evidence:

- Added `UpstreamRunnerEvidence::dependencyClosurePlan()` to classify blocked runner records against the pinned package manifest and external-tool requirements.
- Focused `UpstreamRunnerEvidenceTest.php` passed `1 test files / 44 assertions / 0 failures`, up from the accepted `32` assertions.
- Full LightningCSS PHP lane passed `14 test files / 9150 assertions / 0 failures`, up from accepted `9138`.

Verification:

- `php -l lanes/lightningcss/src/UpstreamRunnerEvidence.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/UpstreamRunnerEvidenceTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/UpstreamRunnerEvidenceTest.php` -> `1 test files / 44 assertions / 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `14 test files / 9150 assertions / 0 failures`.
- `php -r "json_decode(file_get_contents('lanes/lightningcss/lane-status.json'), true, 512, JSON_THROW_ON_ERROR);"` -> JSON ok.
- `php -r "json_decode(file_get_contents('lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);"` -> JSON ok.
- `git diff --check -- lanes/lightningcss` -> passed.

Non-overlap:

This slice does not add CSS parser, minifier, bundler, CSS Modules, source-map, CSSOM, media-query, or target-prefix behavior. It only sharpens the current Rust/Node/WASM runner dependency closure evidence. Conservative mapped behavior coverage remains `2439 / 3532`.

Dependency closure:

No new PHP support component is required. Full upstream runner closure needs the upstream Node dependencies installed from the pinned manifest (`detect-libc`, `uvu`, `napi-wasm`) plus external `wasm-opt` from Binaryen for the WASM build scripts. After those are present, rerun the full Node uvu suite and WASM runtime/build checks.
