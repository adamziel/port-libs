Gitoxide Cargo workspace evidence wrap-up, 2026-06-01

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Offline metadata command:
  `timeout 60 cargo metadata --locked --offline --format-version 1 --no-deps`.
  Result: exit 0; 70 packages, 70 workspace members, 126 declared targets, and
  101 declared test-capable targets.
- Full workspace no-run command:
  `CARGO_TARGET_DIR=/tmp/port-libs-gitoxide-cargo-workspace-target timeout 180 cargo test --workspace --locked --offline --no-run`.
  Result: exit 101 before compilation due to sparse-cache target-resolution
  errors. Cargo reported missing default workspace test/example targets in
  `gix`, `gix-error`, `gix-features`, `gix-odb`, and `gix-tempfile`.
- Declared target filesystem inventory found 16 missing source paths. Ten block
  the full workspace no-run probe immediately; the remaining six are benches or
  feature-gated/non-default transport tests that did not appear in the default
  workspace no-run error set.
- Bounded fallback no-run command:
  `CARGO_TARGET_DIR=/tmp/port-libs-gitoxide-default-target timeout 180 cargo test --locked --offline --no-run`.
  Result: exit 0; default `gitoxide` package test profile built in 50.13s and
  produced the `unittests src/lib.rs` executable.
- Bounded fallback runnable command:
  `CARGO_TARGET_DIR=/tmp/port-libs-gitoxide-default-target timeout 120 cargo test --locked --offline --lib`.
  Result: exit 0; 4 passed, 0 failed, 0 ignored, 0 measured, 0 filtered out.

Native PHP evidence delta:

- Added `PortLibs\Gitoxide\CargoWorkspaceEvidence` to preserve the current
  pinned upstream workspace admission status, missing-target blocker set, and
  bounded Cargo fallback pass.
- Added focused `CargoWorkspaceEvidenceTest.php` coverage so the lane can
  assert the workspace evidence without re-running the broad Cargo workspace.
- Focused PHP verification passed `1 test files, 37 assertions, 0 failures`.
  Full Gitoxide PHP lane verification passed `41 test files, 10817 assertions,
  0 failures`; `phpPass` moves from `10780` to `10817` while mapped coverage
  remains `1819 / 2886`.

Dependency closure:

- No new native PHP support component is needed. The blocker is upstream-cache
  hydration, not PHP port behavior: hydrate the missing Cargo target source
  files or run against a complete upstream checkout before attempting full
  `cargo test --workspace --locked --offline --no-run` again.
