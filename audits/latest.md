# Independent Audit - 2026-05-23T13:50:51Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, dirty tree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `005fd6867404` (`Refresh independent audit
status`). Recent history reviewed includes `005fd686`, `79a7e66a`,
`9dfec34f`, `1c06a555`, `85dcd312`, `66798317`, `be7cf14f`, `c1f67cda`,
`d52cc007`, `24260634`, `51867989`, `b75226d1`, `30be5e3c`, `90d1fa3b`,
and `81419ac3`.

## Findings

1. **Critical - the repository is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:13`,
     `porting.html:32`-`36`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`, `goal.md:44`,
     `goal.md:48`, and `goal.md:49` require capped active work, current
     owner/session tracking, small reviewable committed slices, supervisor
     verification, and honest repo-wide test evidence.
   - Evidence: `progress.md:25` still declares a launch target of two
     implementation lanes plus one auditor, while `progress.md:31`-`42`
     reports all lane sessions as `stopped`.
   - Evidence: process sampling found active watchdog/agent jobs for Dolt
     runner, Readability, Dolt, Syncthing, Pandoc, Quadrable, rclone,
     markerPDF, esbuild, LightningCSS, libsqlite, Difftastic, auditor,
     integrator, Gitoxide, four fixed-HEAD PHP capacity agents, evaluator,
     capacity-controller, and dashboard-updater. Broad Dolt BATS was also
     active under PID `575005` with child BATS processes.
   - Evidence: latest dirty-tree samples reported `1738` default
     `git status --short --untracked-files=all` rows, `174` tracked changed
     files, and `174 files changed, 60523 insertions(+), 5346 deletions(-)`.
   - Audit judgment: aggregate acceptance should remain blocked until active
     writers/status publishers and broad upstream runners are frozen, then one
     regenerated snapshot is validated.

2. **High - the published dashboard is stale and does not satisfy the stated
   dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, `porting-summary.json`, current
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `005fd6867404`.
   - Evidence: `porting.html:41`-`50` still collapses required fields into
     compact `Benchmark` and `Mapped` columns instead of separate benchmark
     source, upstream denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree materially with current manifests and
     lane statuses. Examples: Difftastic publishes `417 / 160 / 160` but the
     current manifest/status report `588 artifacts / 276 mapped / 276 PHP`;
     markerPDF publishes `78 / 159 / 264` but current evidence is
     `278 / 226 / 340`; rclone publishes `327 / 291 / 291` but current
     evidence is `2553 / 481 / 481`; Gitoxide publishes `1432 mapped /
     2646 PHP` but current evidence is `1955 / 3646`; Readability publishes
     `1031 mapped / 107 PHP` but current evidence is `1652 / 153`; Syncthing
     publishes `235 / 235` but current evidence is `355 / 352`.

3. **High - high progress percentages are attached to unaccepted dirty
   batches.**
   - Paths: `lanes/rclone/lane-status.json:4` and `:13`,
     `lanes/markerpdf/lane-status.json:4` and `:13`,
     `lanes/readability/lane-status.json:4` and `:13`,
     `lanes/lightningcss/lane-status.json:4` and `:13`,
     `progress.md:31`-`42`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, `goal.md:36`, and
     `goal.md:48` require small committed native slices, meaningful acceptance
     beyond passing tests, and supervisor verification before assigning the
     next work.
   - Evidence: lane status files now report high estimates such as rclone
     `98%`, libsqlite `98%`, Gitoxide `98%`, Syncthing `98%`, Quadrable
     `99%`, Pandoc `95%`, Dolt `92%`, markerPDF/Readability/Difftastic
     `91%`, and LightningCSS `83%`, while `progress.md:31`-`42` still shows
     old `5%` to `66%` estimates.
   - Evidence: lane `latestCommit` fields are still pending/uncommitted or
     dirty-batch prose. Examples include rclone `pending lane-local changes`,
     markerPDF `uncommitted lane batch`, Readability `uncommitted QQ Tencent
     article-root readability slice`, LightningCSS `current ... batch remains
     uncommitted`, and Gitoxide `pending`.
   - Evidence: recent Git history is dominated by audit/status commits; only a
     small number of lane implementation commits appear in the latest 15
     commits, so the current portfolio state is not a sequence of accepted
     small lane slices.

4. **High - manifest/status count schemas still cannot support trustworthy
   portfolio math.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/*/lane-status.json:5`-`7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require a real upstream denominator, mapped upstream tests,
     PHP passing/failing counts, and generated dashboard fields backed by
     comparable values.
   - Evidence: `benchmarkDenominator.total` mixes numeric values with prose
     strings. Difftastic, Dolt, Pandoc, and Quadrable store their denominator
     as long descriptive strings, while Gitoxide, libsqlite, LightningCSS,
     markerPDF, rclone, Readability, and Syncthing store numbers.
   - Evidence: mapped units and PHP pass units are often different measures:
     markerPDF has `226` mapped units but `340` PHP passes, LightningCSS has
     `1229` mapped units but `1362` PHP passes, Gitoxide has `1955` mapped
     units but `3646` PHP passes, and Quadrable has `55` mapped units but
     `143` PHP passes.
   - Evidence: WordPress scenario fields are long prose strings, not stable
     denominator/count records, so the dashboard cannot distinguish one broad
     scenario from many accepted scenarios.

5. **High - root-test records remain stale and non-comparable for the current
   dirty snapshot.**
   - Paths: `lanes/rclone/lane-status.json:10`-`12`,
     `lanes/markerpdf/lane-status.json:10`-`12`,
     `lanes/readability/lane-status.json:5` and `:10`-`12`,
     `lanes/lightningcss/lane-status.json:10`-`12`,
     `porting.html:54`-`65`, `progress.md`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:49`, and `goal.md:52`
     require precise blockers, honest failure recording, periodic repo-wide
     tests/static checks, and visible current progress.
   - Evidence: the required exact duplicate-root gate returned no active
     no-argument root harness at this sample, but the tree was not stable
     enough for a trustworthy root run because active writers and broad Dolt
     BATS were still running.
   - Evidence: lane records mix "focused tests green", "root pending",
     "accidental pre-slice root passed", and stale dashboard green counts.
     Readability explicitly says a `231` file root pass predates the slice;
     markerPDF says no aggregate root was started because the focused handoff
     did not require it; rclone and LightningCSS defer root because broad
     upstream runners were active. These are not comparable aggregate evidence.

