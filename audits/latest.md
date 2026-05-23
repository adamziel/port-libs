# Independent Audit - 2026-05-23T14:17:45Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
all 12 `lanes/*/lane-status.json` files, recent Git history, dirty tree
state, active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `8bfcb55106c2` (`Refresh independent audit
status`). Recent history reviewed includes `8bfcb551`, `ac948ba0`,
`4ba8b4f4`, `37bbfa36`, `005fd686`, `79a7e66a`, `9dfec34f`, `1c06a555`,
`85dcd312`, `66798317`, `be7cf14f`, `c1f67cda`, `d52cc007`, `24260634`,
`51867989`, `b75226d1`, `30be5e3c`, `90d1fa3b`, `81419ac3`, `69405063`,
`0f1444c1`, `efa4e0c2`, `f8bd46e4`, `09995598`, `a04f2c8b`, `5b6d5a84`,
`957f8587`, `f40a591b`, `e6182a8c`, `9f4f13f`, and `e77d40c2`.

## Findings

1. **Critical - the repository is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json:10`-`13`, `porting.html:32`-`36`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped active
     work, current owner/session tracking, small committed slices, supervisor
     verification, and honest repo-wide test evidence.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` still
     reports every lane session as `stopped`.
   - Evidence: process sampling found active lane/auditor/integrator agents,
     team watchdog, evaluator, capacity controller, dashboard updater, Dolt
     BATS, and SQLite `testrunner.tcl --jobs 10` processes owned by `claude`.
     Examples include PIDs `1036684`/`1036713`/`1036720` for Dolt BATS,
     PIDs `1057838`/`1057839` for SQLite upstream runner, and active
     `run-tmux-agent.sh` loops for esbuild, LightningCSS, gitoxide,
     markerPDF, libsqlite, readability, pandoc, quadrable, rclone, dolt,
     syncthing, auditor, and integrator.
   - Evidence: latest dirty samples reported `1778` default
     `git status --short` rows, `181` tracked changed files, and
     `181 files changed, 64185 insertions(+), 5223 deletions(-)`.
   - Audit judgment: aggregate acceptance remains blocked until active
     writers and broad runners are frozen, then one regenerated snapshot is
     accepted or rejected lane by lane.

2. **High - the published dashboard is stale and violates the dashboard
   contract.**
   - Paths: `porting.html:32`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, `porting-summary.json:1`-`90`,
     current `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with benchmark source, upstream denominator, mapped tests, PHP
     pass/fail, WordPress scenarios, phase, audit, current work, blocker, and
     commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`4` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `8bfcb55106c2`.
   - Evidence: dashboard rows materially disagree with current files.
     Rclone publishes `327 benchmark`, `291 pass`, and `291 / 327 mapped` at
     `porting.html:63`, while the current manifest reports `2553` total and
     `492` mapped at `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`, and
     status reports `492` PHP passes at `lanes/rclone/lane-status.json:6`.
     markerPDF publishes `78 benchmark`, `264 pass`, and `159 / 78 mapped` at
     `porting.html:60`, while the current manifest reports `280` total and
     `228` mapped at `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     and status reports `342` PHP passes at
     `lanes/markerpdf/lane-status.json:6`. Syncthing publishes `235 pass` and
     `235 / 658 mapped` at `porting.html:65`, while current files report
     `359` mapped and `359` PHP passes at
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15` and
     `lanes/syncthing/lane-status.json:6`.
   - Evidence: `porting.html:41`-`50` still collapses required dashboard
     fields into combined `Benchmark` and `Mapped` cells, so upstream
     denominator, mapped tests, and PHP pass/fail are not first-class columns.

3. **High - root-test evidence is contradictory and non-comparable for the
   current dirty tree.**
   - Paths: `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/quadrable/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:49`, and
     `goal.md:52` require precise blockers, honest repo-wide test/static
     check recording, and visible current status.
   - Evidence: libsqlite records a red no-argument root run with `231` test
     files, `27884` assertions, and `2` failures at
     `lanes/libsqlite/lane-status.json:10`-`13`. Quadrable records a green
     repo-wide root run with `231` files, `27984` assertions, and `0`
     failures at `lanes/quadrable/lane-status.json:10`, and LightningCSS
     records another green root run with `231` files, `27965` assertions, and
     `0` failures at `lanes/lightningcss/lane-status.json:10`.
   - Evidence: several other lanes record root pending or duplicate-gated
     status because broad Dolt/SQLite runners were active, including
     gitoxide, dolt, rclone, readability, markerPDF, esbuild, and syncthing.
   - Audit judgment: these are different moving-tree samples. None should be
     treated as the accepted aggregate baseline for sampled `HEAD` plus the
     current dirty worktree.

4. **High - high lane progress percentages are attached to unaccepted dirty
   batches.**
   - Paths: `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:13`,
     `progress.md:31`-`42`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, `goal.md:36`, and
     `goal.md:48` require small committed native slices, quality beyond
     passing tests, and supervisor verification before assigning next work.
   - Evidence: lane status files now claim very high progress: Difftastic and
     markerPDF `92%`, Dolt and Readability `93%`, Pandoc `95%`, Gitoxide,
     libsqlite, rclone, and Syncthing `98%`, and Quadrable `99%`.
     Meanwhile `progress.md:31`-`42` still reports old estimates from `5%` to
     `66%`.
   - Evidence: every sampled `latestCommit` field is pending, prose, or an
     unaccepted dirty-batch handoff rather than a clean accepted commit ID.
     Examples include Difftastic "pending in shared dirty worktree",
     Gitoxide "pending", libsqlite "uncommitted lane-scoped changes", rclone
     "pending lane-local changes", Readability "uncommitted", Syncthing
     "pending", and Dolt "not committed".

5. **High - manifest/status count schemas still cannot support trustworthy
   portfolio math.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/*/lane-status.json:4`-`7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require a real upstream denominator,
     mapped upstream tests, PHP passing/failing counts, and dashboard fields
     backed by comparable values.
   - Evidence: several `benchmarkDenominator.total` values are prose strings
     instead of numeric denominators: Difftastic, Dolt, esbuild, Pandoc, and
     Quadrable. Other lanes use numbers, so automated dashboard math mixes
     schemas.
   - Evidence: mapped units and PHP pass units are different measures. For
     example, Gitoxide reports `1972` mapped units in its manifest but `3680`
     PHP passes/assertions in lane status; LightningCSS reports `1266` mapped
     units but `1396` PHP passes/assertions; Readability reports `1697`
     mapped units but `155` PHP tests; Pandoc reports `682` mapped units but
     `221` PHP tests. These may be locally useful, but they are not a
     normalized denominator/mapped/pass/fail schema.

6. **Medium - hard upstream-runner and hard-feature gaps are softened by
   near-complete percentages.**
   - Paths: `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require precise blockers, meaningful
     fixture parity, upstream tests as source of truth, and explicit future
     slices for hard features.
   - Evidence: Difftastic lacks full Cargo parity, Gitoxide lacks full cargo
     workspace parity, markerPDF has not executed full benchmarks or
     model-heavy conversion paths, Pandoc lacks built Haskell upstream runner
     parity, rclone excludes live providers and mount/FUSE suites, Syncthing
     lacks full `go test ./...`, and libsqlite still defers WAL checkpoint
     mutation, rollback journals, broader writes, general SQL execution, and
     broader corruption validation. These blockers are real; 92%-99% lane
     percentages make them look nearly done.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed result for the final pre-edit exact gate in this audit: no rows.
