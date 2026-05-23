# Independent Audit - 2026-05-23T13:24:03Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, dirty tree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `66798317d1f0` (`Refresh independent audit
status`). Recent history reviewed includes `66798317`, `be7cf14f`,
`c1f67cda`, `d52cc007`, `24260634`, `51867989`, `b75226d1`,
`30be5e3c`, `90d1fa3b`, `81419ac3`, `69405063`, `0f1444c1`,
`efa4e0c2`, `f8bd46e4`, and `09995598`.

## Findings

1. **Critical - the repository is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json`, `porting.html`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped workers,
     current owner/session tracking, small reviewable committed slices,
     supervisor verification, and honest repo-wide testing.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` still
     reports every lane session as `stopped`.
   - Evidence: active process sampling found lane/watchdog/status work for
     Dolt, Difftastic, Readability, Quadrable, Pandoc, esbuild, libsqlite,
     Syncthing, Gitoxide, LightningCSS, markerPDF, rclone, the auditor, the
     integrator, the evaluator, the dashboard updater, and the capacity
     controller. Broad Dolt BATS, SQLite `testrunner.tcl --jobs 8`,
     Syncthing `go test`, and LightningCSS `cargo test` runs were also active.
   - Evidence: the dirty tree widened again during the audit: latest samples
     reported `1633` default `git status --short --untracked-files=all` rows,
     `169` tracked changed files, and `169 files changed, 56201 insertions(+),
     5257 deletions(-)`.
   - Audit judgment: focused lane green results, progress percentages, and
     dashboard values are not acceptance evidence until active writers are
     frozen and one regenerated snapshot is validated.

2. **High - the published dashboard is stale and fails the required dashboard
   contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:40`-`65`,
     `porting-summary.json:1`-`215`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `66798317d1f0`.
   - Evidence: `porting.html:40`-`50` still collapses required fields into
     compact `Benchmark` and `Mapped` cells rather than separate benchmark
     source, upstream denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifest/status evidence:

     | Lane | Dashboard denominator / mapped / PHP | Current manifest/status evidence |
     | --- | --- | --- |
     | difftastic | `417 / 160 / 160` | `588 / 271 / 271` |
     | dolt | `613 / 242 / 193` | `613 / 499 / 296` |
     | esbuild | `2567 / 164 / 164` | `2567 / 230 / 230` |
     | gitoxide | `2877 / 1432 / 2646` | `2877 / 1943 / 3586` |
     | libsqlite | `1454 / 149 / 149` | `1589 / 217 / 217` |
     | LightningCSS | `3532 / 773 / 906` | `3532 / 1216 / 1349` |
     | markerPDF | `78 / 159 / 264` | `276 / 223 / 335` |
     | pandoc | `2028 / 426 / 164` | `2276 / 665 / 216` |
     | quadrable | `55 / 55 / 108` | `55 / 55 / 142` |
     | rclone | `327 / 291 / 291` | `2553 / 475 / 475` |
     | readability | `1984 / 1031 / 107` | `1984 / 1624 / 151` |
     | syncthing | `658 / 235 / 235` | `658 / 349 / 347` |

