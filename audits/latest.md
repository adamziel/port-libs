# Independent Audit - 2026-05-23T03:06:24Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected
lane status files needed to check dashboard/status drift, bridge/shell-out
usage in PHP files, and recent Git history.

Recent history observed during this audit included `3f4ea3c`, `f53ab46`,
`dcf3a7d`, `af649df`, `5cc43f3`, `4b40102`, `d31c1aa`, and `eea5621`.
I did not edit lane implementation files, launch agents or tmux sessions, or
push. The only intended writes from this pass are this audit and the
audit-status/next-intervention text in `progress.md`.

## Findings

1. **Critical - the current tree is not a stable accepted integration checkpoint.**
   - Paths: worktree-wide; `progress.md:237`-`245`; `goal.md:29`,
     `goal.md:48`-`49`.
   - Evidence: `HEAD` moved during this audit, from the prior audit commit
     `5cc43f3` through new lane commits to current `3f4ea3cda693`. The current
     dirty state still reports `380` `git status --short` entries, `64` tracked
     modified entries, and `git diff --shortstat` reports
     `64 files changed, 9263 insertions(+), 501 deletions(-)`.
   - The required root run is green, but it is a moving-worktree sample:
     `php tools/run-tests.php` exited `0` with `176` test files,
     `16736` assertions, and `0` failures.
   - Goal requirement at risk: the goal requires small reviewable slices,
     verification before integration, and honest repo-wide failure/status
     recording.
   - Audit judgment: do not treat the dirty aggregate or the green root run as
     an accepted checkpoint until writers are frozen or explicitly coordinated
     and the same snapshot is rerun.

2. **High - `porting.html` and `porting-summary.json` are stale relative to current `HEAD`, manifests, and lane statuses.**
   - Paths: `porting.html:32`-`36`, `porting.html:54`-`65`,
     `porting-summary.json:2`-`8`, `porting-summary.json:16`-`18`,
     `porting-summary.json:83`-`86`, `porting-summary.json:168`-`171`,
     `lanes/libsqlite/lane-status.json:5`-`7`,
     `lanes/rclone/lane-status.json:5`-`7`,
     `lanes/syncthing/lane-status.json:5`-`7`.
   - Evidence: the dashboard still says it was generated from
     `dashboard-publish-attempt 9fe7e4414071`, while current `HEAD` is
     `3f4ea3cda693`. It shows stale counts such as Difftastic `137 / 417`
     mapped while the manifest now says `139 / 417`, libsqlite `122 pass`
     while lane status says `129`, rclone `265 pass` while lane status says
     `270`, markerPDF `247 pass` while lane status says `254`, esbuild `150`
     while lane status says `152`, Readability `100` while lane status says
     `102`, and Syncthing `197` while lane status says `205`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     generated dashboard to show current upstream denominator, mapped tests, PHP
     pass/fail counts, current work, blockers, and commit.
   - Audit judgment: regenerate the dashboard only after accepting or rejecting
     dirty lane batches from one frozen green snapshot.

3. **High - latest-commit fields are not consistently machine-checkable commits, and one lane explicitly records a mixed-lane commit.**
   - Paths: `porting.html:55`, `porting.html:57`-`58`,
     `porting.html:62`, `porting.html:64`-`65`,
     `porting-summary.json:42`, `porting-summary.json:76`,
     `porting-summary.json:93`, `porting-summary.json:161`,
     `porting-summary.json:195`, `porting-summary.json:212`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Evidence: dashboard commit cells render values like `generat`, `current`,
     `Port li`, and `pending`. Lane status files contain prose such as
     `metadata-only column/check-constraint patch boundary lane batch; root
     tests pass`, `uncommitted Gitoxide shell-demotion slice`, and
     `uncommitted Syncthing lane worker slice`. markerPDF records
     `f53ab46 Record difftastic huge cpp status (mixed-lane commit containing
     markerPDF runtime and OCR language token planning)`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:44`, and `goal.md:45`
     require small reviewable slices, current progress coordination, and a
     trustworthy latest commit column.
   - Audit judgment: split accepted commit SHA from dirty batch labels and
     reject mixed-lane commits as a status source unless the supervisor
     intentionally accepts the combined batch.

4. **High - upstream denominator units remain mixed, and markerPDF now maps more cases than its declared denominator.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`-`18`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`-`17`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12`-`16`.
   - Evidence: Difftastic's denominator mixes Rust test attributes, golden
     output pairs, fixture pairs, parser corpus files, and targeted source
     files. markerPDF declares `78 tracked upstream repository paths` as the
     denominator but now reports `mapped: 81`. Pandoc counts files/artifacts
     rather than executable Tasty cases. LightningCSS counts helper invocations
     and Rust/Node tests in one denominator.
   - Goal requirement at risk: `goal.md:25` and `goal.md:35`-`38` require real
     upstream denominators, upstream tests as source of truth, meaningful
     fixture parity, and explicit slices when the upstream suite is huge.
   - Audit judgment: normalize manifest schema into separate fields for
     upstream files, executable upstream tests, behavior cases, mapped behavior
     cases, native PHP tests, native assertions, failures, runner parity class,
     and static/bounded/full evidence.

5. **Medium - lane status root-test statements conflict with the current exact root result.**
   - Paths: `lanes/lightningcss/lane-status.json:10`-`12`,
     `lanes/rclone/lane-status.json:10`-`12`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`,
     `progress.md:239`-`241`.
   - Evidence: LightningCSS and rclone still describe a final red root rerun
     with two Dolt failures; Gitoxide records `16727` root assertions; Dolt and
     libsqlite record `16734`; Syncthing records the current `16736`. This
     audit's exact run is `176` files, `16736` assertions, `0` failures.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, `goal.md:45`, and
     `goal.md:49` require current blocker/audit status, current PHP pass/fail,
     and honest repo-wide test recording.
   - Audit judgment: keep lane-local results separate from the last accepted
     root run, and update all lane statuses from the same frozen root sample.

6. **Medium - `progress.md` still carries stale active-lane estimates and next-task text.**
   - Paths: `progress.md:27`-`42`.
   - Evidence: the Active Lanes table still lists all sessions as `stopped` and
     old estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`,
     libsqlite `12%`, Syncthing `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`,
     while current lane-status files claim materially different estimates and
     work.
   - Goal requirement at risk: `goal.md:44` requires current active lanes,
     blockers, owner/session, next task per lane, and percentage estimates.
   - Audit judgment: leave the table untouched until the supervisor freezes a
     snapshot; update it only from accepted lane statuses, not from the dirty
     aggregate.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane PHP shell-out was found. The only current PHP shell-out match is
dashboard coordination tooling. The previous Gitoxide external-driver shell
concern remains demoted in status: lane code requires injected runner behavior
and should not count a shell-backed external process as native progress.

## Test Run

Required command:

```text
php tools/run-tests.php
```

Exact result from this audit:

```text
exit status: 0
176 test files, 16736 assertions, 0 failures
```

This is not worse than the previous final audit sample, but it is still a
moving-worktree run because `HEAD` and dirty lane state changed during the
audit.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. First reconcile accepted commit
SHAs, dirty batch labels, and root-test fields in every `lane-status.json`.
Then accept or reject dirty lane batches one lane at a time, rerun focused lane
tests plus `php tools/run-tests.php` after each accepted batch, commit accepted
slices, and regenerate `progress.md`, `porting.html`, `porting-summary.json`,
and lane statuses from one stable green snapshot with normalized denominator,
mapped-case, native-test, runner-parity, blocker, and latest-commit fields.
