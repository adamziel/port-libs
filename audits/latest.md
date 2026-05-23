# Independent Audit - 2026-05-23T14:43:11Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
all 12 `lanes/*/lane-status.json` files, recent Git history, dirty tree
state, active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `b976ea80556d` (`Refresh independent audit
status`). Recent history reviewed shows the latest five commits are all
audit-only `Refresh independent audit status` commits touching only
`audits/latest.md` and `progress.md`.

Manifest snapshot reviewed:

| Lane | Manifest denominator | Manifest mapped | Lane PHP pass/fail | Lane estimate |
| --- | ---: | ---: | ---: | ---: |
| difftastic | prose/string `592 inspected...` | 285 | 285 / 0 | 93% |
| dolt | prose/string `613 upstream...` | 538 | 303 / 0 | 94% |
| esbuild | prose/string `2,567 counted...` | 240 | 240 / 0 | 74% |
| gitoxide | 2877 | 1992 | 3755 / 0 | 98% |
| libsqlite | 1589 | 234 | 234 / 0 | 98% |
| lightningcss | 3532 | 1282 | 1450 / 0 | 85% |
| markerPDF | 282 | 230 | 345 / 0 | 93% |
| pandoc | prose/string `2276 upstream...` | 705 | 223 / 0 | 96% |
| quadrable | prose/string `55 tracked...` | 55 | 148 / 0 | 99% |
| rclone | 1601 | 497 | 497 / 0 | 98% |
| readability | 1984 | 1744 | 157 / 0 | 95% |
| syncthing | 658 | 370 | 370 / 0 | 98% |

## Findings

1. **Critical - another aggregate test baseline would be invalid from this
   moving tree.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json:13`, active process state.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped active
     work, current owner/session tracking, small committed slices, supervisor
     verification, and honest repo-wide test evidence.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, while `progress.md:31`-`42` says
     every lane session is `stopped`.
   - Evidence: active process sampling found live lane/watchdog/status loops
     for markerPDF, Quadrable, Pandoc, Syncthing, Dolt, Readability, esbuild,
     rclone, Dolt runner, libsqlite, auditor, team watchdog, evaluator,
     capacity controller, and dashboard updater.
   - Evidence: dirty-tree sampling moved during the audit. Latest samples
     reported `1844` default `git status --short` rows, `189` tracked changed
     files, and `189 files changed, 68604 insertions(+), 5462 deletions(-)`.
   - Audit judgment: freeze writers and status publishers before accepting or
     rejecting a repo-wide baseline. Current lane root-pass claims are anecdotes
     from different repository states.

2. **Critical - the required duplicate-root gate found active root harnesses,
   so this audit correctly did not start `php tools/run-tests.php`.**
   - Paths: process state; `lanes/rclone/lane-status.json:10`-`12`,
     `lanes/difftastic/lane-status.json:10`-`12`,
     `lanes/syncthing/lane-status.json:10`-`12`.
   - Goal requirement at risk: `goal.md:49` requires repo-wide tests to be run
     periodically and recorded honestly; the user instruction for this run
     forbids starting a duplicate root harness after a matching gate result.
   - Required gate:
     ```text
     pgrep -af '^php tools/run-tests\.php( |$)'
     ```
   - Gate result before this audit update:
     ```text
     1519896 php tools/run-tests.php
     1523175 php tools/run-tests.php
     ```
   - Owner evidence:
     ```text
     1519896 claude 1511481 25 Rs php tools/run-tests.php
     1523175 claude 1523165 16 R  php tools/run-tests.php
     ```
   - Earlier in this audit, the same gate briefly matched focused lane
     harnesses (`php tools/run-tests.php lanes/syncthing/tests` and
     `php tools/run-tests.php lanes/quadrable/tests`) before those exited.
   - Evidence: a later post-edit duplicate-root sample returned no rows, but
     the tree was still non-quiescent and continued to move.
   - Audit judgment: no additional root harness should run until this gate is
     empty and the tree is quiescent.

3. **High - `porting.html` and `porting-summary.json` are still stale and do
   not satisfy the dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with benchmark source, upstream denominator, mapped tests, PHP
     pass/fail, WordPress scenarios, phase, audit, current work, blocker, and
     commit.
   - Evidence: `porting.html:32`-`36` still publishes generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`; sampled
     `HEAD` is `b976ea80556d`.
   - Evidence: `porting-summary.json` likewise reports generated
     `2026-05-23 04:57:16 UTC`, dashboard commit `8ba77df82902`, source
     commit `bda83c6b93d4`, and average progress `68.8`.
   - Evidence: current files disagree materially with the dashboard. Rclone is
     published as denominator `327`, mapped `291`, and `291 pass`
     (`porting.html:63`), while current lane files report denominator `1601`,
     mapped `497`, and `497` PHP pass (`lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/rclone/lane-status.json:4`-`7`). markerPDF is published as
     denominator `78`, mapped `159`, and `264 pass` (`porting.html:60`), while
     current files report denominator `282`, mapped `230`, and `345` PHP pass
     (`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/markerpdf/lane-status.json:4`-`7`). Gitoxide is published as
     mapped `1432` and `2646 pass` (`porting.html:57`), while current files
     report mapped `1992` and `3755` PHP pass
     (`lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/gitoxide/lane-status.json:4`-`7`).
   - Evidence: `porting.html:41`-`50` still combines benchmark source,
     upstream denominator, mapped tests, and PHP pass/fail into compact
     `Benchmark`/`Mapped` cells instead of first-class columns required by
     `goal.md:45`.

