# Independent Audit - 2026-05-23T14:03:28Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
all `lanes/*/lane-status.json` files, recent Git history, dirty tree state,
active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `4ba8b4f4a069` (`Refresh independent audit
status`). Recent history reviewed includes `4ba8b4f4`, `37bbfa36`,
`005fd686`, `79a7e66a`, `9dfec34f`, `1c06a555`, `85dcd312`, `66798317`,
`be7cf14f`, `c1f67cda`, `d52cc007`, `24260634`, `51867989`, `b75226d1`,
`30be5e3c`, `90d1fa3b`, and `81419ac3`. The latest 12 commits are audit-only
updates; only a few implementation commits appear in the latest 30 commits.

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
   - Evidence: process sampling found active agents/status publishers and
     upstream runners despite that table, including `run-tmux-agent.sh` for
     Difftastic, Readability, Quadrable, Dolt, rclone, markerPDF, Syncthing,
     esbuild, LightningCSS, libsqlite, Gitoxide, Pandoc, the auditor, and the
     integrator; long-running `run-team-watchdog.sh`, evaluator, capacity, and
     dashboard loops were also active.
   - Evidence: dirty-tree samples reported `1764` default
     `git status --short --untracked-files=all` rows, `175` tracked changed
     files, and `175 files changed, 62586 insertions(+), 5298 deletions(-)`.
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
     `bda83c6b93d4`, while sampled `HEAD` is `4ba8b4f4a069`.
   - Evidence: `porting.html:41`-`50` still collapses required fields into
     compact `Benchmark` and `Mapped` columns instead of separate benchmark
     source, upstream denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows materially disagree with current manifests and
     lane statuses. Examples: Difftastic publishes `417 / 160 / 160` while the
     current files report `589 / 279 / 279`; rclone publishes
     `327 / 291 / 291` while current evidence is `2553 / 488 / 488`;
     markerPDF publishes `78 / 159 / 264` while current evidence is
     `279 / 227 / 341`; Syncthing publishes `658 / 235 / 235` while current
     evidence is `658 / 355 / 356`.

3. **High - high progress percentages are attached to unaccepted dirty
   batches.**
   - Paths: `lanes/difftastic/lane-status.json:4` and `:13`,
     `lanes/dolt/lane-status.json:4` and `:13`,
     `lanes/gitoxide/lane-status.json:4` and `:13`,
     `lanes/libsqlite/lane-status.json:4` and `:13`,
     `lanes/rclone/lane-status.json:4` and `:13`,
     `lanes/syncthing/lane-status.json:4` and `:13`,
     `progress.md:31`-`42`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, `goal.md:36`, and
     `goal.md:48` require small committed native slices, meaningful acceptance
     beyond passing tests, and supervisor verification before assigning the
     next work.
   - Evidence: lane status files now report very high estimates such as
     Quadrable `99%`, Gitoxide/libsqlite/rclone/Syncthing `98%`, Pandoc `95%`,
     Dolt `93%`, Difftastic/markerPDF/Readability `92%`, and LightningCSS
     `84%`, while `progress.md:31`-`42` still shows old `5%` to `66%`
     estimates.
   - Evidence: `latestCommit` fields are mostly pending or dirty-batch prose:
     Difftastic, Dolt, Gitoxide, libsqlite, rclone, markerPDF, Readability,
     esbuild, Syncthing, and Quadrable all describe uncommitted or pending
     lane batches rather than accepted commits.

