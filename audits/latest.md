# Independent Audit - 2026-05-23T13:59:09Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, dirty tree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `37bbfa365ffa` (`Refresh independent audit
status`). Recent history reviewed includes `37bbfa36`, `005fd686`,
`79a7e66a`, `9dfec34f`, `1c06a555`, `85dcd312`, `66798317`,
`be7cf14f`, `c1f67cda`, `d52cc007`, `24260634`, `51867989`,
`b75226d1`, `30be5e3c`, `90d1fa3b`, and `81419ac3`.

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
   - Evidence: process sampling found active lane/watchdog/status work despite
     the stopped-lane table, including Dolt BATS PID `575005` with child BATS
     processes, lane agents for Syncthing, esbuild, LightningCSS, libsqlite,
     Difftastic, Gitoxide, Readability, Quadrable, Dolt, Pandoc, rclone, the
     auditor/integrator, and long-running evaluator/capacity/dashboard loops.
   - Evidence: dirty-tree samples reported `1763` default
     `git status --short --untracked-files=all` rows, `177` tracked changed
     files, and `177 files changed, 62117 insertions(+), 5417 deletions(-)`.
   - Audit judgment: aggregate acceptance remains blocked until active writers
     and broad upstream runners are frozen, then a single regenerated snapshot
     is validated and accepted lane by lane.

2. **High - the published dashboard is stale and violates the dashboard
   contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, `porting-summary.json`, current
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `37bbfa365ffa`.
   - Evidence: `porting.html:41`-`50` still collapses required fields into
     compact `Benchmark` and `Mapped` columns instead of separate benchmark
     source, upstream denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows materially disagree with current manifests and
     lane statuses. Examples: Difftastic publishes `417 / 160 / 160` while the
     current files report `588 artifacts / 276 mapped / 276 PHP`; rclone
     publishes `327 / 291 / 291` while current evidence is `2553 / 486 / 486`;
     markerPDF publishes `78 / 159 / 264` while current evidence is
     `278 / 226 / 340`; LightningCSS publishes `773 mapped / 906 PHP` while
     current evidence is `1245 mapped / 1379 PHP`; Readability publishes
     `1031 mapped / 107 PHP` while current evidence is `1652 / 153`.

3. **High - high progress percentages are attached to unaccepted dirty
   batches.**
   - Paths: `lanes/difftastic/lane-status.json:4` and `:13`,
     `lanes/dolt/lane-status.json:4` and `:13`,
     `lanes/gitoxide/lane-status.json:4` and `:13`,
     `lanes/libsqlite/lane-status.json:4` and `:13`,
     `lanes/rclone/lane-status.json:4` and `:13`,
     `progress.md:31`-`42`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, `goal.md:36`, and
     `goal.md:48` require small committed native slices, meaningful acceptance
     beyond passing tests, and supervisor verification before assigning the
     next work.
   - Evidence: lane status files now report very high estimates such as
     Quadrable `99%`, Gitoxide/libsqlite/rclone/Syncthing `98%`, Pandoc `95%`,
     Dolt `92%`, Difftastic/markerPDF/Readability `91%`, and LightningCSS
     `84%`, while `progress.md:31`-`42` still shows old `5%` to `66%`
     estimates.
   - Evidence: `latestCommit` fields are mostly pending or dirty-batch prose:
     Difftastic says pending in a shared dirty worktree, Dolt says not
     committed, Gitoxide says pending, libsqlite says uncommitted, rclone says
     pending lane-local changes, and markerPDF/readability/esbuild also record
     uncommitted slices.
   - Evidence: recent Git history is dominated by audit/status commits. Only a
     small number of implementation commits appear in the latest 30 commits,
     so the current portfolio state is not an accepted sequence of small lane
     slices.

4. **High - root-test evidence is stale or non-comparable for the current
   dirty snapshot.**
   - Paths: `lanes/*/lane-status.json:10`-`12`, `progress.md:324` and
     `progress.md:325`,
     `porting.html:54`-`65`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:49`, and `goal.md:52`
     require precise blockers, honest failure recording, periodic repo-wide
     tests/static checks, and visible current progress.
   - Evidence: the final required exact duplicate-root gate returned active
     no-argument root harness PID `939608`, owned by `claude`, so a duplicate
     root run was forbidden. Earlier gates alternated between empty and
     transient active root PIDs.
   - Evidence: lane records mix focused lane passes, stale root anecdotes,
     pre-slice aggregate results, and root-pending statements. Readability
     explicitly says an accidental root pass predates its slice; markerPDF
     records an active focused/root PHP PID `863332` at its handoff; several
     lanes defer root because Dolt BATS, Syncthing Go, rclone Go, Gitoxide
     Cargo, or other broad runners were active.
   - Audit judgment: no current aggregate PHP result should be promoted until
     the exact gate is clear and the tree is quiesced.

5. **High - manifest/status count schemas still cannot support trustworthy
   portfolio math.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/*/lane-status.json:5`-`7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require a real upstream denominator, mapped upstream tests,
     PHP passing/failing counts, and generated dashboard fields backed by
     comparable values.
   - Evidence: `benchmarkDenominator.total` mixes numbers with long prose
     strings. Difftastic, Dolt, esbuild, Pandoc, and Quadrable use prose
     strings, while other lanes use numbers.
   - Evidence: mapped units and PHP pass units are often different measures:
     markerPDF has `226` mapped units but `340` PHP passes; Gitoxide has
     `1955` mapped units but `3646` PHP assertions/passes in lane status;
     Readability has `1652` mapped units but `153` PHP behavior tests; many
     manifests omit `nativeImplementation.phpBehaviorTests` entirely.
   - Evidence: WordPress scenario fields are still long prose strings, not
     stable denominator/count records, so the dashboard cannot distinguish one
     broad scenario from many accepted scenarios.