4. **High - lane status claims 93%-99% completion while most latest work is
   uncommitted dirty-batch handoff prose.**
   - Paths: `progress.md:31`-`42`, `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:13`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:44`, and `goal.md:48`
     require small committed slices, current progress tracking, verification,
     cleanup of accidental unrelated changes, and reassignment after agent
     completion.
   - Evidence: `progress.md:31`-`42` still shows stale estimates from `5%` to
     `66%`, while current lane statuses claim Difftastic `93%`, Dolt `94%`,
     Gitoxide/libsqlite/rclone/Syncthing `98%`, Quadrable `99%`, Pandoc `96%`,
     Readability `95%`, markerPDF `93%`, LightningCSS `85%`, and esbuild
     `74%`.
   - Evidence: `latestCommit` fields are not clean accepted commits for the
     current slices. Examples include `pending in shared dirty worktree`,
     `not committed`, `uncommitted port-esbuild lane batch`, `pending -
     current Gitoxide filter-process protocol batch`, `uncommitted
     lane-scoped changes`, `pending lane-local changes`, and `pending`.
   - Evidence: recent Git history confirms integration is not happening: the
     latest five commits are audit-only updates to `audits/latest.md` and
     `progress.md`, while lane statuses report major active implementation
     changes.

5. **High - manifest denominator and evidence units remain non-normalized, so
   portfolio percentages are not comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json:5`-`7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require a real upstream
     denominator, mapped upstream tests, PHP pass/fail counts, and comparable
     dashboard fields.
   - Evidence: several `benchmarkDenominator.total` values are prose strings
     instead of numeric counts: Difftastic, Dolt, esbuild, Pandoc, and
     Quadrable. Other lanes use numeric totals, so the generated average is
     mixing unlike units.
   - Evidence: mapped units and PHP pass units are different measures but are
     displayed as if comparable. Current examples: Gitoxide has `1992` mapped
     units and `3755` PHP pass; markerPDF has `230` mapped units and `345` PHP
     pass; Pandoc has `693` mapped units and `223` PHP pass; Readability has
     far more mapped upstream units than PHP behavior tests.
   - Evidence: some lanes count file/path inventories, some count upstream
     test functions, some count behavior assertions, some count supplied
     document excerpts, and some include bounded runner evidence in the same
     field. That directly weakens the `goal.md:45` suite-progress and
     denominator columns.

6. **High - hard upstream-runner and hard-feature gaps are being softened by
   near-complete status percentages.**
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
   - Evidence: Gitoxide is `98%` while full Cargo workspace parity, shell
     backed filter launch/lifecycle, broader refspec baseline parity, sparse
     index writing, SHA-256 existing-pack reuse, and full merge semantics
     remain open. markerPDF is `93%` while Python benchmarks, Streamlit,
     multiprocessing, GitHub Actions, Torch/Surya/Texify/tabled/OCR/Pandoc
     execution, and live model downloads remain unexecuted. Pandoc is `96%`
     while the Haskell Tasty runner is unexecuted. rclone and Syncthing are
     `98%` while full provider/mount/live-service and broad `go test ./...`
     coverage remains open. libsqlite is `98%` while broad write, WAL,
     rollback-journal, SQL execution, trigger/upsert, expression-index, and
     corruption cases remain open. Quadrable is `99%` while full 500-trial
     sync-fuzzer and 100,000-record sync benchmark remain opt-in.
   - Audit judgment: these may be good focused slices, but the displayed
     percentages should not imply near-complete upstream parity.

7. **Medium - current root-test records conflict across lanes.**
   - Paths: `lanes/rclone/lane-status.json:10`-`12`,
     `lanes/difftastic/lane-status.json:10`-`12`,
     `lanes/syncthing/lane-status.json:10`-`12`,
     `lanes/markerpdf/lane-status.json:10`-`12`,
     `lanes/pandoc/lane-status.json:10`-`12`.
   - Goal requirement at risk: `goal.md:49` requires failures to be recorded
     honestly.
   - Evidence: Difftastic records a green no-argument root run with `233` test
     files and `28466` assertions; Syncthing records another green root run
     with `233` files and `28387` assertions after waiting for a lock; rclone
     records an aggregate root run that is red because of unrelated Difftastic
     failures; markerPDF and Pandoc record root pending due active broad
     runners. These results cannot all describe the same accepted tree state.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed gate result before this audit update:

```text
1519896 php tools/run-tests.php
1523175 php tools/run-tests.php
```

Owner evidence:

```text
1519896 claude 1511481 25 Rs php tools/run-tests.php
1523175 claude 1523165 16 R  php tools/run-tests.php
```

No root run was started by this audit. The duplicate-root gate was active at
the required pre-run sample, a later gate cleared, and the stability gate still
failed.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
jq -r '[.library, (.estimatedProgress|tostring), (.phpPass|tostring), (.phpFail|tostring), .latestCommit, .blocker]|@tsv' lanes/*/lane-status.json
jq -r '[.generated, .dashboardCommitShort, .sourceCommitShort, (.averageProgressPercent|tostring), (.lanes|length|tostring)] | @tsv' porting-summary.json
git log --oneline --name-only -n 5
git show --stat --oneline --decorate -n 10
git status --short | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -o pid,user,ppid,etimes,stat,command -p 1519896,1523175
```

## Next Best Step

Freeze all lane writers, watchdog/status publishers, and root/focused PHP
harnesses; then accept or reject dirty lane batches one lane at a time with
focused verification. Only after that, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, lane statuses, and manifests from the
same accepted snapshot, rerun the duplicate-root gate, and capture one
quiesced root `php tools/run-tests.php` run if the gate remains empty.
