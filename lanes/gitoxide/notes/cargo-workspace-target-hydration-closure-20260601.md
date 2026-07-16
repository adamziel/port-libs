Gitoxide Cargo workspace target hydration closure, 2026-06-01

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Prior blocker: the sparse upstream cache did not materialize 16 declared
  Cargo target paths. Ten of those paths blocked
  `cargo test --workspace --locked --offline --no-run` during target
  resolution before compilation started.
- Hydration closure evidence: `git ls-tree -r HEAD -- <missing target paths>`
  and `git cat-file -s/-p HEAD:<path>` prove all 16 sparse-cache missing
  targets are present as blobs in the pinned upstream commit. The focused PHP
  evidence records the manifest path, blob id, byte length, line count, target
  kind, and whether the target blocks default workspace no-run.
- Blocking source-available targets: `gix/examples/clone.rs`,
  `gix-error/tests/auto_chain_error.rs`,
  `gix-features/tests/parallel_shared.rs`,
  `gix-features/tests/parallel_shared_threaded.rs`,
  `gix-features/tests/parallel_threaded.rs`,
  `gix-features/tests/pipe.rs`, `gix-odb/tests/odb/main.rs`,
  `gix-tempfile/examples/delete-tempfiles-on-sigterm.rs`,
  `gix-tempfile/examples/delete-tempfiles-on-sigterm-interactive.rs`, and
  `gix-tempfile/examples/try-deadlock-on-cleanup.rs`.
- Nonblocking source-available targets: `gix-config/benches/large_config_file.rs`,
  `gix-diff/benches/line_count.rs`, `gix-glob/benches/wildmatch.rs`,
  `gix-index/benches/from_tree.rs`,
  `gix-transport/tests/async-transport.rs`, and
  `gix-transport/tests/blocking-transport-http.rs`.

Native PHP evidence delta:

- `PortLibs\Gitoxide\CargoWorkspaceEvidence` now exposes
  `targetHydrationClosure()`, `targetHydrationStatus()`, and
  `hydratableTargetSources()` so the lane can assert the closure without
  mutating the shared sparse upstream cache.
- `CargoWorkspaceEvidenceTest.php` now verifies all 16 missing target paths are
  covered by pinned blob metadata and preserves the 10 blocking / 6 nonblocking
  split.
- Focused verification before this patch: `php tools/run-tests.php
  lanes/gitoxide/tests/CargoWorkspaceEvidenceTest.php` passed `1 test files,
  37 assertions, 0 failures`.
- Focused verification after this patch: `php tools/run-tests.php
  lanes/gitoxide/tests/CargoWorkspaceEvidenceTest.php` passed `1 test files,
  131 assertions, 0 failures`.
- Full Gitoxide PHP lane verification after this patch: `php tools/run-tests.php
  lanes/gitoxide/tests` passed `41 test files, 10934 assertions, 0 failures`.

Remaining gate:

- The sparse cache itself was not mutated by this worker. The full Cargo
  workspace no-run gate still needs a materialized sparse checkout or complete
  checkout, then:
  `CARGO_TARGET_DIR=/tmp/port-libs-gitoxide-cargo-workspace-target timeout 180 cargo test --workspace --locked --offline --no-run`.
- This slice closes the missing-target source-truth question; it does not claim
  full workspace compile/test parity.

Dependency closure:

- No new native PHP support component is needed. The remaining work is runner
  materialization and full-workspace Cargo evidence, not PHP port behavior.