6. **Medium - hard upstream-runner and hard-feature gaps are still softened by
   high percentages.**
   - Paths: `lanes/difftastic/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:20`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require precise blockers, meaningful fixture parity,
     upstream tests as source of truth, and explicit future slices for hard
     features.
   - Evidence: Difftastic lacks full Cargo parity, esbuild still excludes
     release-extra `make test-all` paths, Gitoxide lacks full cargo workspace
     parity, markerPDF has not run full benchmark/workflow/model-heavy
     conversion paths, rclone excludes live provider/mount/FUSE coverage,
     Syncthing has no full `go test ./...`, Pandoc has no built Haskell
     upstream runner, and Quadrable still excludes the full 100,000-record
     benchmark.
   - Audit judgment: these are legitimate blockers/future slices and should not
     sit beneath 90%+ portfolio percentages without a normalized, accepted
     denominator and explicit non-goals.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Latest exact gate result in this audit:

```text
PGREP_EMPTY
```

No root run was started because the stability condition failed even though the
exact duplicate-root gate was empty. Active broad-runner/status evidence
included:

```text
575005 claude timeout 90m bats verify-constraints.bats ...
575036 claude /usr/bin/bash /usr/libexec/bats-core/bats ...
575043 claude /usr/bin/bash /usr/libexec/bats-core/bats-exec-suite ...
575044 claude /usr/bin/bash /usr/libexec/bats-core/bats ...
551208 claude bash ... run-tmux-agent.sh port-dolt-runner ...
618416 claude bash ... run-tmux-agent.sh port-readability ...
618932 claude bash ... run-tmux-agent.sh port-dolt ...
666262 claude bash ... run-tmux-agent.sh port-syncthing ...
698024 claude bash ... run-tmux-agent.sh port-pandoc ...
698081 claude bash ... run-tmux-agent.sh port-quadrable ...
698391 claude bash ... run-tmux-agent.sh port-rclone ...
707129 claude bash ... run-tmux-agent.sh port-markerpdf ...
707343 claude bash ... run-tmux-agent.sh port-esbuild ...
724819 claude bash ... run-tmux-agent.sh port-lightningcss ...
725043 claude bash ... run-tmux-agent.sh port-libsqlite ...
755365 claude bash ... run-tmux-agent.sh port-difftastic ...
778437 claude bash ... run-tmux-agent.sh port-auditor ...
778592 claude bash ... run-tmux-agent.sh port-integrator ...
810941 claude bash scripts/run-tmux-agent.sh port-capacity-php-fixed-79a7e66-core-git-dolt-20260523T1347Z ...
810983 claude bash scripts/run-tmux-agent.sh port-capacity-php-fixed-79a7e66-docweb-20260523T1347Z ...
810990 claude bash scripts/run-tmux-agent.sh port-capacity-php-fixed-79a7e66-storage-20260523T1347Z ...
811015 claude bash scripts/run-tmux-agent.sh port-capacity-php-fixed-79a7e66-css-pdf-quad-20260523T1347Z ...
822203 claude bash ... run-tmux-agent.sh port-gitoxide ...
2424048 claude bash ... run-evaluator-loop.sh
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
git log --oneline --decorate -n 30
git show --stat --oneline --decorate --no-renames -n 15
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON. Latest samples reported `1738`
default status rows, `174` tracked changed files, and `174 files changed,
60523 insertions(+), 5346 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and broad upstream runners first. Then
validate manifests from the frozen tree, accept or reject dirty lane batches
one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, scenario, and commit fields, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same accepted snapshot, rerun the exact duplicate-root gate, and capture
one quiesced no-argument `php tools/run-tests.php` result.
