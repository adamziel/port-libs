# Independent Audit - 2026-05-23T15:00:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, dirty tree state, active process
state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `50a8b9d5f025` (`Refresh independent audit
status`). The latest 12 commits sampled by `git log --oneline --name-only -n
12` are all audit-only `Refresh independent audit status` commits touching
`audits/latest.md` and `progress.md`, while lane files remain broadly dirty.

Manifest/status snapshot reviewed:

| Lane | Manifest denominator | Manifest mapped | Lane PHP pass/fail | Lane estimate |
| --- | ---: | ---: | ---: | ---: |
| difftastic | prose/string `595 inspected...` | 288 | 288 / 0 | 94% |
| dolt | prose/string `613 upstream...` | 546 | 304 / 0 | 94% |
| esbuild | prose/string `2,567 counted...` | 241 | 241 / 0 | 75% |
| gitoxide | 2877 | 2001 | 3772 / 0 | 98% |
| libsqlite | 1589 | 237 | 235 / 0 | 98% |
| lightningcss | 3532 | 1293 | 1462 / 0 | 86% |
| markerPDF | 283 | 231 | 345 / 0 | 93% |
| pandoc | prose/string `2276 upstream...` | 705 | 225 / 0 | 96% |
| quadrable | prose/string `55 tracked...` | 55 | 149 / 0 | 99% |
| rclone | 1601 | 507 | 507 / 0 | 98% |
| readability | 1984 | 1757 | 159 / 0 | 97% |
| syncthing | 658 | 375 | 375 / 0 | 98% |

## Findings

