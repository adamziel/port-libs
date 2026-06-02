Gitoxide Cargo target materialization wrap-up, 2026-06-02

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Current blocker carried forward from the accepted Cargo workspace evidence:
  full offline `cargo test --workspace --locked --offline --no-run` fails
  during target resolution when the sparse cache lacks declared test/example
  target source files.
- Native PHP delta: added `CargoTargetMaterializer`, which takes the accepted
  16 source-available target records, reads blob objects through the native
  `ObjectDatabase`, verifies SHA-1 blob id, byte count, line count, and clean
  relative paths, then materializes them into a caller-supplied absolute
  checkout root. Existing matching files are idempotent; divergent files are
  blocked unless overwrite is explicit.
- Current target plan: 16 targets, 10 workspace-no-run blockers, 6
  non-blocking bench/feature-gated targets, 38,927 bytes, 1,254 lines, across
  10 target directories.
- Local source-truth smoke:
  `php -r 'require "tools/bootstrap.php"; $dest = sys_get_temp_dir() . "/port-libs-gitoxide-current-cargo-targets-" . bin2hex(random_bytes(4)); $report = PortLibs\Gitoxide\CargoTargetMaterializer::materializeFromObjectDatabase(new PortLibs\Gitoxide\ObjectDatabase("/home/claude/port-libs/.upstream-cache/gitoxide/.git"), $dest); echo $report["materializedTargets"] . " targets, " . $report["writtenTargets"] . " written, " . $report["totalBytes"] . " bytes, " . $report["totalLines"] . " lines at " . $dest . "\n";'`
  Result: `16 targets, 16 written, 38927 bytes, 1254 lines`.
- Focused verification:
  `php tools/run-tests.php lanes/gitoxide/tests/CargoWorkspaceEvidenceTest.php`
  passed `1 test files, 174 assertions, 0 failures`.
- Full Gitoxide lane verification:
  `php tools/run-tests.php lanes/gitoxide/tests` passed `41 test files, 11007
  assertions, 0 failures`.
- PHP lint passed for changed source/test files. `git diff --check --
  lanes/gitoxide` passed.

Dependency closure:

- No new native support component outside the Gitoxide lane is needed. The
  blocker is runner-source materialization, now represented by a native PHP
  materializer. Integrator can point it at the pinned upstream `.git` object
  database and a sparse checkout root, then rerun
  `CARGO_TARGET_DIR=/tmp/port-libs-gitoxide-cargo-workspace-target timeout 180
  cargo test --workspace --locked --offline --no-run`.
