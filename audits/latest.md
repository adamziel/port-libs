# Independent Audit - 2026-05-23T14:50:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
all 12 `lanes/*/lane-status.json` files, recent Git history, dirty tree
state, active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `96a846153cda` (`Refresh independent audit
status`). Recent history reviewed shows the latest ten commits are all
audit-only `Refresh independent audit status` commits touching
`audits/latest.md` and `progress.md`, while lane files remain broadly dirty.

Manifest/status snapshot reviewed:

| Lane | Manifest denominator | Manifest mapped | Lane PHP pass/fail | Lane estimate |
| --- | ---: | ---: | ---: | ---: |
| difftastic | prose/string `595 inspected...` | 288 | 285 / 0 | 93% |
| dolt | prose/string `613 upstream...` | 546 | 304 / 0 | 94% |
| esbuild | prose/string `2,567 counted...` | 240 | 240 / 0 | 74% |
| gitoxide | 2877 | 1994 | 3772 / 0 | 98% |
| libsqlite | 1589 | 235 | 235 / 0 | 98% |
| lightningcss | 3532 | 1282 | 1450 / 0 | 85% |
| markerPDF | 282 | 230 | 345 / 0 | 93% |
| pandoc | prose/string `2276 upstream...` | 705 | 225 / 0 | 96% |
| quadrable | prose/string `55 tracked...` | 55 | 148 / 0 | 99% |
| rclone | 1601 | 502 | 502 / 0 | 98% |
| readability | 1984 | 1744 | 158 / 0 | 96% |
| syncthing | 658 | 370 | 370 / 0 | 98% |

## Findings

1. **Critical - the current tree is not stable enough for an accepted root
   baseline, even though the exact duplicate-root gate was clear.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `.tmux-team/prompts/*`, `lanes/*/lane-status.json`, active process state.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped active work,
     current owner/session tracking, small committed slices, supervisor
     verification, and honest repo-wide test evidence.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` still
     reports every lane session as `stopped`.
   - Evidence: active process sampling found live `run-tmux-agent.sh` sessions
     for Quadrable, Pandoc, Dolt, Readability, rclone, Dolt runner, libsqlite,
     Gitoxide, LightningCSS, Difftastic, esbuild, Syncthing, integrator,
     auditor, and markerPDF, plus `scripts/run-team-watchdog.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     and `scripts/run-dashboard-updater-loop.sh`.
   - Evidence: broad Dolt BATS remained active during the audit
     (`1512764`, `1512793`, `1512800`, `1512801`, later per-file/test
     descendants), so an aggregate PHP run would compete with non-lane
     broad runners and describe a moving tree.
   - Evidence: dirty-tree sampling reported `1846` default
     `git status --short` rows, `187` tracked changed files, and later
     `187 files changed, 70881 insertions(+), 6721 deletions(-)`.
   - Evidence: manifest values changed while auditing. Difftastic moved from
     `592/285` to `595/288` manifest denominator/mapped counts during this
     run, proving the manifest surface was still being written.
   - Audit judgment: do not accept any root-pass anecdote until writers and
     status publishers are frozen and the test is run from one snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale and do
   not satisfy the dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with benchmark source, upstream denominator, mapped tests, PHP
     pass/fail, WordPress scenarios, phase, audit, current work, blocker, and
     commit.
   - Evidence: `porting.html:32`-`36` still publishes generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`; sampled
     `HEAD` is `96a846153cda`.
   - Evidence: `porting-summary.json` likewise reports generated
     `2026-05-23 04:57:16 UTC`, dashboard commit `8ba77df82902`, source
     commit `bda83c6b93d4`, and average progress `68.8`.
   - Evidence: the published table disagrees with current lane files. Rclone
     is published as denominator `327`, mapped `291`, and `291 pass`
     (`porting.html:63`), while current files report denominator `1601`,
     mapped `502`, and `502` PHP pass
     (`lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/rclone/lane-status.json:4`-`7`). markerPDF is published as
     denominator `78`, mapped `159`, and `264 pass` (`porting.html:60`), while
     current files report denominator `282`, mapped `230`, and `345` PHP pass
     (`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/markerpdf/lane-status.json:4`-`7`). Difftastic is published as
     denominator `417`, mapped `160`, and `160 pass` (`porting.html:54`),
     while current files report denominator prose `595 inspected...`, mapped
     `288`, and `285` PHP pass
     (`lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/difftastic/lane-status.json:4`-`7`).
   - Evidence: `porting.html:41`-`50` still combines benchmark source,
     upstream denominator, mapped tests, and PHP pass/fail into compact
     `Benchmark`/`Mapped` cells instead of first-class columns required by
     `goal.md:45`.

3. **High - lane status and progress reporting are misaligned with commit
   reality.**
   - Paths: `progress.md:31`-`42`, `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:13`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:44`, and `goal.md:48`
     require small committed slices with passing tests, current progress
     tracking, cleanup of accidental unrelated changes, and reassignment after
     verification.
   - Evidence: `progress.md:31`-`42` still shows stale estimates from `5%` to
     `66%`, while current lane statuses claim Difftastic `93%`, Dolt `94%`,
     Gitoxide/libsqlite/rclone/Syncthing `98%`, Quadrable `99%`, Pandoc and
     Readability `96%`, markerPDF `93%`, LightningCSS `85%`, and esbuild
     `74%`.
   - Evidence: `latestCommit` fields are not accepted commits for the current
     work. Examples include `pending in shared dirty worktree`, `not
     committed`, `uncommitted port-esbuild lane batch`, `pending - current
     Gitoxide filter-process lifecycle batch`, `uncommitted lane-scoped
     changes`, `pending lane-local changes`, and plain `pending`.
   - Evidence: recent Git history does not show integration commits for these
     claimed slices; the latest ten commits are audit-only updates to
     `audits/latest.md` and `progress.md`.