Post-edit validation gates briefly returned focused lane PID `1353081`
(`php tools/run-tests.php lanes/quadrable/tests`), which exited before owner
sampling, and focused lane PID `1371350` owned by `claude`
(`php tools/run-tests.php lanes/syncthing/tests`). A subsequent exact gate
returned no rows.

No root run was started because the tree was not stable enough. Active process
evidence included broad upstream runners and many agent/status loops, for
example:

```text
1036684 claude timeout 90m bats verify-constraints.bats ...
1036713 claude /usr/bin/bash /usr/libexec/bats-core/bats ...
1036720 claude /usr/bin/bash /usr/libexec/bats-core/bats-exec-suite ...
1057838 claude timeout 7200 ./testfixture ../src/test/testrunner.tcl --jobs 10 --stop-on-error all
1057839 claude ./testfixture ../src/test/testrunner.tcl --jobs 10 --stop-on-error all
1277936 claude bash ... run-tmux-agent.sh port-gitoxide ...
1278036 claude bash ... run-tmux-agent.sh port-markerpdf ...
1278178 claude bash ... run-tmux-agent.sh port-libsqlite ...
1278407 claude bash ... run-tmux-agent.sh port-readability ...
1278598 claude bash ... run-tmux-agent.sh port-pandoc ...
1278858 claude bash ... run-tmux-agent.sh port-quadrable ...
1279108 claude bash ... run-tmux-agent.sh port-rclone ...
1279335 claude bash ... run-tmux-agent.sh port-dolt ...
1279556 claude bash ... run-tmux-agent.sh port-auditor ...
1279693 claude bash ... run-tmux-agent.sh port-integrator ...
1289923 claude bash ... run-tmux-agent.sh port-syncthing ...
2347911 claude bash scripts/run-team-watchdog.sh
2424048 claude bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
2452997 claude bash scripts/run-capacity-controller-loop.sh
2479222 claude bash scripts/run-dashboard-updater-loop.sh
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
rg --files lanes | rg '/UPSTREAM_TEST_MANIFEST\.json$'
git log --oneline --decorate --no-abbrev-commit -n 35
git status --short --untracked-files=no | wc -l
git status --short | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -eo pid,user,ppid,etime,stat,args | rg 'run-tmux-agent|run-capacity-controller-loop|run-dashboard-updater-loop|run-evaluator-loop|run-team-watchdog|run-tests\.php|testrunner\.tcl|bats |go test|cargo test|npm test'
```

Results: all lane upstream manifests, all lane status files, and
`porting-summary.json` parsed as valid JSON. All 12 lane manifests were found.
Latest samples reported `1778` default status rows, `181` tracked changed
files, and `181 files changed, 64185 insertions(+), 5223 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize manifest/status denominator,
mapped, PHP pass/fail, runner, progress, scenario, and commit fields,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from that same accepted snapshot, rerun the exact duplicate-root
gate, and capture one quiesced no-argument `php tools/run-tests.php` result.
