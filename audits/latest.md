# Independent Audit - 2026-05-23T15:06:21Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, dirty tree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `5b71aedfc582` (`Refresh independent audit
status`). The latest 15 commits sampled by `git log --oneline --name-only -n
15` are all audit-only `Refresh independent audit status` commits touching
`audits/latest.md` and `progress.md`, while lane implementation, manifest, and
status files remain broadly dirty.

Manifest/status snapshot reviewed:

| Lane | Manifest denominator | Manifest mapped | Lane PHP pass/fail | Lane estimate |
| --- | ---: | ---: | ---: | ---: |
| difftastic | prose/string `595 inspected...` | 288 | 288 / 0 | 94% |
| dolt | prose/string `613 upstream...` | 546 | 304 / 0 | 94% |
| esbuild | prose/string `2,567 counted...` | 241 | 243 / 0 | 76% |
| gitoxide | 2877 | 2001 | 3798 / 0 | 98% |
| libsqlite | 1589 | 237 | 237 / 0 | 98% |
| lightningcss | 3532 | 1308 | 1462 / 0 | 86% |
| markerPDF | 283 | 231 | 347 / 0 | 93% |
| pandoc | prose/string `2276 upstream...` | 711 | 226 / 0 | 96% |
| quadrable | prose/string `55 tracked...` | 55 | 149 / 0 | 99% |
| rclone | 1601 | 507 | 507 / 0 | 98% |
| readability | 1984 | 1757 | 159 / 0 | 97% |
| syncthing | 658 | 376 | 376 / 0 | 98% |

## Findings

1. **Critical - the repository is not stable enough for an accepted aggregate
   root-test baseline.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `.tmux-team/prompts/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `lanes/*/lane-status.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` requires capped lane work, current
     owner/session tracking, small committed slices with passing tests,
     periodic repo-wide tests, and failures recorded honestly.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` still
     reports every lane session as `stopped`.
   - Evidence: active process sampling found live `run-tmux-agent.sh` sessions
     for Difftastic, Dolt runner, Dolt, esbuild, Gitoxide, libsqlite,
     LightningCSS, markerPDF, Pandoc, Quadrable, rclone, Readability,
     Syncthing, auditor, integrator, and nine capacity/acceptance agents, plus
     watchdog, evaluator, capacity-controller, and dashboard-updater loops.
   - Evidence: dirty-tree samples reported `1895` default
     `git status --short` rows, `189` tracked dirty rows, `1744` untracked
     rows, and `189 files changed, 73524 insertions(+), 7138 deletions(-)`.
   - Evidence: the required duplicate-root gate matched PID `1752402`
     (`php tools/run-tests.php lanes/syncthing/tests lanes/libsqlite/tests`);
     the process exited before owner sampling, so owner evidence was not
     recoverable. No duplicate root run was started.
   - Audit judgment: no aggregate PHP result should be accepted until writers,
     status publishers, and broad lane/focused test loops are frozen and the
     root test is run from one known snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still
   do not satisfy the dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`9`.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with
     average progress and per-lane columns for library, suite progress,
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:30`-`33` still publishes average progress
     `68.8%`, generated time `2026-05-23 04:57:16 UTC`, and source commit
     `bda83c6b93d4`; sampled `HEAD` is `5b71aedfc582`.
   - Evidence: `porting-summary.json:2`-`8` carries the same stale generated
     time, source commit, dashboard commit, and average progress.
   - Evidence: published rows disagree with current lane files. Difftastic is
     published as `55%`, mapped `160`, and `160 pass` (`porting.html:54`),
     while current files report `94%`, mapped `288`, and `288` PHP pass.
     rclone is published as denominator `327` and mapped `291`
     (`porting.html:63`), while the current manifest/status report
     denominator `1601`, mapped `507`, and `507` PHP pass. Syncthing is
     published as mapped/pass `235` (`porting.html:65`), while current files
     report `376`.
   - Evidence: `porting.html:41`-`50` still collapses benchmark source,
     upstream denominator, mapped tests, and PHP pass/fail into compact
     `Benchmark` and `Mapped` cells instead of the first-class columns the
     goal asks for.

3. **High - `progress.md` and lane status files are aligned to neither the
   current tree nor recent Git history.**
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
     claimed lane slices; the latest sampled 15 commits are audit-only updates.

