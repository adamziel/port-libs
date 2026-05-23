# Independent Audit - 2026-05-23T03:35:41Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected
lane status files needed to verify dashboard/status drift, PHP shell-out usage
in `lanes/`, `tools/`, and `scripts/`, and recent Git history.

Recent history observed at the end of the audit: `dd55d42`, `e0614e5`,
`100bb7c`, `977ac74`, `3ec36a4`, `f6489ca`, `9fcad97`, `d2a0f17`,
`ce35417`, `675de77`, `412b96d`, and `f37fe5f`. `HEAD` moved while this audit
was reading and testing the tree, so the test result below is a
moving-worktree sample, not an accepted integration checkpoint. I did not edit
lane implementation files, launch agents or tmux sessions, or push.

## Findings

1. **Critical - the current green root sample is still an unaccepted dirty aggregate.**
   - Paths: worktree-wide; `progress.md:237`-`245`; `goal.md:29`,
     `goal.md:44`, `goal.md:48`-`49`.
   - Evidence: the required root test now passes, but the tree was moving
     during the audit: observed `HEAD` advanced through `675de77` to
     `dd55d42`, `git status --short` reported `398` entries, tracked-only
     status reported `42` modified entries, and `git diff --shortstat`
     reported `42 files changed, 9309 insertions(+), 397 deletions(-)`.
   - The exact harness result was `exit 0`, `179` test files, `17246`
     assertions, and `0` failures.
   - Audit judgment: this is better than the starting red audit record, but it
     is not a stable integration checkpoint until active writers are frozen or
     explicitly coordinated and the accepted snapshot is rerun green.

2. **High - `porting.html` and `porting-summary.json` are stale relative to current `HEAD` and lane files.**
   - Paths: `porting.html:32`-`36`, `porting.html:54`-`65`,
     `porting-summary.json:2`-`8`, `porting-summary.json:16`-`18`,
     `porting-summary.json:33`-`35`, `porting-summary.json:67`-`69`,
     `porting-summary.json:84`-`86`, `porting-summary.json:101`-`103`,
     `porting-summary.json:118`-`120`, `porting-summary.json:169`-`171`,
     `porting-summary.json:186`-`188`, `porting-summary.json:203`-`205`.
   - Evidence: the dashboard still claims a generated snapshot from
     `2026-05-23 03:09:50 UTC` at source commit `3f4ea3cda693`, while current
     `HEAD` is `dd55d42`. Mismatch examples: Difftastic dashboard says
     `139 / 417` and `139 pass` while manifest/status say `147`; Dolt says
     `197` mapped and `171 pass` while current files say `200` mapped and
     `176` pass; Gitoxide says `1358` mapped and `2511 pass` while current
     files say `1362` mapped and `2537` assertions; libsqlite says `129` while
     current files say `134`; LightningCSS says `703` mapped and `827 pass`
     while current files say `728` and `858`; markerPDF still renders the
     impossible `81 / 78` and stale `254 pass` while current files say `83`
     mapped and `257` pass; rclone says `265` while current
     files say `276`; Readability says `907` mapped and `100 pass` while
     current files say `984` mapped and `104` pass; Syncthing says `204` while
     current files say `213`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     generated dashboard with denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: do not publish or rely on the dashboard until it is
     regenerated from one frozen green snapshot.

3. **High - lane status files contain contradictory root-test and blocker claims.**
   - Paths: `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/difftastic/lane-status.json:10`-`12`,
     `lanes/esbuild/lane-status.json:10`-`12`,
     `lanes/markerpdf/lane-status.json:10`-`12`,
     `lanes/pandoc/lane-status.json:10`-`12`,
     `lanes/quadrable/lane-status.json:10`-`12`,
     `lanes/readability/lane-status.json:10`-`12`,
     `lanes/syncthing/lane-status.json:10`-`12`, `progress.md:240`.
   - Evidence: current exact root result is green with `179` files, `17246`
     assertions, and `0` failures. Status files still cite older root states:
     Dolt reports a markerPDF root failure, Quadrable reports two unrelated
     failures, Pandoc reports one markerPDF failure, Difftastic says root was
     not started, Esbuild says root verification is pending, and several green
     claims use stale assertion counts such as `16997`, `17042`, `17109`, or
     `17211`.
   - Goal requirement at risk: `goal.md:44`, `goal.md:45`, and `goal.md:49`
     require precise blockers, audit status, and repo-wide test recording.
   - Audit judgment: split lane-local pass/fail, root-suite pass/fail, and
     accepted snapshot SHA into separate fields. Root status should be stamped
     once per frozen snapshot, not opportunistically per lane.

