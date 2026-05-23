# Independent Audit - 2026-05-23T10:24:11Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane status files needed for alignment checks, recent Git history, dirty-tree
state, active process state, and the required PHP root-test gate. During this
audit, `HEAD` was observed moving from `f996fca1` through `35aa3bed`,
`1dbf28af`, `9d9122d4`, and `ed415770`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`, `progress.md:281`,
     `progress.md:290`, `porting.html:30`-`36`, `porting-summary.json:2`-`5`,
     `lanes/*/lane-status.json`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, and
     `scripts/run-evaluator-loop.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`, `goal.md:44`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require capped supervision,
     small committed slices, current coordination, honest repo-wide tests, and
     one visible stable baseline.
   - Evidence: `progress.md:25` still documents a launch target of two
     implementation lanes plus one auditor, and `progress.md:31`-`42` still
     reports every lane session as `stopped`. Process sampling found 62 matching
     active watchdog/evaluator/capacity/dashboard/agent Codex processes,
     including primary lane agents for Pandoc, Quadrable, markerPDF, Gitoxide,
     Dolt, esbuild, libsqlite, LightningCSS, Syncthing, Readability, Difftastic,
     rclone, plus auditor/integrator/capacity jobs.
   - Evidence: `HEAD` moved repeatedly during the audit from `f996fca1` to
     `ed415770`. The latest dirty sample reported `1217` `git status --short`
     rows, `127` tracked changed files, and `127 files changed, 30873
     insertions(+), 785 deletions(-)`.
   - Audit judgment: do not accept portfolio percentages, blockers, commit
     fields, or aggregate pass/fail claims until active writers/status
     publishers are frozen and one regenerated snapshot is validated.

2. **High - root-test evidence is contradictory and the required gate blocked
   this audit from starting a duplicate run.**
   - Paths: `tools/run-tests.php`, `progress.md:281`, `progress.md:289`,
     `lanes/libsqlite/lane-status.json:12`-`13`,
     `lanes/lightningcss/lane-status.json:12`-`13`,
     `lanes/markerpdf/lane-status.json:12`-`13`,
     `lanes/pandoc/lane-status.json:12`-`13`,
     `lanes/rclone/lane-status.json:12`-`13`,
     `lanes/readability/lane-status.json:12`-`13`, and
     `lanes/syncthing/lane-status.json:12`-`13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the required duplicate-root gate initially returned active root
     PIDs `2977690` and `2977866`. Owner evidence was:

     ```text
     2977690 claude Sat May 23 10:22:17 2026 00:19 php tools/run-tests.php
     2977866 claude Sat May 23 10:22:21 2026 00:14 php tools/run-tests.php
     ```

     A later exact gate returned active root PID `2982446`; owner evidence was
     `2982446 claude 2952808 00:22 Rs php tools/run-tests.php`.
   - Evidence: a final exact gate was clear, but the stability condition still
     failed because active writers were present and `HEAD` had moved again.
     Lane statuses still disagree about aggregate root health: libsqlite records
     a red root caused by Quadrable failures, LightningCSS records a red root
     caused by Readability failures, markerPDF/rclone record pending root gates,
     while Pandoc, Readability, Syncthing, esbuild, and others record green root
     anecdotes from different moving snapshots.

3. **High - the public dashboard is stale and still does not expose the exact
   required column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, `porting-summary.json:2`-`205`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit
     columns.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`5` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while current `HEAD` reached `ed415770`.
   - Evidence: the dashboard collapses benchmark source plus denominator into
     `Benchmark`, and PHP pass/fail plus mapped tests into `Mapped`, so it still
     omits the exact upstream-denominator and PHP-pass/fail column separation
     required by the goal.
   - Evidence: dashboard mapped counts disagree with current manifests:
     Difftastic `160/417` vs `234/584`, Dolt `242/613` vs `414/613`, esbuild
     `164/2567` vs `210/2567`, Gitoxide `1432/2877` vs `1836/2877`, libsqlite
     `149/1454` vs `199/1589`, LightningCSS `773/3532` vs `1087/3532`,
     markerPDF `159/78` vs `196/251`, Pandoc `426/2028` vs `570/2276`, rclone
     `291/327` vs `402/2553`, Readability `1031/1984` vs `1428/1984`, and
     Syncthing `235/658` vs `301/658`. Quadrable's mapped denominator remains
     `55/55`, but dashboard PHP count `108` is stale against lane status `130`.

4. **High - manifest, lane-status, and progress schemas still cannot produce
   trustworthy portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `progress.md:31`-`42`,
     `porting-summary.json`, and `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     `goal.md:44`, and `goal.md:45` require real denominators, explicit slices,
     current coordination fields, and meaningful percentages.
   - Evidence: `benchmarkDenominator.total` is prose/string in Difftastic,
     Dolt, esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` is an object in many lanes,
     a string in Gitoxide, markerPDF, and Quadrable, and absent/null on the
     Pandoc manifest surface.
   - Evidence: PHP count units are mixed: Dolt's manifest maps `414` upstream
     items but records `248` `phpBehaviorTests` while lane status says `253`
     PHP pass; markerPDF records `306` manifest PHP behavior tests but lane
     status says `305`; Gitoxide and LightningCSS lane status `phpPass` values
     are assertion/check counts (`3246`, `1208`) rather than behavior-test file
     counts used by several other lanes.
   - Evidence: `latestCommit` fields still include pending/prose states in
     Difftastic, Dolt, Gitoxide, libsqlite, LightningCSS, markerPDF, Pandoc,
     rclone, Readability, and Syncthing, so they are not reliable accepted
     commit pointers.
   - Evidence: `progress.md:31`-`42` estimates remain far behind current lane
     statuses, for example Gitoxide `66%` vs lane-status `98%`,
     LightningCSS `14%` vs `79%`, markerPDF `10%` vs `80%`, libsqlite `12%`
     vs `97%`, Pandoc `10%` vs `92%`, rclone `9%` vs `96%`, and Syncthing
     `8%` vs `95%`.

5. **Medium - high progress language still over-credits bounded or
   runner-incomplete evidence.**
   - Paths: `lanes/gitoxide/lane-status.json:4`-`12`,
     `lanes/libsqlite/lane-status.json:4`-`12`,
     `lanes/markerpdf/lane-status.json:4`-`12`,
     `lanes/pandoc/lane-status.json:4`-`12`,
     `lanes/rclone/lane-status.json:4`-`12`,
     `lanes/syncthing/lane-status.json:4`-`12`, and
     `lanes/quadrable/lane-status.json:4`-`12`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require hard gaps to be explicit and prohibit counting
     bridge/shell/generated evidence as native implementation progress.
   - Evidence: Gitoxide is reported at `98%` without full Cargo workspace
     runner parity; libsqlite is `97%` with SQLite `veryquick` but not full
     `all`/release permutations; rclone is `96%` while live provider, FUSE,
     Docker, and `fstest/test_all` coverage are excluded; Syncthing is `95%`
     without `go test ./...`; Pandoc is `92%` without the Haskell runner;
     markerPDF is `80%` without the full Python benchmark/model stack; and
     Quadrable is `98%` while the full 500-trial sync-fuzzer probe remains
     outside the fast suite.
   - Audit judgment: keep bounded runner probes and supplied/generated fixtures
     as evidence, but do not let them drive near-complete portfolio percentages
     until native behavior, full denominators, runner gaps, and accepted commit
     status are normalized.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed results:

```text
2977690 php tools/run-tests.php
2977866 php tools/run-tests.php
2982446 php tools/run-tests.php
<final exact gate clear>
```

Owner evidence:

```text
2977690 claude Sat May 23 10:22:17 2026 00:19 php tools/run-tests.php
2977866 claude Sat May 23 10:22:21 2026 00:14 php tools/run-tests.php
2982446 claude 2952808 00:22 Rs php tools/run-tests.php
```

No duplicate root run was started. The final gate later cleared, but the tree
was still not stable enough for a trustworthy root run because active
automation/writer/status loops persisted, `HEAD` moved during the audit, and the
dirty tree remained broad.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
rg -n 'proc_open|shell_exec|exec\(|passthru|system\(' lanes -g '*.php'
```

Results: all lane upstream manifests were valid JSON at the time checked, and
no PHP shell-out APIs were found in `lanes/**/*.php` by that focused scan.

Recent history reviewed:

```text
ed415770 Record pandoc lane status
9d9122d4 Record quadrable lane commit pointer
1dbf28af Advance pandoc markdown writer references
35aa3bed Advance quadrable CLI command parity
f996fca1 Refresh independent audit status
1c553d5d Record esbuild lane status
98ccd4af Port esbuild decorator helper slices
212a5189 Refresh independent audit status
4396ea72 Advance rclone provider copy metadata
8fca4c31 pandoc: record ordered list writer status
74aa03ab pandoc: map markdown ordered list writer markers
c20307a4 difftastic: stamp builtin highlight status
```

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
