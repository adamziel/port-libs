# Independent Audit - 2026-05-23T15:12:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, dirty tree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `f356aa702b94` (`Refresh independent audit
status`). The latest 20 commits sampled by `git log --oneline --name-only -n
20` are all audit-only `Refresh independent audit status` commits touching
`audits/latest.md` and `progress.md`, while lane implementation, manifest, and
status files remain broadly dirty.

Manifest/status snapshot reviewed:

| Lane | Manifest denominator | Manifest mapped | Lane PHP pass/fail | Lane estimate |
| --- | ---: | ---: | ---: | ---: |
| difftastic | prose/string `597 inspected...` | 291 | 288 / 0 | 94% |
| dolt | prose/string `613 upstream...` | 554 | 305 / 0 | 94% |
| esbuild | prose/string `2,567 counted...` | 243 | 243 / 0 | 76% |
| gitoxide | 2877 | 2001 | 3816 / 0 | 98% |
| libsqlite | 1589 | 238 | 238 / 0 | 98% |
| lightningcss | 3532 | 1308 | 1477 / 0 | 86% |
| markerPDF | 284 | 232 | 352 / 0 | 94% |
| pandoc | prose/string `2276 upstream...` | 711 | 226 / 0 | 96% |
| quadrable | prose/string `55 tracked...` | 55 | 149 / 0 | 99% |
| rclone | 1601 | 511 | 511 / 0 | 98% |
| readability | 1984 | 1772 | 159 / 0 | 97% |
| syncthing | 658 | 376 | 376 / 0 | 98% |

## Findings

1. **Critical - the repository is not stable enough for an accepted aggregate root-test baseline.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `.tmux-team/prompts/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-php-dirty-root.sh`,
     `lanes/*/lane-status.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` requires capped lane work, current
     owner/session tracking, small committed slices with passing tests,
     periodic repo-wide tests, and failures recorded honestly.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` still
     reports every lane session as `stopped`.
   - Evidence: active process sampling found live `run-tmux-agent.sh` sessions
     for Difftastic, Dolt, Dolt runner, esbuild, Gitoxide, libsqlite,
     LightningCSS, markerPDF, Pandoc, Quadrable, rclone, Readability,
     Syncthing, auditor, and integrator, plus watchdog, evaluator,
     capacity-controller, dashboard-updater, a dirty-root wrapper, and broad
     Dolt BATS processes.
   - Evidence: dirty-tree samples reported `1928` default
     `git status --short` rows, `189` tracked dirty rows, `1777` untracked
     files, and `189 files changed, 74048 insertions(+), 7164 deletions(-)`.
   - Evidence: the required duplicate-root gate initially matched PID
     `1763933` (`php tools/run-tests.php`). `ps -o pid,user,ppid,stat,etime,args
     -p 1763933` returned only the header because the process exited before
     owner sampling. A later exact gate was clear, but no root run was started
     because the tree remained non-quiescent.
   - Audit judgment: no aggregate PHP result should be accepted until writers,
     status publishers, and broad lane/focused test loops are frozen and the
     root test is run from one known snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still do not satisfy the dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`8`.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with
     average progress and per-lane columns for library, suite progress,
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:30`-`33` still publishes average progress
     `68.8%`, generated time `2026-05-23 04:57:16 UTC`, and source commit
     `bda83c6b93d4`; sampled `HEAD` is `f356aa702b94`.
   - Evidence: `porting-summary.json:2`-`8` carries the same stale generated
     time, source commit, dashboard commit, and average progress.
   - Evidence: published rows disagree with current lane files. Difftastic is
     published as `55%`, mapped `160`, and `160 pass` (`porting.html:54`),
     while current files report `94%`, manifest mapped `291`, and `288` PHP
     pass. rclone is published as denominator `327` and mapped/pass `291`
     (`porting.html:63`), while current files report denominator `1601`,
     mapped `511`, and `511` PHP pass. markerPDF is published as denominator
     `78`, mapped `159`, and `264 pass` (`porting.html:60`), while current
     files report denominator `284`, mapped `232`, and `352` PHP pass.
   - Evidence: `porting.html:41`-`50` still collapses benchmark source,
     upstream denominator, mapped tests, and PHP pass/fail into compact
     `Benchmark` and `Mapped` cells instead of the first-class columns the
     goal asks for.

3. **High - `progress.md` and lane status files are aligned to neither the current tree nor recent Git history.**
   - Paths: `progress.md:31`-`42`, `lanes/*/lane-status.json`, recent Git
     history.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and small committed slices with passing tests.
   - Evidence: `progress.md:31`-`42` still shows stopped-lane estimates from
     `5%` to `66%`, while current lane statuses claim `76%` to `99%`.
   - Evidence: current `latestCommit` fields are mostly not accepted commits:
     examples include `pending in shared dirty worktree`, `not committed`,
     `uncommitted port-esbuild lane batch`, `pending - current Gitoxide...`,
     `uncommitted lane-scoped changes`, and `pending lane-local changes`.
   - Evidence: recent history does not show integration commits for these
     claimed lane slices; the latest sampled 20 commits are audit-only updates.

4. **High - manifest denominator, mapped-test, and PHP-pass units are still non-normalized and sometimes internally inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`20`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:604`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1086`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator, mapped upstream tests, PHP passing/failing counts, and
     dashboard fields that can be compared across lanes.
   - Evidence: five manifests still use prose strings as
     `benchmarkDenominator.total`: Difftastic, Dolt, esbuild, Pandoc, and
     Quadrable. Other lanes use numeric totals.
   - Evidence: mapped units and PHP pass units are different measures but are
     displayed as if comparable. Gitoxide has `2001` mapped units but `3816`
     PHP passes; LightningCSS has `1308` mapped and `1477` PHP passes;
     markerPDF has `232` mapped and `352` PHP passes; Readability has `1772`
     mapped upstream checks but only `159` status PHP passes.
   - Evidence: internal fields also disagree. Difftastic's current manifest
     mapped count is `291` while lane status reports `288` PHP passes;
     Readability's manifest `phpBehaviorTests` is `160` while lane status
     reports `159`; Syncthing's warning text still says native progress maps
     `359` focused lane tests while current manifest/status counts are `376`.
   - Evidence: some lanes count file/path inventories, some count upstream test
     functions, some count helper invocations, some count behavior assertions,
     and some mix bounded runner evidence with static inventory in the same
     field.

5. **High - near-complete percentages mask explicit upstream-runner and hard feature gaps.**
   - Paths: `lanes/gitoxide/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:4`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of
     truth, passing tests are not enough, and hard features must be marked as
     blockers or future slices rather than silently skipped.
   - Evidence: Gitoxide is `98%` while full Cargo workspace parity is still not
     executed. Pandoc is `96%` while the Haskell runner remains unexecuted.
     Syncthing is `98%` while full `go test ./...` remains unexecuted.
     markerPDF is `94%` while full upstream benchmark execution, live Streamlit
     paths, top-level multiprocessing, and heavy model dependencies remain
     unexecuted.
   - Evidence: libsqlite and rclone also report `98%` while broad upstream
     surfaces remain outside accepted native PHP evidence: SQLite full
     all/release permutations and SQL engine breadth, plus rclone live
     provider/mount parity.

6. **Medium - root-test and verification records across lanes do not describe one accepted integration checkpoint.**
   - Paths: `lanes/dolt/lane-status.json:12`-`13`,
     `lanes/esbuild/lane-status.json:12`-`13`,
     `lanes/libsqlite/lane-status.json:12`-`13`,
     `lanes/markerpdf/lane-status.json:12`-`13`,
     `lanes/readability/lane-status.json:12`-`13`,
     `lanes/syncthing/lane-status.json:12`-`13`,
     `progress.md:336`.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     honest failure recording.
   - Evidence: current lane statuses mix focused-only green results, pending
     aggregate verification, duplicate-root skips, and historical red/green
     root anecdotes. Those records may be locally true, but they are not one
     frozen-tree root baseline.
   - Evidence: `progress.md:336` already records the same pattern from the
     previous audit: a duplicate PHP harness, active writer/status processes,
     a broad dirty tree, stale dashboard artifacts, and non-comparable lane
     commit/root-test records.

7. **Medium - shell bridge risk is controlled by scan, but generated and supplied evidence still dominates several lanes.**
   - Paths: `lanes/syncthing/src/SqliteCheckpointStore.php:21`,
     `lanes/syncthing/src/SqliteCheckpointStore.php:186`,
     `lanes/syncthing/src/SqliteCheckpointStore.php:195`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:515`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:598`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` says bridge code, generated fixtures,
     supplied fixtures, or shell-outs must not count as native implementation
     progress unless explicitly temporary oracle tooling.
   - Evidence: `rg -n '\b(shell_exec|exec|proc_open|passthru|system)\s*\('
     lanes --glob '*.php'` found only PDO `exec()` calls in
     `SqliteCheckpointStore.php`; no PHP shell bridge was found by that
     pattern.
   - Evidence: the higher risk is accounting, not direct shell-out usage:
     markerPDF, Readability, and rclone continue to rely on supplied/generated
     fixtures, static inventories, bounded runner evidence, and provider/model
     skips in ways that must remain clearly separated from native parity
     claims.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed initial gate result:

```text
1763933 php tools/run-tests.php
```

Owner evidence:

```text
PID 1763933 exited before `ps -o pid,user,ppid,stat,etime,args -p 1763933`
could capture owner evidence; the `ps` command returned only the header row.
```

Later exact gate sample returned no rows. After the first audit commit, handoff
samples found PID `1824117` (`php tools/run-tests.php`) and then PID `1835672`
(`php tools/run-tests.php lanes/quadrable/tests`); both exited before `ps`
could capture owner evidence and later exact gate sampling returned no rows. No
duplicate root run was started. The tree was not stable enough for this auditor
to start a root harness even after exact gates cleared: active lane agents,
capacity work, watchdog/status publisher loops, a dirty-root wrapper, broad
Dolt BATS, a very broad dirty worktree, stale dashboard artifacts, and
non-comparable lane verification records mean a root result would not represent
one accepted snapshot.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
jq -r 'input_filename as $f | .benchmarkDenominator as $b | [($f|split("/")[1]), (.upstream.name // .library // .name // ""), (.phase // ""), (.benchmarkSource // .source // ""), ($b.total|type), ($b.total|tostring), ($b.mapped|type), ($b.mapped|tostring)] | @tsv' lanes/*/UPSTREAM_TEST_MANIFEST.json
jq -r 'input_filename as $f | [($f|split("/")[1]), (.estimatedProgress|tostring), (.phase|tostring), (.phpPass|tostring), (.phpFail|tostring), (.latestCommit|tostring), (.blocker|tostring)] | @tsv' lanes/*/lane-status.json
jq -r '[.generated, .dashboardCommitShort, .sourceCommitShort, (.averageProgressPercent|tostring), (.lanes|length|tostring)] | @tsv' porting-summary.json
git log --oneline --name-only -n 20
git status --short
git status --short --untracked-files=no
git ls-files --others --exclude-standard
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -o pid,user,ppid,stat,etime,args -p 1763933
pgrep -af '(run-tmux-agent|run-team-watchdog|run-capacity-controller|run-dashboard-updater|run-evaluator|run-integrator|run-php-|run-dolt|run-php-clean-head-root|run-sqlite|cargo test|go test|bats|testrunner\.tcl|npm test)'
rg -n '\b(shell_exec|exec|proc_open|passthru|system)\s*\(' lanes --glob '*.php'
```

## Next Best Step

Freeze active writers, status publishers, capacity jobs, and duplicate
root/focused PHP loops; validate all manifests from the frozen tree; accept or
reject dirty lane batches one lane at a time; normalize denominator, mapped,
PHP pass/fail, runner, blocker, and commit fields; regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from the same
accepted snapshot; then rerun the exact duplicate-root gate and capture one
quiesced root `php tools/run-tests.php` run only if the gate remains empty.