4. **High - upstream denominator units remain mixed, and markerPDF still reports more mapped items than its denominator.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`21`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`20`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
   - Evidence: markerPDF declares `78 tracked upstream repository paths` while
     reporting `mapped: 83`. Difftastic's `417` mixes Rust attributes, golden
     pairs, numbered fixture pairs, parser corpus files, CLI fixtures, and
     source files. Dolt's denominator is executable test files while the same
     text also records BATS cases, Go test functions, benchmarks, and fixture
     artifacts. Gitoxide uses `2877` upstream files while mapped progress is
     behavior slices and native assertions. Quadrable's `total` is a prose
     string combining tracked paths, scenarios, checks, and runner evidence.
     Readability maps `984` local assertions against a `1984` Mocha-test
     upstream denominator while status reports only `104` PHP behavior tests.
   - Goal requirement at risk: `goal.md:25` and `goal.md:35`-`38` require a
     real upstream denominator, upstream tests as source of truth, and explicit
     slices when suites are huge.
   - Audit judgment: normalize manifests into separate upstream-file,
     executable-test, behavior-case, mapped-behavior, PHP-test, assertion,
     failure, runner-parity, and static/bounded/full evidence fields before
     percentages are treated as portfolio progress.

5. **High - latest-commit/provenance fields are not reliably auditable commit identifiers.**
   - Paths: `porting-summary.json:42`, `porting-summary.json:76`,
     `porting-summary.json:127`, `porting-summary.json:161`,
     `porting-summary.json:195`, `porting-summary.json:212`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Evidence: dashboard/status commit fields contain truncated or prose values
     such as `metadat`, `pending`, `pending-current-batch`, `not committed`,
     `pending verified rclone...`, and a markerPDF mixed-lane note pointing at
     a Difftastic commit. These are not stable SHAs for review.
   - Goal requirement at risk: `goal.md:29`, `goal.md:44`, and `goal.md:45`
     require small reviewable slices and a trustworthy latest-commit column.
   - Audit judgment: split accepted SHA, dirty batch label, root-test sample,
     and mixed-lane provenance. The dashboard commit column should contain real
     accepted SHAs or an explicit unaccepted/dirty marker.

6. **Medium - bounded/static evidence is still presented too close to upstream parity in several lanes.**
   - Paths: `lanes/difftastic/lane-status.json:5`,
     `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`-`20`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:271`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:697`.
   - Evidence: Difftastic has no upstream runner parity. Gitoxide still has no
     full workspace Cargo pass. markerPDF's full benchmark runner is still not
     executed. Pandoc lacks the full Haskell runner. rclone explicitly excludes
     live providers/mounts. Syncthing remains static plus focused bounded
     runners, not full `go test ./...` parity.
   - Goal requirement at risk: `goal.md:35`-`40` says upstream tests are the
     source of truth, static inventories must be marked clearly, and hard
     features cannot be silently skipped.
   - Audit judgment: keep these as bounded/static slices until the upstream
     runners are executed, or deliberately narrow the scope and label it as
     bounded evidence in both manifests and dashboard.

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
dashboard coordination tooling.

## Test Run

Required command:

```text
php tools/run-tests.php
```

Exact result from this audit:

```text
exit status: 0
179 test files, 17246 assertions, 0 failures
```

This is better than the starting audit record in `progress.md`, which reported
exit `1`, `178` test files, `17135` assertions, and `2` failures. It is still
a moving-worktree sample, not an accepted integration checkpoint.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. Accept or reject the dirty lane
batches one lane at a time, rerun focused lane tests and `php tools/run-tests.php`
after each accepted batch, commit accepted slices, then regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same stable green snapshot with normalized denominator, mapped-case,
native-test, runner-parity, blocker, and latest-commit fields.