1. **Critical - the tree is still moving and is not stable enough for an
   accepted root baseline.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `.tmux-team/prompts/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `scripts/run-evaluator-loop.sh`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires capped lane work, current
     owner/session tracking, small committed slices with passing tests,
     periodic repo-wide tests, and failures recorded honestly.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` still
     reports every lane session as `stopped`.
   - Evidence: active process sampling found live `run-tmux-agent.sh` sessions
     for Dolt runner, markerPDF, Readability, Pandoc, Gitoxide, libsqlite,
     Quadrable, rclone, Difftastic, Dolt, esbuild, LightningCSS, Syncthing,
     auditor, integrator, and capacity acceptance work, plus watchdog,
     evaluator, capacity-controller, and dashboard-updater loops.
   - Evidence: dirty-tree samples reported `1881` default
     `git status --short` rows, `189` tracked dirty rows, `1728` untracked
     rows, and `189 files changed, 72707 insertions(+), 7105 deletions(-)`.
   - Evidence: manifest/status values moved again since the prior audit:
     Gitoxide mapped units are now `2001`, libsqlite `237`, rclone `507`,
     Readability `1757`, Syncthing `375`, and Quadrable PHP pass count `149`.
   - Audit judgment: no aggregate PHP result should be accepted until writers,
     status publishers, and broad lane runners are frozen and the test is run
     from one known snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale and do not
   satisfy the dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with
     average progress and per-lane columns for library, suite progress,
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:30`-`33` still publishes average progress
     `68.8%`, generated time `2026-05-23 04:57:16 UTC`, and source commit
     `bda83c6b93d4`; sampled `HEAD` is `50a8b9d5f025`.
   - Evidence: `porting-summary.json` also reports generated time
     `2026-05-23 04:57:16 UTC`, dashboard commit `8ba77df82902`, source
     commit `bda83c6b93d4`, average progress `68.8`, and 12 lanes.
   - Evidence: published rows disagree with current lane files. Difftastic is
     published as `55%`, mapped `160`, and `160 pass` (`porting.html:54`),
     while current files report `94%`, mapped `288`, and `288` PHP pass.
     markerPDF is published as denominator `78`, mapped `159`, and `264 pass`
     (`porting.html:60`), while current files report denominator `283`, mapped
     `231`, and `345` PHP pass. Readability is published as `61%`, mapped
     `1031`, and `107 pass` (`porting.html:64`), while current files report
     `97%`, mapped `1757`, and `159` PHP pass.
   - Evidence: `porting.html:41`-`50` still collapses benchmark source,
     upstream denominator, mapped tests, and PHP pass/fail into compact
     `Benchmark` and `Mapped` cells instead of the first-class columns the goal
     asks for.

3. **High - progress and lane status are misaligned with commit reality.**
   - Paths: `progress.md:31`-`42`, `lanes/*/lane-status.json`,
     recent Git history.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and small committed slices with passing tests.
   - Evidence: `progress.md:31`-`42` still shows stopped-lane estimates from
     `5%` to `66%`, while current lane statuses claim 75% to 99%.
   - Evidence: current `latestCommit` fields are mostly not accepted commits:
     examples include `pending in shared dirty worktree`, `not committed`,
     `uncommitted port-esbuild lane batch`, `pending - current Gitoxide...`,
     `uncommitted lane-scoped changes`, and `pending lane-local changes`.
   - Evidence: recent history does not show integration commits for these
     claimed lane slices; the latest sampled 12 commits are audit-only updates.

4. **High - manifest denominator and evidence units remain non-normalized, so
   portfolio percentages are not comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator, mapped upstream tests, PHP passing/failing counts, and
     dashboard fields that can be compared across lanes.
   - Evidence: five manifests still use prose strings as
     `benchmarkDenominator.total`: Difftastic, Dolt, esbuild, Pandoc, and
     Quadrable. Other lanes use numeric totals.
   - Evidence: mapped units and PHP pass units are different measures but are
     displayed as if comparable. Gitoxide has `2001` mapped units but `3772`
     PHP passes; LightningCSS has `1293` mapped and `1462` PHP passes;
     markerPDF has `231` mapped and `345` PHP passes; Readability has `1757`
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
     `lanes/rclone/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of
     truth, passing tests are not enough, and hard features must be marked as
     blockers or future slices rather than silently skipped.
   - Evidence: Gitoxide is `98%` while full Cargo workspace parity, broader
     refspec fixture parity, sparse-index writing, SHA-256 existing-pack reuse,
     and fuller merge semantics remain open.
   - Evidence: libsqlite is `98%` while broad write behavior,
     checkpoint/WAL reset, rollback-journal edge cases, general SQL execution,
     triggers, upserts, arbitrary expression indexes, planner shapes, and
     broader corruption validation remain open.
   - Evidence: markerPDF is `93%` while full upstream benchmark execution, live
     Streamlit execution, `convert.py` multiprocessing, GitHub Actions, and
     heavy Python/model dependencies remain unexecuted. Pandoc is `96%` while
     the Haskell runner remains unexecuted. rclone and Syncthing are `98%`
     while live provider/mount or broad `go test ./...` coverage remains open.
     Quadrable is `99%` while full sync-fuzzer and 100,000-record sync
     benchmark runs remain opt-in.

6. **Medium - root-test records across lane statuses cannot describe one
   accepted integration checkpoint.**
   - Paths: `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/libsqlite/lane-status.json`, `lanes/markerpdf/lane-status.json`,
     `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     honest failure recording.
   - Evidence: current lane statuses mix focused-only green results, pending
     root verification, duplicate-root skips, and references to earlier active
     root or focused PIDs. Those records may be locally true, but they are not
     one frozen-tree root baseline.
   - Evidence: the pre-edit exact duplicate-root gate was clear at sample time,
     but a post-commit handoff gate found an active no-argument root harness PID
     `1745128` owned by `claude`, plus focused Syncthing PID `1745176` owned by
     `claude`. A root run would still be untrustworthy because lane files,
     manifests, status files, and dashboard sources were changing during the
     audit.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed gate result before audit edits:

```text
<no rows>
```

Observed post-commit handoff gate:

```text
1745128 claude   php tools/run-tests.php
1745176 claude   php tools/run-tests.php lanes/syncthing/tests
```

No duplicate root run was started. The tree was not stable enough for this
auditor to start a root harness even before the post-commit root PID appeared:
active lane agents, capacity work, watchdog/status publisher loops,
integrator/auditor loops, a very broad dirty worktree, and changing
manifest/status values mean a root result would not represent one accepted
snapshot.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
jq -r 'input_filename as $f | .benchmarkDenominator as $b | [($f|split("/")[1]), ($b.total|type), ($b.total|tostring|.[0:120]), ($b.mapped|type), ($b.mapped|tostring)] | @tsv' lanes/*/UPSTREAM_TEST_MANIFEST.json
jq -r 'input_filename as $f | [$f, (.estimatedProgress|tostring), (.phpPass|tostring), (.phpFail|tostring), (.latestCommit|tostring|.[0:120]), (.blocker|tostring|.[0:180])] | @tsv' lanes/*/lane-status.json
jq -r '[.generated, .dashboardCommitShort, .sourceCommitShort, (.averageProgressPercent|tostring), (.lanes|length|tostring)] | @tsv' porting-summary.json
git log --oneline --name-only -n 12
git status --short
git status --short --untracked-files=no
git ls-files --others --exclude-standard
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af '(run-tmux-agent|run-team-watchdog|run-capacity-controller|run-dashboard-updater|run-evaluator|run-integrator|run-dolt|run-php-|run-sqlite|cargo test|go test|bats|testrunner\.tcl|npm test)'
rg -n '\b(shell_exec|exec|proc_open|passthru|system)\s*\(' lanes --glob '*.php'
```

The shell-out scan found only PDO `exec()` calls in
`lanes/syncthing/src/SqliteCheckpointStore.php`; no PHP shell bridge was found
in lane code by that pattern.

## Next Best Step

Freeze active lane writers, watchdog/status publishers, capacity jobs, and
root/focused PHP loops; validate manifests from the frozen tree; accept or
reject dirty lane batches one lane at a time; normalize denominator, mapped,
PHP pass/fail, runner, blocker, and commit fields; regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from the same
accepted snapshot; then rerun the exact duplicate-root gate and capture one
quiesced root `php tools/run-tests.php` run only if the gate remains empty.