4. **High - manifest denominator, mapped-test, and PHP-pass units are still
   non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator, mapped upstream tests, PHP passing/failing counts, and
     dashboard fields that can be compared across lanes.
   - Evidence: five manifests still use prose strings as
     `benchmarkDenominator.total`: Difftastic, Dolt, esbuild, Pandoc, and
     Quadrable. Other lanes use numeric totals.
   - Evidence: mapped units and PHP pass units are different measures but are
     displayed as if comparable. Gitoxide has `2001` mapped units but `3798`
     PHP passes; LightningCSS has `1308` mapped and `1462` PHP passes;
     markerPDF has `231` mapped and `347` PHP passes; Readability has `1757`
     mapped upstream checks but only `159` PHP behavior tests.
   - Evidence: some lanes count file/path inventories, some count upstream test
     functions, some count helper invocations, some count behavior assertions,
     and some mix bounded runner evidence with static inventory in the same
     field.

5. **High - near-complete percentages mask explicit upstream-runner and hard
   feature gaps.**
   - Paths: `lanes/gitoxide/lane-status.json`,
     `lanes/libsqlite/lane-status.json`, `lanes/markerpdf/lane-status.json`,
     `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/syncthing/lane-status.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of
     truth, passing tests are not enough, and hard features must be marked as
     blockers or future slices rather than silently skipped.
   - Evidence: Gitoxide is `98%` while full Cargo workspace parity is still
     not executed. Pandoc is `96%` while the Haskell runner remains
     unexecuted. Syncthing is `98%` while full `go test ./...` remains
     unexecuted. markerPDF is `93%` while full upstream benchmark execution,
     live Streamlit/CLI paths, multiprocessing, and heavy model dependencies
     remain unexecuted.
   - Evidence: libsqlite, rclone, and Quadrable also report `98%`/`99%` while
     broad upstream surfaces remain explicitly outside the current accepted
     native PHP evidence: SQLite full all/release permutations and SQL engine
     breadth, rclone live provider/mount parity, and Quadrable long sync
     fuzzer/benchmark runs.

6. **Medium - root-test and verification records across lanes do not describe
   one accepted integration checkpoint.**
   - Paths: `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/libsqlite/lane-status.json`, `lanes/markerpdf/lane-status.json`,
     `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`,
     `progress.md:335`.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     honest failure recording.
   - Evidence: current lane statuses mix focused-only green results, pending
     aggregate verification, duplicate-root skips, and historical red/green
     root anecdotes. Those records may be locally true, but they are not one
     frozen-tree root baseline.
   - Evidence: `progress.md:335` already records the same pattern from the
     previous audit: a clear early gate, later duplicate-root evidence, active
     writer loops, and a broad dirty tree.

7. **Medium - shell bridge risk is currently controlled by scan, but generated
   and supplied evidence still dominates several lanes.**
   - Paths: `lanes/syncthing/src/SqliteCheckpointStore.php`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` says bridge code, generated fixtures,
     supplied fixtures, or shell-outs must not count as native implementation
     progress unless explicitly temporary oracle tooling.
   - Evidence: `rg -n '\b(shell_exec|exec|proc_open|passthru|system)\s*\('
     lanes --glob '*.php'` found only PDO `exec()` calls in
     `SqliteCheckpointStore.php`; no PHP shell bridge was found by that
     pattern.
   - Evidence: the higher risk is still accounting, not direct shell-out
     usage: markerPDF, Readability, and rclone use supplied/generated fixtures,
     static inventories, bounded runner evidence, and provider/model skips in
     ways that must remain clearly separated from native parity claims.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed gate result during audit sampling:

```text
1752402 php tools/run-tests.php lanes/syncthing/tests lanes/libsqlite/tests
```

Owner evidence:

```text
PID 1752402 exited before `ps -o pid,user,ppid,stat,etime,args -p 1752402`
could capture owner evidence.
```

No duplicate root run was started. The tree was not stable enough for this
auditor to start a root harness even after that focused harness exited: active
lane agents, capacity work, watchdog/status publisher loops, a very broad dirty
worktree, stale dashboard artifacts, and non-comparable lane verification
records mean a root result would not represent one accepted snapshot.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
jq -r 'input_filename as $f | .benchmarkDenominator as $b | [($f|split("/")[1]), ($b.total|type), ($b.total|tostring|.[0:140]), ($b.mapped|type), ($b.mapped|tostring)] | @tsv' lanes/*/UPSTREAM_TEST_MANIFEST.json
jq -r 'input_filename as $f | [($f|split("/")[1]), (.estimatedProgress|tostring), (.phpPass|tostring), (.phpFail|tostring), (.latestCommit|tostring|.[0:160]), (.blocker|tostring|.[0:220])] | @tsv' lanes/*/lane-status.json
jq -r '[.generated, .dashboardCommitShort, .sourceCommitShort, (.averageProgressPercent|tostring), (.lanes|length|tostring)] | @tsv' porting-summary.json
git log --oneline --name-only -n 15
git status --short
git status --short --untracked-files=no
git ls-files --others --exclude-standard
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
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
