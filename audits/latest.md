# Independent Audit - 2026-05-23T04:24:36Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected
`lanes/*/lane-status.json` files needed to verify status drift, PHP shell-out
usage in `lanes/`, `tools/`, and `scripts/`, and recent Git history observed
through `7b04576d`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
or count bridge/generated/oracle tooling as native implementation progress.

## Findings

1. **High - the required root harness is green now, but the coordination surface is stale and contradictory.**
   - Paths: `porting.html:32`-`36`, `porting.html:54`-`65`,
     `porting-summary.json:2`-`8`, `porting-summary.json:15`-`18`,
     `progress.md:31`-`42`, `progress.md:242`-`248`,
     `lanes/syncthing/lane-status.json:10`-`13`,
     `lanes/pandoc/lane-status.json:10`-`13`.
   - Requirement at risk: `goal.md:3`, `goal.md:44`, `goal.md:45`, and
     `goal.md:49` require current progress coordination, visible dashboard
     status, and honest repo-wide test recording.
   - Evidence: the latest completed required command, rerun during this audit
     after additional commits landed, exits `0` with
     `183 test files, 18012 assertions, 0 failures`. A later LightningCSS
     implementation/status commit landed after that run, so this remains a
     moving-tree sample. Also,
     `porting.html` and `porting-summary.json` still publish the
     `2026-05-23 03:09:50 UTC` snapshot from `3f4ea3cda693`, while current
     observed implementation history has advanced through `7b04576d`.
     `progress.md` still carries stale lane phases.
     Several lane statuses also carry older root outcomes; for example
     Syncthing says root is red with 7 unrelated failures, while this audit's
     exact root run is green.
   - Audit judgment: do not publish the dashboard or treat lane status root
     samples as authoritative until the supervisor regenerates all status files
     from one accepted green snapshot.

2. **High - the repo is a large unaccepted dirty aggregate, so "green" is not yet an integration checkpoint.**
   - Paths: worktree-wide; examples include `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`, `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`, `lanes/syncthing/lane-status.json:13`,
     `porting.html:54`-`65`.
   - Requirement at risk: `goal.md:29`, `goal.md:31`, `goal.md:48`, and
     `goal.md:49` require small reviewable slices, precise blockers, verified
     agent integration, and recorded repo-wide tests.
   - Evidence: after the green root run and later moving-tree commits,
     `git status --short` reports `502` entries, tracked-only status reports
     `64` entries, and
     `git diff --shortstat` reports
     `64 files changed, 11883 insertions(+), 395 deletions(-)`. Multiple
     `latestCommit` fields are prose or dirty-batch labels rather than accepted
     SHAs, including "current lane batch", "uncommitted", "pending", and
     "root harness red" labels. `porting.html` still displays older SHAs and
     counts, so dashboard readers cannot tell which green root result belongs
     to which lane changes.
   - Audit judgment: freeze writers, accept or reject dirty batches lane by
     lane, stamp accepted SHAs, rerun focused tests plus the root harness after
     each accepted batch, then regenerate the dashboard from that same commit.

3. **High - manifest denominator units remain mixed and percentages are not machine-checkable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12`-`21`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`-`19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:12`-`20`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:38` require
     a real upstream denominator, upstream tests as source of truth, and
     explicit slices for huge suites.
   - Evidence: Difftastic's denominator is a prose mix of Rust attributes,
     golden pairs, numbered fixture pairs, parser corpus files, and targeted
     source files. Dolt mixes executable test files, BATS cases, Go functions,
     benchmark functions, fixture paths, and reference matches. Gitoxide uses
     file counts as the numeric denominator while mapped progress is behavior
     slices. markerPDF reports `mapped: 156` against a visible denominator of
     `78 tracked upstream repository paths`, so mapped units exceed the
     displayed denominator. Quadrable's `total` combines paths, upstream
     scenarios, verify checks, and runner status in one string.
   - Audit judgment: split manifests into typed fields for upstream files,
     executable tests, behavior cases, mapped cases, PHP tests/assertions,
     runner parity, evidence class, and accepted snapshot.

4. **Medium - bounded/static upstream evidence is still easy to overread as full parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`-`20`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`-`19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`-`17`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/rclone/lane-status.json:10`-`12`,
     `lanes/dolt/lane-status.json:10`-`12`.
   - Requirement at risk: `goal.md:35`-`40` require meaningful fixture parity,
     upstream tests as source of truth, and hard features to be marked as
     blockers or future slices.
   - Evidence: Difftastic, Gitoxide, markerPDF, Pandoc, and Syncthing still do
     not have full upstream runner parity. Rclone and Dolt have useful bounded
     runner evidence, but still exclude full provider/mount, full BATS, or full
     Go coverage. Those distinctions are buried in long prose and are not
     consistently visible beside progress percentages.
   - Audit judgment: make evidence class (`full-runner`, `bounded-runner`,
     `static-inventory`, `oracle-fixture`) a first-class dashboard/status
     field, and stop letting percentages imply broader parity than the evidence
     supports.

5. **Medium - the generated dashboard/summary schema still flattens required fields.**
   - Paths: `porting.html:41`-`50`, `porting-summary.json:11`-`25`,
     `porting-summary.json:27`-`43`, `porting-summary.json:61`-`77`.
   - Requirement at risk: `goal.md:45` requires separate dashboard columns for
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: HTML still combines benchmark source and denominator into one
     `Benchmark` column and combines PHP pass/fail with mapped coverage in one
     `Mapped` column. `porting-summary.json` uses flattened string fields such
     as `benchmark`, `coverage`, `php`, and `commit`, making it hard for agents
     to compare manifest counts to dashboard counts without parsing display
     text.
   - Audit judgment: update the generator/schema before the next publication,
     then regenerate from accepted metadata rather than the live dirty tree.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane PHP shell-out was found. The only PHP shell-out match is dashboard
coordination tooling.

## Test Run

Required command:

```text
php tools/run-tests.php
```

Exact result for this audit:

```text
exit status: 0
183 test files, 18012 assertions, 0 failures
```

This is a green dirty-worktree sample, not an accepted integration checkpoint,
because the tree has 64 modified tracked entries and hundreds of untracked
coordination/evidence artifacts.

## Recommended Next Intervention

Freeze writers long enough to integrate deliberately. Accept or reject dirty
lane batches one at a time, stamp accepted SHAs, rerun each lane's focused
tests plus `php tools/run-tests.php`, normalize manifest/status schema fields,
and regenerate `progress.md`, `porting.html`, and `porting-summary.json` from
the same accepted green snapshot.