6. **Medium - hard upstream-runner and hard-feature gaps are softened by high
   percentages.**
   - Paths: `lanes/difftastic/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
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
   - Evidence: Difftastic lacks full Cargo parity, esbuild still excludes
     release-extra `make test-all`, Gitoxide lacks full cargo workspace parity,
     markerPDF has not run full benchmark/workflow/model-heavy conversion
     paths, Pandoc lacks built Haskell upstream runner parity, rclone excludes
     live provider/mount/FUSE coverage, Syncthing lacks full `go test ./...`,
     and libsqlite still defers WAL/checkpoint/rollback-journal and broader SQL
     execution. These can be valid blockers or future slices, but not hidden
     beneath 90%+ portfolio percentages without normalized accepted
     denominators.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Latest exact gate result in this audit:

```text
939608 php tools/run-tests.php
939608 claude 879649 00:05 Rs php tools/run-tests.php
```

No root run was started because the final exact gate found another active
no-argument root harness. The stability condition also failed because active
broad-runner/status evidence included:

```text
575005 claude timeout 90m bats verify-constraints.bats ...
575036 claude /usr/bin/bash /usr/libexec/bats-core/bats ...
575043 claude /usr/bin/bash /usr/libexec/bats-core/bats-exec-suite ...
575044 claude /usr/bin/bash /usr/libexec/bats-core/bats ...
666262 claude bash ... run-tmux-agent.sh port-syncthing ...
707343 claude bash ... run-tmux-agent.sh port-esbuild ...
724819 claude bash ... run-tmux-agent.sh port-lightningcss ...
725043 claude bash ... run-tmux-agent.sh port-libsqlite ...
755365 claude bash ... run-tmux-agent.sh port-difftastic ...
822203 claude bash ... run-tmux-agent.sh port-gitoxide ...
879533 claude bash ... run-tmux-agent.sh port-readability ...
879952 claude bash ... run-tmux-agent.sh port-quadrable ...
880894 claude bash ... run-tmux-agent.sh port-dolt ...
906984 claude bash ... run-tmux-agent.sh port-pandoc ...
907297 claude bash ... run-tmux-agent.sh port-integrator ...
927890 claude bash ... run-tmux-agent.sh port-rclone ...
928252 claude bash ... run-tmux-agent.sh port-auditor ...
933687 claude npm test --grep webmd
939608 claude php tools/run-tests.php
2424048 claude bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
2452997 claude bash scripts/run-capacity-controller-loop.sh
2479222 claude bash scripts/run-dashboard-updater-loop.sh
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -eo pid,user,ppid,etime,stat,args | rg 'run-tmux-agent|run-capacity-controller-loop|run-dashboard-updater-loop|run-evaluator-loop|run-integrator|auditor|artifact|verifier|php tools/run-tests\.php|testrunner\.tcl|bats |go test|cargo test|npm test'
git log --oneline --decorate -n 30 --no-abbrev-commit
rg -n 'proc_open|shell_exec|passthru\(|system\(|\bexec\(' lanes/*/src lanes/*/tests lanes/*/examples
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON. Latest samples reported `1763`
default status rows, `177` tracked changed files, and `177 files changed,
62117 insertions(+), 5417 deletions(-)`. The implementation shell-out scan
found only `PDO::exec` calls in `lanes/syncthing/src/SqliteCheckpointStore.php`,
not process shell-outs.

## Next Intervention

Freeze active writers/status publishers and broad upstream runners first. Then
validate manifests from the frozen tree, accept or reject dirty lane batches
one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, scenario, and commit fields, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same accepted snapshot, rerun the exact duplicate-root gate, and capture
one quiesced no-argument `php tools/run-tests.php` result.