4. **High - root-test evidence is stale or non-comparable for the current
   dirty snapshot.**
   - Paths: `lanes/*/lane-status.json:10`-`13`, `progress.md:325`,
     `porting.html:54`-`65`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:49`, and `goal.md:52`
     require precise blockers, honest failure recording, periodic repo-wide
     tests/static checks, and visible current progress.
   - Evidence: the required exact duplicate-root gate returned no active
     `php tools/run-tests.php` rows during this audit sample, but the tree was
     not stable enough for a root run because active agents and upstream
     runners were still running and the dirty tree is broad.
   - Evidence: lane records mix focused lane passes, stale root anecdotes,
     root-pending statements, and incompatible aggregate claims. For example,
     Syncthing says root `php tools/run-tests.php` passed `231` files and
     `27845` assertions, markerPDF says the normal locked root harness is
     green, rclone and Pandoc still cite an earlier active root PID `938813`,
     and Difftastic says aggregate root was not green because of an unrelated
     rclone expectation failure.
   - Audit judgment: no current aggregate PHP result should be promoted until
     the exact gate is clear and the tree is quiesced.

5. **High - manifest/status count schemas still cannot support trustworthy
   portfolio math.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/lane-status.json:6`,
     `lanes/markerpdf/lane-status.json:6`,
     `lanes/readability/lane-status.json:6`,
     `lanes/syncthing/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require a real upstream denominator, mapped upstream tests,
     PHP passing/failing counts, and generated dashboard fields backed by
     comparable values.
   - Evidence: `benchmarkDenominator.total` mixes numbers with long prose
     strings. Difftastic, Dolt, esbuild, Pandoc, and Quadrable use prose
     strings, while other lanes use numbers.
   - Evidence: mapped units and PHP pass units are still different measures:
     Gitoxide has `1959` mapped units but `3662` PHP passes; markerPDF has
     `227` mapped units but `341` PHP passes; Pandoc has `678` mapped units
     but `220` PHP passes; Readability has `1682` mapped units but `154` PHP
     passes; Syncthing has `355` mapped units but `356` PHP passes.
   - Evidence: WordPress scenario fields remain long prose strings, not stable
     denominator/count records, so the dashboard cannot distinguish one broad
     scenario from many accepted scenarios.

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
     markerPDF has not executed full benchmark/workflow/model-heavy conversion
     paths, Pandoc lacks built Haskell upstream runner parity, rclone excludes
     live provider/mount/FUSE coverage, Syncthing lacks full `go test ./...`,
     and libsqlite still defers WAL checkpoint/read-mark behavior, rollback
     journals, broader write behavior, and general SQL execution. These can be
     valid blockers or future slices, but not hidden beneath 90%+ percentages
     without normalized accepted denominators.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Latest exact gate result in this audit:

```text
<no output>
```

No root run was started because the duplicate-root gate was clear but the tree
was not stable enough for a trustworthy aggregate run. Active process evidence
included:

```text
551208 claude bash ... run-tmux-agent.sh port-dolt-runner ...
755365 claude bash ... run-tmux-agent.sh port-difftastic ...
879533 claude bash ... run-tmux-agent.sh port-readability ...
879952 claude bash ... run-tmux-agent.sh port-quadrable ...
880894 claude bash ... run-tmux-agent.sh port-dolt ...
927890 claude bash ... run-tmux-agent.sh port-rclone ...
934480 claude bash ... run-tmux-agent.sh port-markerpdf ...
935779 claude bash ... run-tmux-agent.sh port-syncthing ...
935966 claude bash ... run-tmux-agent.sh port-esbuild ...
937373 claude bash ... run-tmux-agent.sh port-lightningcss ...
938395 claude bash ... run-tmux-agent.sh port-libsqlite ...
939745 claude bash ... run-tmux-agent.sh port-integrator ...
940721 claude bash ... run-tmux-agent.sh port-gitoxide ...
942202 claude bash ... run-tmux-agent.sh port-pandoc ...
942524 claude bash ... run-tmux-agent.sh port-auditor ...
943875 claude cargo test -p gix-config -p gix-status ...
943915 claude ./testfixture ../src/test/testrunner.tcl --jobs 6 --stop-on-error veryquick
2347911 claude bash scripts/run-team-watchdog.sh
2424048 claude bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
2452997 claude bash scripts/run-capacity-controller-loop.sh
2479222 claude bash scripts/run-dashboard-updater-loop.sh
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
find lanes -path '*/UPSTREAM_TEST_MANIFEST.json' -print | sort
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -eo pid,user,ppid,etime,stat,args | rg 'run-tmux-agent|run-capacity-controller-loop|run-dashboard-updater-loop|run-evaluator-loop|run-team-watchdog|run-integrator|auditor|artifact|verifier|php tools/run-tests\.php|testrunner\.tcl|bats |go test|cargo test|npm test'
git log --oneline --decorate -n 30 --no-abbrev-commit
rg -n 'proc_open|shell_exec|passthru\(|system\(|\bexec\(' lanes/*/src lanes/*/tests lanes/*/examples
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON. All 12 lane manifests were found.
Latest samples reported `1764` default status rows, `175` tracked changed
files, and `175 files changed, 62586 insertions(+), 5298 deletions(-)`. The
implementation shell-out scan found only `PDO::exec` calls in
`lanes/syncthing/src/SqliteCheckpointStore.php`, not process shell-outs.

## Next Intervention

Freeze active writers/status publishers and broad upstream runners first. Then
validate manifests from the frozen tree, accept or reject dirty lane batches
one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, scenario, and commit fields, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same accepted snapshot, rerun the exact duplicate-root gate, and capture
one quiesced no-argument `php tools/run-tests.php` result.