3. **High - manifest, status, and progress schemas still cannot produce
   trustworthy portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `progress.md:31`-`42`,
     `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     mapped upstream tests, PHP passing/failing counts, and generated
     dashboard fields backed by those values.
   - Evidence: denominator units remain mixed. Some manifests expose numeric
     totals (`gitoxide`, `libsqlite`, `LightningCSS`, `markerPDF`, `rclone`,
     `readability`, `syncthing`), while others expose prose totals
     (`difftastic`, `dolt`, `esbuild`, `pandoc`, `quadrable`).
   - Evidence: mapped-test units and PHP-pass units are not normalized:
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json` maps `1624` while
     `lanes/readability/lane-status.json` reports `151` PHP passes;
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` maps `1943` while
     `lanes/gitoxide/lane-status.json` reports `3586`; `lanes/markerpdf`
     maps `223` while status reports `335`.
   - Evidence: status changed under the audit surface. `syncthing` was sampled
     at `347` mapped manifest units earlier in this audit and later at `349`,
     while `lanes/syncthing/lane-status.json` still reports `347` PHP passes.
   - Evidence: progress estimates conflict across files. `progress.md:31`-`42`
     still shows estimates from `5%` to `66%`, `porting.html:54`-`65` shows
     `50%` to `96%`, and current lane statuses show `72%` to `99%`.

4. **High - repo-wide PHP test evidence remains non-comparable.**
   - Paths: `tools/run-tests.php`, `progress.md`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:48`, and `goal.md:49` require committed slices with passing
     tests, meaningful coverage, supervisor verification, and honest
     repo-wide test/static-check records.
   - Evidence: the required exact duplicate-root gate returned no active
     no-argument `php tools/run-tests.php` rows in the audit sample, but broad
     upstream runners were active: Dolt BATS PIDs `237222`, `237253`,
     `237260`, `237261`, `237262`, SQLite `testrunner.tcl` PIDs `3854382`
     and `3854383`, Syncthing `go test` PIDs `437425` and `437426`, and
     LightningCSS `cargo test` PIDs `438325` and `438326`.
   - Evidence: lane status files mix focused green lane results, pending root
     states, upstream runner anecdotes, and dirty-batch handoff prose. That is
     not comparable to a quiesced no-argument root test from one accepted tree.
   - Audit judgment: the next accepted test result must be one quiesced
     `php tools/run-tests.php` run after active writers and broad runners stop.

5. **Medium - high progress claims are attached to unaccepted dirty batches.**
   - Paths: `lanes/*/lane-status.json:13`, `progress.md:31`-`42`,
     `porting.html:54`-`65`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:30`,
     `goal.md:36`, and `goal.md:48` require small committed native slices,
     no bridge/generated progress credit, and supervisor acceptance before
     assigning the next work.
   - Evidence: current lane `latestCommit` fields are mostly `pending`,
     `uncommitted`, `not committed`, `current`, `HEAD ...`, or prose dirty
     batch descriptions rather than accepted commit IDs, while lane statuses
     claim `72%` to `99%` progress.
   - Evidence: recent history is dominated by audit/status commits around a
     small number of lane commits, while the worktree still has `169` tracked
     changed files and extensive untracked artifacts.

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

No root run was started because the stability condition failed. Active
broad-runner evidence included:

```text
237222 timeout 90m bats verify-constraints.bats ... keyless-foreign-keys.bats
237253 /usr/bin/bash /usr/libexec/bats-core/bats verify-constraints.bats ...
237260 /usr/bin/bash /usr/libexec/bats-core/bats-exec-suite ...
3854382 timeout 3600 ./testfixture ../src/test/testrunner.tcl --jobs 8 --stop-on-error all
3854383 ./testfixture ../src/test/testrunner.tcl --jobs 8 --stop-on-error all
437425 timeout 20m env -i ... go test -count=1 ... ./lib/rand ... ./lib/config
437426 go test -count=1 -timeout=15m -p=2 -parallel=4 ./lib/rand ... ./lib/config
438325 timeout 30m env ... cargo test -p parcel_selectors -p lightningcss --lib --tests --locked --offline --jobs 3 -- --test-threads=2
438326 cargo test -p parcel_selectors -p lightningcss --lib --tests --locked --offline --jobs 3 -- --test-threads=2
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
bridge calls to external binaries. Latest samples reported `1633` default
status rows, `169` tracked changed files, and `169 files changed, 56201
insertions(+), 5257 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and broad upstream runners first. Then
validate manifests from the frozen tree, accept or reject dirty lane batches one
lane at a time, normalize manifest/status denominator, mapped, PHP pass/fail,
runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced no-argument `php tools/run-tests.php` result.
