# Independent Audit - 2026-05-23T10:36:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for alignment checks, recent Git history, dirty-tree state,
active process state, and the required PHP root-test gate. During this audit,
`HEAD` was observed moving from `75030b55` through `cf8be719` and `1968b5b3`
to `e9c15a9a`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:278`-`296`, `porting.html:30`-`65`,
     `porting-summary.json:2`-`213`, `lanes/*/lane-status.json`,
     `scripts/run-team-watchdog.sh`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, and
     `scripts/run-evaluator-loop.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`, `goal.md:44`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require capped
     supervision, small committed passing slices, current coordination, honest
     repo-wide tests, and one visible stable baseline.
   - Evidence: `progress.md:25` still documents a target of two implementation
     lanes plus one auditor, while `progress.md:31`-`42` reports every lane as
     `stopped`. Process sampling found 22 matching active
     watchdog/capacity/dashboard/evaluator/agent processes, including primary
     lane agents, capacity jobs, an auditor, an integrator, and runner/status
     loops.
   - Evidence: `HEAD` moved during this audit from `75030b55` to `e9c15a9a`.
     The latest samples reported `1202` `git status --short` rows, `120`
     tracked changed files, and `120 files changed, 29017 insertions(+), 840
     deletions(-)`.
   - Audit judgment: do not accept portfolio percentages, blocker fields,
     commit fields, or aggregate pass/fail claims until active writers and
     status publishers are frozen and one regenerated snapshot is validated.

2. **High - the public dashboard is stale and fails the required column
   contract.**
   - Paths: `porting.html:30`-`65`, `porting-summary.json:2`-`213`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track benchmark source, upstream denominator, mapped tests,
     PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker,
     and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while current observed `HEAD` reached `e9c15a9a`.
   - Evidence: `porting.html:41`-`50` collapses benchmark source plus
     denominator into `Benchmark`, and PHP pass/fail plus mapped tests into
     `Mapped`, so it still omits the exact upstream-denominator and
     PHP-pass/fail column separation required by the goal.
   - Evidence: dashboard rows disagree with current manifests and statuses:
     Difftastic `160/417` vs `237/585`, Dolt `242/613` vs `420/613`, esbuild
     `164/2567` vs `212/2567`, Gitoxide `1432/2877` vs `1876/2877`, libsqlite
     `149/1454` vs `200/1589`, LightningCSS `773/3532` vs `1100/3532`,
     markerPDF `159/78` vs `197/252`, Pandoc `426/2028` vs `582/2276`,
     Quadrable dashboard PHP `108` vs lane status `131`, rclone `291/327` vs
     `406/2553`, Readability `1031/1984` vs `1445/1984`, and Syncthing
     `235/658` vs `306/658`.

3. **High - manifest/status schemas still cannot produce trustworthy
   portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `progress.md:31`-`42`,
     `porting-summary.json`, and `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     `goal.md:44`, and `goal.md:45` require real denominators, explicit
     slices, meaningful percentages, and current coordination fields.
   - Evidence: `benchmarkDenominator.total` is a prose string in Difftastic,
     Dolt, esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` is an object in many lanes,
     a string in Gitoxide, markerPDF, and Quadrable, and absent/null on the
     Pandoc manifest surface.
   - Evidence: PHP count units are mixed. Dolt records `258` PHP behavior
     tests, markerPDF records `306` in lane status but `307` in the manifest,
     Readability records `137`, while Gitoxide reports `3246` and
     LightningCSS `1221`, which are assertion/check counts rather than
     comparable behavior-test counts.
   - Evidence: `latestCommit` fields still include prose or pending dirty-batch
     states in Difftastic, Dolt, Gitoxide, LightningCSS, markerPDF, Quadrable,
     and Syncthing. `progress.md:31`-`42` estimates remain stale against both
     current lane status text and manifest mapped counts.

4. **High - root-test evidence remains non-comparable across lane records.**
   - Paths: `tools/run-tests.php`, `progress.md:281`-`291`,
     `lanes/difftastic/lane-status.json:10`-`13`,
     `lanes/readability/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/libsqlite/lane-status.json:10`-`13`, and
     `lanes/rclone/lane-status.json:10`-`13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the exact duplicate-root gate returned no active
     `php tools/run-tests.php` process during the final audit samples, but the
     tree was not stable enough for a trustworthy root run because active
     writer/status loops and `HEAD` movement persisted.
   - Evidence: lane status files still mix root-green anecdotes, root-pending
     duplicate-gate anecdotes, aggregate-red anecdotes, focused-only green
     claims, and uncommitted-batch claims from different moving snapshots.
     Those are not one accepted integration result.

5. **Medium - high progress language still over-credits bounded or
   runner-incomplete evidence.**
   - Paths: `lanes/difftastic/lane-status.json:5`-`13`,
     `lanes/gitoxide/lane-status.json:5`-`13`,
     `lanes/libsqlite/lane-status.json:5`-`13`,
     `lanes/markerpdf/lane-status.json:5`-`13`,
     `lanes/pandoc/lane-status.json:5`-`13`,
     `lanes/rclone/lane-status.json:5`-`13`,
     `lanes/syncthing/lane-status.json:5`-`13`, and
     `lanes/quadrable/lane-status.json:5`-`13`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require hard gaps to be explicit and prohibit counting
     bridge/shell/generated evidence as native implementation progress.
   - Evidence: Gitoxide full Cargo workspace tests remain unexecuted;
     libsqlite has `veryquick` and focused Tcl evidence but not full SQLite
     all/release permutations; rclone excludes live provider, mount/FUSE,
     Docker, and `fstest/test_all` coverage; Syncthing still lacks
     `go test ./...`; Pandoc lacks the Haskell runner; markerPDF lacks the
     full Python benchmark/model stack; Difftastic has no full Cargo runner.
   - Audit judgment: keep bounded runner probes and supplied/generated
     fixtures as evidence, but do not let them drive near-complete portfolio
     confidence until native behavior, full denominators, runner gaps, and
     accepted commit status are normalized.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed result during final audit samples: no active exact
`php tools/run-tests.php` process. No owner evidence was available because no
matching process was present. No root run was started because the stability
gate failed: active writer/status loops persisted and `HEAD` moved during the
audit.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
rg --pcre2 -n 'proc_open|shell_exec|passthru|system\(|(?<!->)exec\(' lanes -g '*.php'
```

Results: all lane upstream manifests and lane status files were valid JSON at
the time checked. The shell-out scan found no PHP process shell-outs under
`lanes/`.

Recent history reviewed:

```text
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
1dbf28af Advance pandoc markdown writer references
```

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
