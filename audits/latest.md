# Independent Audit - 2026-05-23T10:18:03Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for alignment checks, recent Git history, dirty-tree state,
active process state, and the required PHP root-test gate. During this audit,
`HEAD` was observed moving from `212a5189` through `98ccd4af` to `1c553d5d`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`, `porting.html`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, and
     `scripts/run-evaluator-loop.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`, `goal.md:44`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require capped supervision,
     small committed slices, current coordination, honest repo-wide tests, and
     one visible stable baseline.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` reports
     every lane session as `stopped`. Process sampling found the team watchdog,
     evaluator, capacity controller, dashboard updater, auditor, integrator,
     primary lane agents for Dolt, Syncthing, LightningCSS, esbuild, libsqlite,
     Difftastic, Pandoc, Readability, Quadrable, rclone, markerPDF, Gitoxide,
     plus capacity/status jobs.
   - Evidence: the latest sampled dirty state reported `1218`
     `git status --short` rows, `130` tracked changed files, and `130 files
     changed, 30876 insertions(+), 905 deletions(-)`. `HEAD` moved while this
     audit was running.
   - Audit judgment: do not accept portfolio percentages, blockers, lane commit
     fields, or aggregate pass/fail state until writers and status publishers
     are frozen and one snapshot is validated.

2. **High - root-test evidence is contradictory and remains diagnostic only.**
   - Paths: `tools/run-tests.php`, `progress.md`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/quadrable/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the required duplicate-root gate returned active root PID
     `2962659 php tools/run-tests.php`; owner evidence was
     `2962659 claude 2962658 00:49 R php tools/run-tests.php`. No duplicate
     root run was started.
   - Evidence: lane statuses disagree about aggregate root health: Gitoxide and
     Readability claim green root runs, libsqlite records a red aggregate root
     with Quadrable failures, LightningCSS says root is not green because of
     Readability failures, and Quadrable/Syncthing record pending duplicate-root
     gates. These are useful breadcrumbs, not one accepted integration result.

3. **High - the public dashboard is stale and still misses the required column
   contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`50`,
     `porting-summary.json:1`-`8`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit
     columns.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`5` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while current `HEAD` was observed at `1c553d5d`.
   - Evidence: the dashboard collapses benchmark source plus denominator into
     `Benchmark`, and PHP pass/fail plus mapped tests into `Mapped`, so it does
     not expose the exact required columns.
   - Evidence: dashboard values disagree with current manifests: Difftastic
     `160/417` vs `234/584`, Dolt `242/613` vs `414/613`, esbuild `164/2567`
     vs `210/2567`, Gitoxide `1432/2877` vs `1799/2877`, libsqlite `149/1454`
     vs `199/1589`, LightningCSS `773/3532` vs `1087/3532`, markerPDF `159/78`
     vs `196/251`, Pandoc `426/2028` vs `570/2276`, rclone `291/327` vs
     `397/2553`, Readability `1031/1984` vs `1415/1984`, and Syncthing
     `235/658` vs `296/658`. Quadrable's mapped denominator still matches
     `55/55`, but dashboard PHP count `108` is stale against lane status `129`.

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
     a long string in Gitoxide, markerPDF, and Quadrable, and absent from the
     Pandoc manifest surface sampled by `rg`.
   - Evidence: lane `latestCommit` fields are still not normalized commit IDs:
     Dolt, Gitoxide, libsqlite, LightningCSS, markerPDF, Quadrable, rclone,
     Readability, and Syncthing include pending, uncommitted, previous commit
     plus dirty-batch, or prose states.
   - Evidence: `progress.md:31`-`42` estimates are far behind lane status for
     most lanes, for example Gitoxide `66%` vs `98%`, LightningCSS `14%` vs
     `79%`, markerPDF `10%` vs `80%`, libsqlite `12%` vs `97%`, Pandoc `10%`
     vs `91%`, rclone `9%` vs `95%`, and Syncthing `8%` vs `94%`.

5. **Medium - high progress language still over-credits bounded or
   runner-incomplete evidence.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` prohibit crediting bridge/shell/generated artifacts as native
     implementation progress and require hard gaps to be explicit.
   - Evidence: Gitoxide is reported at `98%` while full Cargo workspace runner
     parity is not executed; libsqlite is `97%` while full SQLite `all`/release
     permutations are not run; rclone is `95%` while live provider, mount/FUSE,
     Docker, and `fstest/test_all` coverage are excluded; Syncthing is `94%`
     while full `go test ./...` remains unexecuted; Pandoc is `91%` while the
     Haskell runner is unexecuted; markerPDF is `80%` while the full Python
     benchmark/model stack is unexecuted.
   - Audit judgment: keep these as blockers or future slices. Bounded upstream
     probes and supplied fixture pairs are useful, but they should not drive
     near-complete portfolio percentages until native behavior, full
     denominators, and runner gaps are normalized.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed results:

```text
2962659 php tools/run-tests.php
2969564 php tools/run-tests.php
2969566 php tools/run-tests.php
<final exact gate clear>
```

Active root owner evidence:

```text
2962659 claude 2962658 00:49 R php tools/run-tests.php
```

Later transient exact-root PIDs `2969564` and `2969566` exited before owner
sampling, and the final exact duplicate-root gate was clear. No root run was
started because the stability condition still failed: active automation,
writer, and status loops were present, `progress.md` contradicted process
state, implementation `HEAD` moved during the audit, and the latest sampled
dirty tree contained `130` tracked changed files and `130 files changed, 30876
insertions(+), 905 deletions(-)`.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
```

Result: all lane upstream manifests were valid JSON at the time checked.

Recent history reviewed:

```text
1c553d5d Record esbuild lane status
98ccd4af Port esbuild decorator helper slices
212a5189 Refresh independent audit status
4396ea72 Advance rclone provider copy metadata
8fca4c31 pandoc: record ordered list writer status
74aa03ab pandoc: map markdown ordered list writer markers
c20307a4 difftastic: stamp builtin highlight status
f1b95822 difftastic: map javascript builtin highlight captures
af52ff75 Refresh independent audit status
c06b7c59 Stamp quadrable checkout fork status
fb196906 Advance quadrable checkout fork command parity
3eaeb1ca Refresh independent audit status
43573ce4 pandoc: refresh task list root result
ed1ffb47 Add Syncthing folder scan checkpoint status
d4ff8922 difftastic: map JSON directory command output
```

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
