# Independent Audit - 2026-05-23T14:09:17Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
all 12 `lanes/*/lane-status.json` files, recent Git history, dirty tree
state, active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `ac948ba08ddf` (`Refresh independent audit
status`). Recent history reviewed includes `ac948ba0`, `4ba8b4f4`,
`37bbfa36`, `005fd686`, `79a7e66a`, `9dfec34f`, `1c06a555`, `85dcd312`,
`66798317`, `be7cf14f`, `c1f67cda`, `d52cc007`, `24260634`, `51867989`,
`b75226d1`, `30be5e3c`, `90d1fa3b`, `81419ac3`, `69405063`, `0f1444c1`,
`efa4e0c2`, `f8bd46e4`, `09995598`, `a04f2c8b`, `5b6d5a84`, `957f8587`,
`f40a591b`, `e6182a8c`, `9f4f13f`, and `e77d40c2`. The latest history is
still dominated by audit/status commits, with a few rclone, readability,
libsqlite, and syncthing implementation commits mixed in.

## Findings

1. **Critical - the repository is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json:13`, `porting.html:32`-`36`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`, `goal.md:44`,
     `goal.md:48`, and `goal.md:49` require capped active work, current
     owner/session tracking, small reviewable committed slices, supervisor
     verification, and honest repo-wide test evidence.
   - Evidence: `progress.md:25` still declares a launch target of two
     implementation lanes plus one auditor, while `progress.md:31`-`42`
     reports every lane session as `stopped`.
   - Evidence: process sampling found active `run-tmux-agent.sh` loops for
     LightningCSS, libsqlite, Gitoxide, Pandoc, Readability, Quadrable, rclone,
     Dolt, Dolt runner, markerPDF, Difftastic, Syncthing, auditor, integrator,
     esbuild, and three capacity PHP shards, plus watchdog, evaluator,
     capacity, and dashboard updater loops.
   - Evidence: dirty samples reported `1784` default
     `git status --short --untracked-files=all` rows, `177` tracked changed
     files, and later `178 files changed, 63357 insertions(+), 5274
     deletions(-)`.
   - Audit judgment: aggregate acceptance remains blocked until active writers
     and broad runners are frozen, then one regenerated snapshot is accepted or
     rejected lane by lane.

2. **High - the published dashboard is stale and violates the dashboard
   contract.**
   - Paths: `porting.html:32`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, `porting-summary.json`, current
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `ac948ba08ddf`.
   - Evidence: dashboard rows materially disagree with current files. Examples:
     rclone publishes `327 benchmark`, `291 pass`, and `291 / 327 mapped` at
     `porting.html:63`, while the current manifest reports `2553` total and
     `488` mapped at `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
     markerPDF publishes `78 benchmark`, `264 pass`, and `159 / 78 mapped` at
     `porting.html:60`, while the current manifest reports `279` total,
     `227` mapped, and the lane status reports `341` PHP passes at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`18` and
     `lanes/markerpdf/lane-status.json:4`-`7`. Syncthing publishes `235 pass`
     and `235 / 658 mapped` at `porting.html:65`, while the current status
     reports `356` PHP passes at `lanes/syncthing/lane-status.json:4`-`7`.
   - Evidence: the table collapses required fields into combined `Benchmark`
     and `Mapped` columns at `porting.html:41`-`50`, so it is not the
     per-field dashboard requested in the goal.

3. **High - high progress percentages are attached to unaccepted dirty
   batches.**
   - Paths: `lanes/difftastic/lane-status.json:4` and `:13`,
     `lanes/rclone/lane-status.json:4` and `:13`,
     `lanes/markerpdf/lane-status.json:4` and `:13`,
     `lanes/syncthing/lane-status.json:4` and `:13`,
     `progress.md:31`-`42`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, `goal.md:36`, and
     `goal.md:48` require small committed native slices, meaningful acceptance
     beyond passing tests, and supervisor verification before assigning next
     work.
   - Evidence: lane status files report very high estimates such as Difftastic
     `92%`, Dolt `93%`, Gitoxide/libsqlite/rclone/Syncthing `98%`, markerPDF
     and Readability `92%`, Pandoc `95%`, and Quadrable `99%`, while
     `progress.md:31`-`42` still reports old `5%` to `66%` estimates.
   - Evidence: most `latestCommit` fields are still pending or prose
     dirty-batch handoffs. Difftastic says "pending in shared dirty worktree",
     rclone says "pending lane-local changes", markerPDF says "uncommitted lane
     batch", Syncthing says "pending", and several other lanes have similar
     non-commit values.

4. **High - root-test evidence is stale or non-comparable for the current
   dirty snapshot.**
   - Paths: `lanes/difftastic/lane-status.json:10`-`12`,
     `lanes/markerpdf/lane-status.json:10`-`12`,
     `lanes/rclone/lane-status.json:10`-`12`,
     `lanes/syncthing/lane-status.json:10`-`12`, `progress.md:15`,
     `progress.md:25`, `porting.html:54`-`65`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:49`, and `goal.md:52`
     require precise blockers, honest failure recording, periodic repo-wide
     tests/static checks, and visible current progress.
   - Evidence: the required duplicate-root gate initially returned active root
     PID `1030529 php tools/run-tests.php`; it exited before owner sampling.
     Later process sampling found active root PID `1035563` owned by `claude`
     with command `php tools/run-tests.php`. Later exact gates cleared, but
     active Dolt BATS and lane/status agents persisted.
   - Evidence: lane records disagree about root status in the same dirty
     aggregate. Difftastic records a no-argument root failure caused by rclone;
     markerPDF records a green root run; Syncthing records a green root run;
     rclone records aggregate root pending behind a prior active PID. These
     are not comparable evidence for sampled `HEAD` plus the current dirty
     tree.

