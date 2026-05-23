# Independent Audit - 2026-05-23T09:54:07Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files needed for alignment
checks, recent Git history, dirty-tree state, active process state, and the
required PHP root-test gate. Observed `HEAD` moved during the audit from
`43ea985caefe` through `7a4ddb318516`, `fb6de2ac`, `3ab0616e8580`, and
`ed1ffb47676d` to `43573ce47b76`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md`, `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, and
     active automation under `scripts/`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, `goal.md:49`, and `goal.md:52` require
     capped supervision, small committed slices, current coordination, honest
     repo-wide tests, and a visible stable baseline.
   - Evidence: `progress.md:25` still documents a two-implementation-worker
     plus one-auditor target and `progress.md:31`-`42` still reports every
     lane session as `stopped`, but process sampling found active
     `run-team-watchdog.sh`, `run-capacity-controller-loop.sh`,
     `run-dashboard-updater-loop.sh`, `run-evaluator-loop.sh`, `port-auditor`,
     `port-integrator`, many lane agents, and capacity jobs.
   - Evidence: the dirty snapshot moved while auditing. The latest sampled
     `HEAD` was `43573ce4`, after starting from `43ea985c`; status samples
     reported `1144` default `git status --short` entries, `119` tracked
     changed files, and `117 files changed, 28686 insertions(+), 692
     deletions(-)`.
   - Audit judgment: do not accept portfolio percentages, pass/fail state,
     blockers, or lane commit fields until writers and status publishers are
     frozen and one snapshot is validated.

2. **High - root-test evidence remains contradictory, so no root run was
   started.**
   - Paths: `tools/run-tests.php` and every `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the required gate
     `pgrep -af '^php tools/run-tests\.php( |$)'` initially returned no rows;
     a later pre-edit gate returned transient focused-lane PID
     `2756443 php tools/run-tests.php lanes/quadrable/tests`, which exited
     before owner sampling; a later post-edit sample returned active root PID
     `2794679`, with owner evidence `2794679 claude 2716061 00:29 Rs php
     tools/run-tests.php`; the latest exact gate was clear. I still did not
     start `php tools/run-tests.php` because the tree was moving, active
     writer/status loops were present, and an active root appeared during
     handoff checks.
   - Evidence: lane statuses still disagree: several lanes record green
     aggregate root runs, Syncthing records a red aggregate run with 3
     failures, esbuild records a red run followed by a filtered green rerun,
     and other lanes record pending duplicate-root gates tied to stale PIDs.
   - Audit judgment: collapse root-test state to one repo-level result from a
     frozen snapshot. Lane-local anecdotes are diagnostic, not acceptance
     evidence.

3. **High - `porting.html` is stale and still does not satisfy the dashboard
   column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with separate benchmark source, upstream denominator, mapped
     tests, PHP pass/fail, WordPress scenarios, phase, audit, current work,
     blocker, and commit columns.
   - Evidence: `porting.html:32`-`36` still publishes generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while audit
     history now observes `HEAD` at `43573ce4`.
   - Evidence: the table still merges benchmark source and denominator into
     one `Benchmark` column and merges PHP pass/fail with mapped tests into
     one `Mapped` column (`porting.html:41`-`45`).
   - Evidence: dashboard rows disagree with current manifests: Difftastic
     `160/417` vs manifest `228/583`, Dolt `242/613` vs `399/613`,
     esbuild `164/2567` vs `204/2567`, Gitoxide `1432/2877` vs `1781/2877`,
     libsqlite `149/1454` vs `196/1589`, LightningCSS `773/3532` vs
     `1010/3532`, markerPDF `159/78` vs `193/248`, Pandoc `426/2028` vs
     `558/2276`, rclone `291/327` vs `395/2553`, Readability `1031/1984` vs
     `1387/1984`, and Syncthing `235/658` vs `291/658`.

4. **High - manifest and lane-status schemas still cannot produce trustworthy
   portfolio percentages.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json` and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, `goal.md:44`, and `goal.md:45` require real denominators,
     explicit slices, current coordination fields, and meaningful percentages.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable (`UPSTREAM_TEST_MANIFEST.json:14`) but
     numeric in Gitoxide, libsqlite, LightningCSS, markerPDF, rclone,
     Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` is an object in many lanes
     but a string in Gitoxide, markerPDF, and Quadrable. PHP behavior counts
     are also not normalized: markerPDF maps `193` upstream units but reports
     `303` PHP behavior tests, while Readability maps `1387` upstream units
     but reports `130` PHP behavior tests.
   - Evidence: many `latestCommit` fields are prose or mixed states rather
     than accepted commit IDs, for example pending/uncommitted batches in
     Difftastic, Dolt, esbuild, Gitoxide, libsqlite, LightningCSS, markerPDF,
     Pandoc, rclone, Readability, and Syncthing lane statuses.

5. **Medium - high progress language still over-credits bounded, supplied, or
   shell/oracle-backed evidence.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` prohibit crediting bridge/shell/generated
     artifacts as native implementation progress and require hard gaps to be
     explicit.
   - Evidence: Gitoxide and Difftastic lack full Cargo runner parity, Pandoc
     lacks full Haskell runner parity, markerPDF has not run the full Python
     benchmark/model stack, rclone excludes live providers/mount/FUSE/Docker,
     Syncthing full `go test ./...` remains unrun, and Quadrable still leans
     heavily on oracle dump/load fixtures despite a passing upstream C++ runner.
   - Audit judgment: keep these as blockers/future slices rather than treating
     them as near-complete native parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed results:

```text
<no rows>
2756443 php tools/run-tests.php lanes/quadrable/tests
<no rows>
2794679 php tools/run-tests.php
<no rows>
```

The transient focused-lane process exited before `ps -p 2756443` could recover
owner evidence. The active root process owner evidence was:

```text
2794679 claude 2716061 00:29 Rs php tools/run-tests.php
```

No root run was started because the stability condition failed: active
automation/writer loops were present, `HEAD` moved during the audit, a root
harness appeared during handoff checks, and the latest dirty-tree sample
contained `1144` default status entries, `119` tracked changed files, and
`117 files changed, 28686 insertions(+), 692 deletions(-)`.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
```

Result: all lane upstream manifests were valid JSON at the time checked.

Recent history reviewed:

```text
43573ce4 pandoc: refresh task list root result
ed1ffb47 Add Syncthing folder scan checkpoint status
d4ff8922 difftastic: map JSON directory command output
3ab0616e pandoc: record task list writer status
7a4ddb31 pandoc: map task list writer slices
43ea985c Refresh independent audit status
811eec9e Record quadrable binary proof status
b3f93896 Advance quadrable binary proof command parity
```

## Next Intervention

Freeze active writers/status publishers and duplicate root loops first. Then
validate all manifests from the frozen tree, accept or reject dirty lane batches
one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from the same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