4. **High - manifest denominator and evidence units are still
   non-normalized, so portfolio percentages are not comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/*/lane-status.json:4`-`7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require a real upstream
     denominator, mapped upstream tests, PHP pass/fail counts, and comparable
     dashboard fields.
   - Evidence: several `benchmarkDenominator.total` values are prose strings
     instead of numeric counts: Difftastic, Dolt, esbuild, Pandoc, and
     Quadrable. Other lanes use numeric totals, so the generated average mixes
     unlike units.
   - Evidence: mapped units and PHP pass units are different measures but are
     displayed as if comparable. Current examples: Gitoxide has `1994` mapped
     units and `3772` PHP assertions/tests reported as pass; markerPDF has
     `230` mapped units and `345` PHP behavior tests; Pandoc has `705` mapped
     units and `225` PHP pass; Readability has `1744` mapped upstream checks
     but only `158` PHP behavior tests.
   - Evidence: some lanes count file/path inventories, some count upstream
     test functions, some count behavior assertions, some count supplied
     document excerpts, and some include bounded runner evidence in the same
     field.

5. **High - near-complete percentages soften hard upstream-runner and
   hard-feature gaps.**
   - Paths: `lanes/gitoxide/lane-status.json:4` and `:12`,
     `lanes/markerpdf/lane-status.json:4` and `:12`,
     `lanes/pandoc/lane-status.json:4` and `:12`,
     `lanes/rclone/lane-status.json:4` and `:12`,
     `lanes/syncthing/lane-status.json:4` and `:12`,
     `lanes/libsqlite/lane-status.json:4` and `:12`,
     `lanes/quadrable/lane-status.json:4` and `:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require precise blockers, upstream tests
     as source of truth, and hard gaps marked as blockers or future slices.
   - Evidence: Gitoxide is `98%` while full Cargo workspace parity,
     shell-backed filter process launch/wait/kill integration, broader
     refspec fixture parity, sparse-index writing, SHA-256 existing-pack
     reuse, and full merge semantics remain open.
   - Evidence: markerPDF is `93%` while `benchmarks/overall.py`,
     `marker_app.py` live Streamlit execution, top-level `convert.py`
     multiprocessing, GitHub Actions, and heavy Python/model dependencies
     remain unexecuted. Pandoc is `96%` while the Haskell Tasty runner remains
     unexecuted. rclone and Syncthing are `98%` while live provider/mount or
     broad `go test ./...` coverage remains open. libsqlite is `98%` while
     broad write, WAL, rollback-journal, SQL execution, triggers/upserts,
     expression-index, and corruption cases remain open. Quadrable is `99%`
     while full 500-trial sync-fuzzer and 100,000-record sync benchmark runs
     remain opt-in.

6. **Medium - root-test records conflict across lanes and cannot all describe
   one accepted tree.**
   - Paths: `lanes/difftastic/lane-status.json:10`-`12`,
     `lanes/dolt/lane-status.json:10`-`12`,
     `lanes/lightningcss/lane-status.json:10`-`12`,
     `lanes/rclone/lane-status.json:10`-`12`,
     `lanes/readability/lane-status.json:10`-`12`,
     `lanes/syncthing/lane-status.json:10`-`12`.
   - Goal requirement at risk: `goal.md:49` requires repo-wide tests and
     failures to be recorded honestly.
   - Evidence: Difftastic records a green no-argument root run with `233` test
     files and `28466` assertions. Dolt records that an exact root harness was
     already active at PID `1548347`, so it skipped root. LightningCSS records
     post-edit root pending because another exact root PID `1507426` was
     active. rclone, Readability, and Syncthing all leave aggregate root
     verification pending for supervisor/integrator ownership.
   - Audit judgment: those records may be individually true, but they are not
     a single integration checkpoint.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed gate result during this audit:

```text
<no rows>
```

No root run was started because the tree was not stable enough: active lane
agents/status publishers and broad Dolt BATS processes were present, the dirty
tree remained very broad, and manifest/status values changed during sampling.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
jq -r 'input_filename as $f | [.lane, (.benchmarkDenominator.total|tostring), (.benchmarkDenominator.mapped|tostring)] | @tsv' lanes/*/UPSTREAM_TEST_MANIFEST.json
jq -r 'input_filename as $f | [$f, (.estimatedProgress|tostring), (.phpPass|tostring), (.phpFail|tostring), (.latestCommit|tostring), (.blocker|tostring)] | @tsv' lanes/*/lane-status.json
jq -r '[.generated, .dashboardCommitShort, .sourceCommitShort, (.averageProgressPercent|tostring), (.lanes|length|tostring)] | @tsv' porting-summary.json
git log --oneline --name-only -n 8 -- audits/latest.md progress.md porting.html porting-summary.json
git show --stat --oneline --decorate -n 10
git status --short
git status --short --untracked-files=no
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af '(run-tmux-agent|run-team-watchdog|run-capacity-controller|run-dashboard-updater|run-evaluator|run-integrator|run-dolt|run-php-|run-sqlite|cargo test|go test|bats|testrunner\.tcl|npm test)'
```

## Next Best Step

Freeze active lane writers, watchdog/status publishers, broad upstream runners,
and root/focused PHP loops; validate manifests from the frozen tree; accept or
reject dirty lane batches one lane at a time; normalize denominator, mapped,
PHP pass/fail, runner, blocker, and commit fields; regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot; then rerun the exact duplicate-root gate and capture one
quiesced root `php tools/run-tests.php` run only if the gate stays empty.