5. **High - manifest/status count schemas still cannot support trustworthy
   portfolio math.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12`-`17`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`-`18`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12`-`15`,
     `lanes/*/lane-status.json:4`-`7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require a real upstream denominator, mapped upstream tests,
     PHP passing/failing counts, and dashboard fields backed by comparable
     values.
   - Evidence: some `benchmarkDenominator.total` values are prose strings
     rather than numbers, e.g. Difftastic at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`. Others use numeric
     totals, so generated portfolio math is mixing schemas.
   - Evidence: mapped units and PHP pass units are still different measures:
     markerPDF has `227` mapped static units but `341` PHP passes; Readability
     has `1682` mapped units but `154` PHP passes; LightningCSS has `1262`
     mapped checks while the status reports `1396` PHP passes/assertions;
     Gitoxide has `1959` mapped units while the status reports `3662` PHP
     passes. These numbers may each be useful locally, but they are not one
     normalized denominator/mapped/pass/fail schema.

6. **Medium - hard upstream-runner and hard-feature gaps are softened by high
   percentages.**
   - Paths: `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require precise blockers, meaningful fixture parity,
     upstream tests as source of truth, and explicit future slices for hard
     features.
   - Evidence: Difftastic lacks full Cargo parity, Gitoxide lacks full cargo
     workspace parity, markerPDF has not executed full benchmarks, top-level
     multiprocessing, or model-heavy conversion paths, Pandoc lacks built
     Haskell upstream runner parity, rclone excludes live providers/mount/FUSE,
     Syncthing lacks full `go test ./...`, and libsqlite still defers WAL
     checkpoint/read-mark, rollback journals, broader writes, and general SQL
     execution. These are valid blockers, but 92%-99% lane percentages make
     them look nearly done.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed exact root evidence in this audit:

```text
1030529 php tools/run-tests.php
1035563 claude 1019446 00:31 Rs php tools/run-tests.php
```

The first process exited before owner sampling; the later process sample
captured owner evidence for another exact no-argument root harness. Later exact
gates returned no rows, but no root run was started because duplicate-root
activity had already been observed during this audit and the tree was still not
stable. Active process evidence also included Dolt BATS and many lane/status
agents, for example:

```text
937373 claude bash ... run-tmux-agent.sh port-lightningcss ...
938395 claude bash ... run-tmux-agent.sh port-libsqlite ...
940721 claude bash ... run-tmux-agent.sh port-gitoxide ...
942202 claude bash ... run-tmux-agent.sh port-pandoc ...
1019235 claude bash ... run-tmux-agent.sh port-readability ...
1019337 claude bash ... run-tmux-agent.sh port-quadrable ...
1019642 claude bash ... run-tmux-agent.sh port-rclone ...
1019708 claude bash ... run-tmux-agent.sh port-dolt ...
1024401 claude bash ... run-tmux-agent.sh port-markerpdf ...
1024601 claude bash ... run-tmux-agent.sh port-difftastic ...
1032244 claude bash ... run-tmux-agent.sh port-syncthing ...
1032796 claude bash ... run-tmux-agent.sh port-auditor ...
1032884 claude bash ... run-tmux-agent.sh port-integrator ...
1035693 claude bash ... run-tmux-agent.sh port-esbuild ...
1036684 claude timeout 90m bats verify-constraints.bats ...
2347911 claude bash scripts/run-team-watchdog.sh
2424048 claude bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
2452997 claude bash scripts/run-capacity-controller-loop.sh
2479222 claude bash scripts/run-dashboard-updater-loop.sh
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
find lanes -path '*/UPSTREAM_TEST_MANIFEST.json' -print | sort
git log --oneline --decorate -n 30 --no-abbrev-commit
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -eo pid,user,ppid,etime,stat,args | rg 'run-tmux-agent|run-capacity-controller-loop|run-dashboard-updater-loop|run-evaluator-loop|run-team-watchdog|php tools/run-tests\.php|testrunner\.tcl|bats |go test|cargo test|npm test'
```

Results: all lane upstream manifests, all lane status files, and
`porting-summary.json` parsed as valid JSON. All 12 lane manifests were found.
Latest samples reported `1784` default status rows, `177` tracked changed
files, and later `178 files changed, 63357 insertions(+), 5274 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped,
PHP pass/fail, runner, progress, scenario, and commit fields, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same accepted snapshot, rerun the exact duplicate-root gate, and capture
one quiesced no-argument `php tools/run-tests.php` result.
