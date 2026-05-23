# Independent Audit - 2026-05-23T13:13:51Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, dirty tree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `c1f67cdaaa43` (`Refresh independent audit
status`). Recent history reviewed includes `c1f67cda`, `d52cc007`,
`24260634`, `51867989`, `b75226d1`, `30be5e3c`, `90d1fa3b`,
`81419ac3`, `69405063`, `0f1444c1`, `efa4e0c2`, and `f8bd46e4`.

## Findings

1. **Critical - the repository is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json`, `porting.html`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped workers,
     current owner/session tracking, small reviewable committed slices,
     supervisor verification, and honest repo-wide testing.
   - Evidence: `progress.md:25` still says the current launch target is 2
     implementation lanes plus 1 auditor, and `progress.md:31`-`42` still
     reports every lane session as `stopped`.
   - Evidence: active process sampling found lane/watchdog/status capacity
     work in progress for Dolt, esbuild, LightningCSS, libsqlite,
     Difftastic, Syncthing, Gitoxide, Readability, markerPDF, Quadrable,
     rclone, Pandoc, the auditor, the integrator, the evaluator, and the
     dashboard updater. Bounded Dolt BATS and SQLite all-suite runners were
     also active.
   - Evidence: the dirty tree remains broad: latest pre-edit samples reported
     `1604` default `git status --short --untracked-files=all` rows, `168`
     tracked changed files, and `168 files changed, 54391 insertions(+),
     5086 deletions(-)`.
   - Audit judgment: focused lane green results, progress percentages, and
     dashboard data are not acceptance evidence until active writers are
     frozen and one regenerated snapshot is validated.

2. **High - the published dashboard remains stale and still fails the required
   dashboard contract.**
   - Paths: `porting.html:32`-`36`, `porting.html:40`-`65`,
     `porting-summary.json:2`-`8`, `porting-summary.json:10`-`213`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8`
     still publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `c1f67cdaaa43`.
   - Evidence: `porting.html:40`-`50` still collapses required fields into
     compact `Benchmark` and `Mapped` cells instead of separate benchmark
     source, upstream denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifest/status evidence:

     | Lane | Dashboard denominator/mapped/php | Current manifest/status evidence |
     | --- | --- | --- |
     | difftastic | `417` / `160` / `160 pass` | manifest `587` / `268`; status php `268` |
     | dolt | `613` / `242` / `193 pass` | manifest `613` / `499`; status php `295` |
     | esbuild | `2567` / `164` / `164 pass` | manifest `2567` / `230`; status php `230` |
     | gitoxide | `2877` / `1432` / `2646 pass` | manifest `2877` / `1939`; status php `3542` |
     | libsqlite | `1454` / `149` / `149 pass` | manifest `1589` / `217`; status php `216` |
     | LightningCSS | `3532` / `773` / `906 pass` | manifest `3532` / `1210`; status php `1337` |
     | markerPDF | `78` / `159` / `264 pass` | manifest `276` / `222`; status php `334` |
     | pandoc | `2028` / `426` / `164 pass` | manifest `2276` / `657`; status php `215` |
     | quadrable | `55` / `55` / `108 pass` | manifest `55` / `55`; status php `140` |
     | rclone | `327` / `291` / `291 pass` | manifest `2553` / `468`; status php `468` |
     | readability | `1984` / `1031` / `107 pass` | manifest `1984` / `1609`; status php `149` |
     | syncthing | `658` / `235` / `235 pass` | manifest `658` / `342`; status php `342` |

3. **High - manifest/status schemas still cannot produce trustworthy
   portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     mapped upstream tests, PHP passing/failing counts, and generated
     dashboard fields backed by those values.
   - Evidence: denominator units remain mixed. `gitoxide`, `libsqlite`,
     `LightningCSS`, `markerPDF`, `rclone`, `readability`, and `syncthing`
     expose numeric totals; `difftastic`, `dolt`, `esbuild`, `pandoc`, and
     `quadrable` expose prose totals under `benchmarkDenominator.total`.
   - Evidence: mapped-test units and PHP-pass units are not normalized.
     Examples: `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:16` maps `217`
     while `lanes/libsqlite/lane-status.json:6` reports `216` PHP passes;
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15` maps `1609` while
     `lanes/readability/lane-status.json:6` reports `149` PHP passes;
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15` maps `1939` while
     `lanes/gitoxide/lane-status.json:6` reports `3542` PHP assertions.
   - Evidence: runner status fields still mix upstream runner results,
     static inventory rationale, PHP counts, skipped-suite rationale, root
     harness anecdotes, and blockers in strings or objects, which prevents a
     generated dashboard from comparing lanes without per-lane special cases.

4. **High - repo-wide PHP test evidence remains non-comparable.**
   - Paths: `tools/run-tests.php`, `progress.md`,
     `lanes/*/lane-status.json:10`-`13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:48`, and `goal.md:49` require committed slices with passing
     tests, meaningful coverage, supervisor verification, and honest
     repo-wide test/static-check records.
   - Evidence: lane statuses disagree about root status. `lanes/dolt/lane-status.json:10`
     records a no-argument root pass with `226` files and `26720`
     assertions; `lanes/esbuild/lane-status.json:10`, `lanes/libsqlite/lane-status.json:10`,
     and `lanes/rclone/lane-status.json:10` record duplicate-root blocking;
     `lanes/pandoc/lane-status.json:10` and `lanes/syncthing/lane-status.json:10`
     record no root run because broad upstream runners were active; and
     `lanes/markerpdf/lane-status.json:10` says a fresh root result was not
     required for focused handoff.
   - Evidence: the latest exact duplicate-root gate returned no active
     no-argument `php tools/run-tests.php` process, but the stability gate
     still failed because active lane agents/status publishers plus Dolt BATS
     and SQLite `testrunner.tcl --jobs 8` were running.
   - Audit judgment: the next accepted test result must be one quiesced,
     no-argument `php tools/run-tests.php` run from one accepted tree, not
     lane-local anecdotes gathered while the checkout is moving.

5. **Medium - high progress claims are attached to pending dirty batches.**
   - Paths: `progress.md:31`-`42`, `porting.html:54`-`65`,
     `lanes/*/lane-status.json:13`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:30`,
     `goal.md:36`, and `goal.md:48` require small committed native slices,
     no bridge/generated progress credit, and supervisor acceptance before
     assigning the next work.
   - Evidence: most lane statuses explicitly report `pending`,
     `uncommitted`, or dirty-batch prose in `latestCommit`, while
     `porting.html:54`-`65` still publishes progress between `50%` and `96%`
     for lanes whose current work is not tied to accepted commits.
   - Evidence: recent history is dominated by audit/status commits around a
     few lane commits, while the worktree still has `168` tracked changed
     files and extensive untracked artifacts. This is not the small,
     reviewable-slice boundary required by the goal.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Latest exact gate result in this audit:

```text
<no rows>
```

No duplicate root run was started because the stability condition still failed.
Active broad runner/process evidence included:

```text
237222 claude timeout 90m bats verify-constraints.bats ... keyless-foreign-keys.bats
237253 claude /usr/bin/bash /usr/libexec/bats-core/bats verify-constraints.bats ...
237260 claude /usr/bin/bash /usr/libexec/bats-core/bats-exec-suite ...
3854382 claude timeout 3600 ./testfixture ../src/test/testrunner.tcl --jobs 8 --stop-on-error all
3854383 claude ./testfixture ../src/test/testrunner.tcl --jobs 8 --stop-on-error all
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af 'run-tmux-agent|capacity|dashboard|evaluator|integrator|auditor|artifact|verifier|run-tests\.php|testrunner\.tcl|bats|go test|cargo test|npm test'
rg -n '\b(proc_open|shell_exec|passthru|system|popen)\s*\(|\bexec\s*\(' lanes/*/src lanes/*/tests lanes/*/examples
git log --oneline --decorate -n 25
git show --stat --oneline --decorate --no-renames HEAD
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON. The shell-out scan found only
`PDO::exec()` calls in `lanes/syncthing/src/SqliteCheckpointStore.php`, not
bridge calls to external binaries. Latest pre-edit samples reported `1604`
default status rows, `168` tracked changed files, and `168 files changed,
54391 insertions(+), 5086 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and broad upstream runners first. Then
validate manifests from the frozen tree, accept or reject dirty lane batches one
lane at a time, normalize manifest/status denominator, mapped, PHP pass/fail,
runner, progress, and commit fields, regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from that same accepted snapshot,
rerun the exact duplicate-root gate, and capture one quiesced no-argument
`php tools/run-tests.php` result.
