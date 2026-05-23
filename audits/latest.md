# Independent Audit - 2026-05-23T10:47:29Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
lane status files needed for alignment checks, recent Git history, dirty-tree
state, active process state, and the required root-test gate. During handoff,
`HEAD` moved from `d656fc477898` through `f03f1473d306` to `24837bc2f6dd`; a
transient `52f8abff82c1` commit was also observed and then disappeared from
current history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - the repository still has no stable integration snapshot to
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:281`-`293`, `porting.html:30`-`65`,
     `porting-summary.json`, `lanes/*/lane-status.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `scripts/run-team-watchdog.sh`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, and
     `scripts/run-evaluator-loop.sh`.
   - Goal requirement at risk: `goal.md` requires capped supervision, small
     committed passing slices, current coordination, honest repo-wide tests,
     and a visible stable baseline for every lane.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` still
     marks every lane `stopped`. Process sampling found 25 matching active
     watchdog/capacity/dashboard/evaluator/integrator/auditor/lane-agent
     processes, including primary lane agents and capacity jobs.
   - Evidence: `HEAD` was `d656fc477898` during the first final sample, then
     moved through `f03f1473d306` to `24837bc2f6dd` while this audit was being
     staged. A transient `52f8abff82c1` commit was also observed; it mixed
     `audits/latest.md` and `progress.md` with
     `lanes/difftastic/lane-status.json` before disappearing from current
     history, confirming that audit/status integration is still happening in a
     moving tree.
   - Evidence: the dirty tree kept changing: tracked changes moved from 133 to
     136 files during the audit, default `git status --short` moved from 1238
     to 1242 rows, and later handoff samples reported 1244 default status rows,
     135 tracked status rows, and `132 files changed, 30475 insertions(+), 911
     deletions(-)`.
   - Evidence: manifest data changed during the audit window. Difftastic moved
     from `585 / 237` to `586 / 240` total/mapped while active writers were
     still running.
   - Audit judgment: do not accept current percentages, blocker fields, commit
     fields, or root-test anecdotes until active writers and status publishers
     are frozen and one regenerated snapshot is validated.

2. **High - the public dashboard is stale and still fails the required status
   contract.**
   - Paths: `porting.html:30`-`65`, `porting-summary.json`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` still publishes generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`; current
     `HEAD` is `24837bc2f6dd`. `porting-summary.json` reports the same
     generated time and source commit.
   - Evidence: `porting.html:41`-`50` still collapses upstream denominator into
     `Benchmark` and PHP pass/fail plus mapped tests into `Mapped`, rather than
     publishing the separate denominator, mapped-test, and PHP pass/fail
     columns required by the goal.
   - Evidence: dashboard rows disagree with current manifests: Difftastic
     `160/417` vs `240/586`, Dolt `242/613` vs `420/613`, esbuild `164/2567`
     vs `215/2567`, Gitoxide `1432/2877` vs `1876/2877`, libsqlite `149/1454`
     vs `202/1589`, LightningCSS `773/3532` vs `1107/3532`, markerPDF
     `159/78` vs `197/252`, Pandoc `426/2028` vs `582/2276`, Quadrable PHP
     `108` vs lane status `131`, rclone `291/327` vs `410/2553`,
     Readability `1031/1984` vs `1445/1984`, and Syncthing `235/658` vs
     `310/658`.

3. **High - manifest/status schemas still cannot produce trustworthy portfolio
   math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `progress.md:31`-`42`, `porting.html`, and
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md` requires real upstream denominators,
     mapped upstream tests, PHP pass/fail counts, explicit blockers, current
     commit state, and meaningful percentages.
   - Evidence: `benchmarkDenominator.total` is a prose string in Difftastic,
     Dolt, esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` is an object in many lanes,
     a string in Gitoxide, markerPDF, and Quadrable, and absent/null at the
     Pandoc manifest surface.
   - Evidence: PHP count units remain mixed. Some lane statuses report behavior
     tests, others report assertions/checks, and some manifests omit
     `nativeImplementation.phpBehaviorTests` entirely while the status file
     has a PHP pass count.
   - Evidence: `latestCommit` fields remain non-commit prose in
     `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/esbuild/lane-status.json`, `lanes/gitoxide/lane-status.json`,
     `lanes/libsqlite/lane-status.json`, `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/quadrable/lane-status.json`,
     `lanes/rclone/lane-status.json`, and
     `lanes/syncthing/lane-status.json`.

4. **High - root-test evidence is non-comparable across lane records.**
   - Paths: `tools/run-tests.php`, `progress.md:281`-`293`,
     `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/esbuild/lane-status.json`, `lanes/gitoxide/lane-status.json`,
     `lanes/libsqlite/lane-status.json`, `lanes/markerpdf/lane-status.json`,
     `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     and `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires committed slices with passing
     tests and honest repo-wide failure records.
   - Evidence: the exact required duplicate-root gate returned no active
     `php tools/run-tests.php` process during the audit samples, but the tree
     was not stable enough for a root run because active writer/status loops
     persisted and dirty files changed underneath the audit.
   - Evidence: lane statuses still mix aggregate-green claims, aggregate-red
     claims, duplicate-gated pending claims, focused-only green claims, and
     uncommitted-batch claims. Examples include Difftastic reporting a prior
     aggregate red on Pandoc, esbuild/Gitoxide/rclone/Readability reporting
     aggregate green, and libsqlite/markerPDF/Quadrable/Syncthing reporting
     root pending due active root PIDs from earlier samples.

5. **Medium - high progress language still over-credits bounded or
   runner-incomplete evidence.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/rclone/lane-status.json`, and
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires real upstream denominators,
     meaningful fixture parity, and explicit blockers; it also prohibits
     counting bridge calls, generated fixtures, or shell-outs as native
     implementation progress.
   - Evidence: Gitoxide full Cargo workspace tests remain unexecuted;
     libsqlite has `veryquick` and focused Tcl evidence but not full SQLite
     all/release permutations; rclone excludes live provider, mount/FUSE,
     Docker, and `fstest/test_all` coverage; Syncthing still lacks
     `go test ./...`; Pandoc lacks the Haskell runner; markerPDF lacks the
     full Python benchmark/model stack; Difftastic lacks full Cargo runner
     parity.
   - Audit judgment: bounded runner probes, supplied documents, generated
     fixtures, and oracle artifacts are useful evidence, but they must not
     drive near-complete status until native behavior, full denominators,
     runner gaps, and accepted commit state are normalized.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed result during audit samples: no active exact `php tools/run-tests.php`
process. A later handoff gate found active root PID `3102951`; owner evidence:

```text
3102951 claude 3102950 00:28 R php tools/run-tests.php
```

No duplicate root run was started. The stability gate also failed: active
writer/status loops persisted and tracked files/manifests changed during the
audit.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
rg --pcre2 -n 'proc_open|shell_exec|passthru|system\(|(?<!->)exec\(' lanes -g '*.php'
```

Results: all lane upstream manifests and lane status files parsed as valid JSON
at the time checked. The shell-out scan found no PHP process shell-outs under
`lanes/`.

Recent history reviewed:

```text
24837bc2 difftastic: stamp highlight status
f03f1473 difftastic: map parser highlight captures
d656fc47 Refresh independent audit status
e9c15a9a Update readability lane status
1968b5b3 Advance readability fixture parity
1687aa5f Record rclone lane commit status
cf8be719 Port rclone OneDrive delta listing slices
75030b55 Refresh independent audit status
bde83721 Advance libsqlite replacement balancing
fbcf368f Record esbuild private accessor status
0cdef3ec Port esbuild private accessor decorators
a0c98a72 Refresh independent audit status
ed415770 Record pandoc lane status
9d9122d4 Record quadrable lane commit pointer
```

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
