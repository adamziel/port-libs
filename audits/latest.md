# Independent Audit - 2026-05-23T03:35:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected
lane status files needed to check status drift, bridge/shell-out usage in PHP
files, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. The only intended writes from this pass are this audit and the
audit-status/next-intervention text in `progress.md`.

## Findings

1. **Critical - the public dashboard is stale and no longer represents the current portfolio.**
   - Paths: `porting.html:30`-`64`, `porting-summary.json:1`-`12`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/dolt/lane-status.json:4`-`13`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12`-`16`.
   - Evidence: `porting.html` and `porting-summary.json` still show
     `Generated: 2026-05-22 15:40:20 UTC`, `Average progress: 14.3%`, and old
     row values such as Difftastic `15 / 404`, Dolt `5 / 613`, Gitoxide
     `737 / 2877`, libsqlite `18 / 1454`, Readability `89 / 1984`, and
     Syncthing `27 / 264`. Current manifests/status files have moved materially:
     Difftastic maps 139, Dolt status claims 168 PHP behavior tests, Gitoxide
     maps 1358, libsqlite status claims 129, Readability maps 943, and Syncthing
     maps 204.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard tracking by upstream denominator, mapped tests, PHP pass/fail,
     phase, audit status, current work, blocker, and latest commit.
   - Audit judgment: do not publish or rely on `porting.html` until it is
     regenerated from the same accepted snapshot as the manifests, lane statuses,
     and `progress.md`.

2. **High - the repo is still a moving dirty aggregate, not an accepted integration checkpoint.**
   - Paths: worktree-wide; `progress.md:236`-`244`,
     `lanes/dolt/tests/PatchFunctionCallTest.php:383`-`392`,
     `lanes/dolt/tests/PatchFunctionCallTest.php:567`-`575`,
     `lanes/dolt/fixtures/wp-patch-generated-default-review.php:60`-`64`.
   - Evidence: during this audit `HEAD` advanced to `4b40102`, while the dirty
     worktree grew to `373` `git status --short` entries, `63` tracked modified
     entries, and `63 files changed, 9620 insertions(+), 415 deletions(-)`. The
     first required root run failed while the Dolt generated/default fixture was
     moving; the rerun passed after the fixture/implementation state changed.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verification before integration, and
     honest repo-wide failure/status recording.
   - Audit judgment: the final green root sample is useful, but it is still a
     moving-worktree sample. Freeze or explicitly coordinate writers before
     accepting, committing, or publishing any lane batch.

3. **High - lane status files still contradict the latest root test state and accepted-commit model.**
   - Paths: `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`13`,
     `lanes/markerpdf/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`.
   - Evidence: the final root run in this audit passes with `176` files and
     `16727` assertions, but libsqlite, LightningCSS, markerPDF, and rclone still
     say the current root harness is red in unrelated lanes. Gitoxide still
     records an older root count of `175` files and `16568` assertions. Multiple
     latest-commit fields remain prose/pending labels instead of accepted SHAs.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and `goal.md:45`
     require trustworthy blocker, audit, owner/session, next task, percentage,
     PHP pass/fail, and latest-commit fields.
   - Audit judgment: normalize lane status into separate machine fields for
     accepted commit SHA, dirty batch label, lane-local test result, root-test
     result, blocker, and prose notes before regenerating the dashboard.

4. **High - upstream denominator units remain mixed across manifests, so percentages are not comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`-`18`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`-`17`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12`-`16`.
   - Evidence: Difftastic counts inspected behavior artifacts, Rust test
     attributes, golden pairs, fixture pairs, and corpus files; markerPDF counts
     repository paths, inspected source semantics, benchmark pairs, and surrogate
     pairs; Pandoc counts files/artifacts rather than executable Tasty cases;
     LightningCSS counts helper invocations as behavior checks. These are useful
     inventories, but a dashboard percentage derived from them is not an
     apples-to-apples upstream pass-parity metric.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`-`38`, and
     `goal.md:45` require real upstream denominators, source-of-truth upstream
     tests, meaningful fixture parity, and interpretable mapped fields.
   - Audit judgment: split denominator schema into upstream files, executable
     upstream tests, behavior cases, mapped behavior cases, native PHP tests,
     native assertions, native failures, runner parity class, and
     static/bounded/full evidence.

5. **Medium - `progress.md` still carries stale active-lane estimates and task text.**
   - Paths: `progress.md:27`-`42`.
   - Evidence: the Active Lanes table still lists stopped sessions and old
     estimates such as Gitoxide `66%`, LightningCSS `14%`, libsqlite `12%`,
     Quadrable `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`, while current
     lane-status files claim much higher estimates and different current work.
   - Goal requirement at risk: `goal.md:44` requires current active lanes,
     blockers, owner/session, next task per lane, and percentage estimates.
   - Audit judgment: update this table only from a coordinated accepted
     snapshot; this audit updates the current status/next intervention but leaves
     the table as a known stale coordination surface.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

The only current PHP shell-out match is dashboard coordination tooling. The
previous Gitoxide `proc_open()` concern is resolved in the current tree:
`ExternalMergeDriverCommand::run()` now requires an injected runner and does not
provide a default shell-backed implementation.

## Test Runs

Required command: `php tools/run-tests.php`

First run during moving Dolt state:

```text
exit status: 1
176 test files, 16716 assertions, 2 failures
failures: lanes/dolt/tests/PatchFunctionCallTest.php generated/default column patch expectations
```

Final rerun after the worktree moved:

```text
exit status: 0
176 test files, 16727 assertions, 0 failures
```

The final result is green, but because `HEAD` and the dirty worktree moved during
the audit, it is a green moving-worktree sample, not a stable integration
checkpoint.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers and preserve ownership of dirty
lane batches. Reconcile stale lane status root-test/blocker/latest-commit fields
first, then accept or reject dirty lane batches one lane at a time. After each
accepted batch, rerun focused lane tests plus `php tools/run-tests.php`, commit
accepted slices, and regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from one stable green snapshot with
normalized denominator, mapped-case, native-test, runner-parity, blocker, and
latest-commit fields.
