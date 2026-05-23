# Independent Audit - 2026-05-23T10:31:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane status files needed for alignment checks, recent Git history, dirty-tree
state, active process state, and the required PHP root-test gate. During this
audit, `HEAD` was observed moving from `a0c98a72` through `fbcf368f` to
`bde83721`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:278`-`295`, `porting.html:30`-`36`,
     `porting-summary.json:2`-`8`, `lanes/*/lane-status.json`,
     `scripts/run-team-watchdog.sh`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, and
     `scripts/run-evaluator-loop.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`, `goal.md:44`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require capped supervision,
     small committed slices, current coordination, honest repo-wide tests, and
     one visible stable baseline.
   - Evidence: `progress.md:25` still documents a target of two implementation
     lanes plus one auditor, while `progress.md:31`-`42` reports every lane as
     `stopped`. Process sampling found `61` matching active
     watchdog/capacity/dashboard/evaluator/agent/Codex/root-test processes,
     including primary lane agents, capacity/artifact jobs, and an auditor.
   - Evidence: `HEAD` moved during this audit from `a0c98a72` to `bde83721`.
     The latest dirty sample reported `1211` `git status --short` rows, `112`
     tracked changed files, and `112 files changed, 28522 insertions(+), 660
     deletions(-)`.
   - Audit judgment: do not accept portfolio percentages, blocker fields,
     commit fields, or aggregate pass/fail claims until active writers and
     status publishers are frozen and one regenerated snapshot is validated.

2. **High - the required root-test gate is active, and lane root-test claims
   are still contradictory.**
   - Paths: `tools/run-tests.php`, `progress.md:281`-`290`,
     `lanes/difftastic/lane-status.json:10`-`13`,
     `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`, and
     `lanes/readability/lane-status.json:10`-`13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the required duplicate-root gate first returned active
     `php tools/run-tests.php` PID `2988545` plus focused Syncthing test PID
     `2989545`. Later exact samples returned root PIDs `2991186`, `2992314`,
     `2992617`, and active root PID `2994414`. A final exact sample later
     cleared, but the stability gate still failed because the tree and active
     automation were not quiescent; a handoff sanity check then found active
     root PID `2996267`.
   - Latest owner evidence:

     ```text
     2996267 claude 2984877 00:14 Rs php tools/run-tests.php
     ```

   - Evidence: lane status files still mix green root anecdotes, pending root
     gates, duplicate-gated root claims, and earlier red/root-race history from
     different moving snapshots. Those are not a single accepted integration
     result.

3. **High - the public dashboard is stale and still does not expose the exact
   required column contract.**
   - Paths: `porting.html:30`-`65`, `porting-summary.json:2`-`80`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit
     columns.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while current observed `HEAD` reached `bde83721`.
   - Evidence: `porting.html:41`-`50` collapses benchmark source plus
     denominator into `Benchmark`, and PHP pass/fail plus mapped tests into
     `Mapped`, so it still omits the exact upstream-denominator and
     PHP-pass/fail column separation required by the goal.
   - Evidence: dashboard rows disagree with current manifests: Difftastic
     `160/417` vs `234/584`, Dolt `242/613` vs `420/613`, esbuild `164/2567`
     vs `212/2567`, Gitoxide `1432/2877` vs `1836/2877`, libsqlite `149/1454`
     vs `200/1589`, LightningCSS `773/3532` vs `1100/3532`, markerPDF
     `159/78` vs `196/251`, Pandoc `426/2028` vs `570/2276`, rclone
     `291/327` vs `402/2553`, Readability `1031/1984` vs `1428/1984`, and
     Syncthing `235/658` vs `301/658`. Quadrable mapped count remains `55/55`,
     but dashboard PHP count `108` is stale against lane status `130`.

4. **High - manifest, lane-status, and progress schemas still cannot produce
   trustworthy portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     all `lanes/*/lane-status.json`, `progress.md:31`-`42`,
     `porting-summary.json`, and `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     `goal.md:44`, and `goal.md:45` require real denominators, explicit slices,
     current coordination fields, and meaningful percentages.
   - Evidence: `benchmarkDenominator.total` is a prose string in Difftastic,
     Dolt, esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` is an object in many lanes,
     a string in Gitoxide, markerPDF, and Quadrable, and absent/null on the
     Pandoc manifest surface.
   - Evidence: PHP count units are mixed. Dolt records `258` PHP behavior
     tests, markerPDF records `306` behavior tests, Readability records `135`,
     while Gitoxide lane status uses `3246` and LightningCSS uses `1221`,
     which are assertion/check counts rather than comparable behavior-test
     counts.
   - Evidence: `latestCommit` fields still include prose or pending dirty-batch
     states in Difftastic, Dolt, Gitoxide, libsqlite, LightningCSS, markerPDF,
     rclone, Readability, and Syncthing. `progress.md:31`-`42` estimates remain
     stale against both current lane status text and manifest mapped counts.

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
   - Evidence: Gitoxide full Cargo workspace tests remain unexecuted; libsqlite
     has `veryquick` and focused Tcl evidence but not full SQLite all/release
     permutations; rclone excludes live provider, mount/FUSE, Docker, and
     `fstest/test_all` coverage; Syncthing still lacks `go test ./...`; Pandoc
     lacks the Haskell runner; markerPDF lacks the full Python benchmark/model
     stack; Quadrable passes `make -r test` but its full sync-fuzzer scope is
     explicitly outside the fast suite; Difftastic has no full Cargo runner.
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

Observed results during this audit:

```text
2988545 php tools/run-tests.php
2989545 php tools/run-tests.php lanes/syncthing/tests
2991186 php tools/run-tests.php
2992314 php tools/run-tests.php
2992617 php tools/run-tests.php
2994414 php tools/run-tests.php
2996267 php tools/run-tests.php
```

Latest owner evidence:

```text
2996267 claude 2984877 00:14 Rs php tools/run-tests.php
```

No duplicate root run was started. A later final exact gate briefly cleared, but
the tree was still not stable enough for a trustworthy aggregate run; a handoff
sanity check then found active root PID `2996267`.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
rg -n 'proc_open|shell_exec|exec\(|passthru|system\(' lanes -g '*.php'
```

Results: all lane upstream manifests were valid JSON at the time checked. The
shell-out scan found only `PDO::exec` calls in
`lanes/syncthing/src/SqliteCheckpointStore.php`, not PHP process shell-outs.

Recent history reviewed:

```text
bde83721 Advance libsqlite replacement balancing
fbcf368f Record esbuild private accessor status
0cdef3ec Port esbuild private accessor decorators
a0c98a72 Refresh independent audit status
ed415770 Record pandoc lane status
9d9122d4 Record quadrable lane commit pointer
1dbf28af Advance pandoc markdown writer references
35aa3bed Advance quadrable CLI command parity
f996fca1 Refresh independent audit status
1c553d5d Record esbuild lane status
98ccd4af Port esbuild decorator helper slices
212a5189 Refresh independent audit status
```

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped,
PHP pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
