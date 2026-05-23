# Independent Audit - 2026-05-23T11:56:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
lane status files needed for alignment checks, recent Git history, dirty-tree
state, active process state, and the required root-test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Initial sampled `HEAD` was `e6182a8c1752`; `HEAD` moved during the audit to
`f40a591b1c99` (`Port rclone OneDrive permission write planner`). Recent
history reviewed includes `f40a591b`, `e6182a8c`, `9f4f13f6`, `e77d40c2`,
`13c0daf8`, `55605cb0`, `07221e76`, `6c13ace2`, `2960351d`, `275ec497`,
`3032d35b`, and `8b0f5af1`.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`, `progress.md:305`,
     `porting.html:32`-`36`, `porting-summary.json:2`-`8`, all
     `lanes/*/lane-status.json`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:20` requires a supervised workflow with
     a practical concurrency cap; `goal.md:29` requires small reviewable slices
     with passing tests; `goal.md:44` requires current owner/session status;
     `goal.md:49` requires honest repo-wide test recording; `goal.md:52`
     requires visible current progress.
   - Evidence: `progress.md:25` still says the launch target is 2
     implementation lanes plus 1 auditor, and `progress.md:31`-`42` marks all
     12 lanes `stopped`. A process sample found active watchdog, capacity,
     dashboard, evaluator, integrator, auditor, lane-agent, and root-test
     processes, including `port-dolt`, `port-markerpdf`, `port-difftastic`,
     `port-pandoc`, `port-libsqlite`, `port-syncthing`, `port-readability`,
     `port-gitoxide`, `port-rclone`, `port-esbuild`, and active root PID
     `3481431`.
   - Evidence: dirty-tree samples reported `1404` default
     `git status --short --untracked-files=all` rows, `132` tracked changed
     files, and `132 files changed, 39434 insertions(+), 2774 deletions(-)`.
   - Audit judgment: current lane percentages, root-test anecdotes, blockers,
     and latest-commit fields are not acceptance evidence until writers/status
     publishers are frozen and one regenerated snapshot is validated.

2. **High - `porting.html` and `porting-summary.json` are stale and still do
   not meet the dashboard contract.**
   - Paths: `porting.html:30`-`65`, `porting-summary.json:2`-`212`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard tracking of benchmark source, upstream denominator, mapped
     tests, PHP pass/fail, WordPress scenarios, phase, audit, current work,
     blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4865c7edddaf7a680378f347eb4e6`, while current `HEAD` sampled
     as `f40a591b1c99`.
   - Evidence: the dashboard table still collapses required fields:
     `porting.html:41`-`50` has `Benchmark` and `Mapped` columns instead of
     separate benchmark source, upstream denominator, mapped tests, and PHP
     pass/fail columns.
   - Evidence: published counts disagree with current manifests. Difftastic is
     published as `160 / 417` at `porting.html:54` and
     `porting-summary.json:15`-`18`, while the current manifest is `255 / 587`
     at `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`. Rclone is
     published as `291 / 327` at `porting.html:63` and
     `porting-summary.json:168`-`171`, while the current manifest is
     `434 / 2553` at `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
     Pandoc is published as `426 / 2028` at `porting.html:61` and
     `porting-summary.json:134`-`137`, while the current manifest is
     `624 / 2276` at `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`.

3. **High - repo-wide PHP test records are mutually non-comparable.**
   - Paths: `tools/run-tests.php`, `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`13`,
     `lanes/markerpdf/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/readability/lane-status.json:10`-`13`, and
     `lanes/syncthing/lane-status.json:10`-`13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, and `goal.md:49`
     require passing tests to be tied to accepted native slices and recorded
     honestly.
   - Evidence: lane statuses currently claim different aggregate root states:
     Gitoxide records `223` files and `25545` assertions passing at
     `lanes/gitoxide/lane-status.json:10`; libsqlite records `223` files and
     `25567` assertions at `lanes/libsqlite/lane-status.json:10`; markerPDF
     records `221` files and `25477` assertions at
     `lanes/markerpdf/lane-status.json:10`; rclone records `224` files and
     `25662` assertions at `lanes/rclone/lane-status.json:10`; esbuild and
     Readability record duplicate-root pending blockers at
     `lanes/esbuild/lane-status.json:10`-`13` and
     `lanes/readability/lane-status.json:10`-`13`; LightningCSS says root is
     still pending at `lanes/lightningcss/lane-status.json:10`-`12`.
   - Evidence: this audit's required exact duplicate-root gate found active root
     PID `3481431` (`php tools/run-tests.php`), owned by `claude`, so I did not
     start a duplicate root run. A later exact gate after edits returned no
     rows; a pre-commit gate briefly found root PID `3496270`, which exited
     before owner sampling; a later pre-commit gate found active root PID
     `3502370`, owned by `claude`. The stability gate still failed because
     active writer/status loops and the broad dirty tree persisted.
   - Audit judgment: the next accepted test result must be one quiesced root
     run from one accepted tree, not a collection of per-lane anecdotes from a
     moving dirty aggregate.

4. **High - manifest/status schemas still cannot produce trustworthy portfolio
   math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `porting-summary.json`, and `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     `goal.md:44`, and `goal.md:45` require real upstream denominators,
     comparable mapped-test/PHP pass-fail counts, precise blockers, and a
     dashboard generated from those fields.
   - Evidence: denominator units remain mixed. Difftastic uses a prose string
     at `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`, Dolt uses a prose
     string at `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`, Pandoc uses a prose
     string at `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, and Quadrable
     uses a prose string at `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`.
     Gitoxide, libsqlite, LightningCSS, markerPDF, rclone, Readability, and
     Syncthing use numeric totals at their `:14` or `:15` lines.
   - Evidence: `runnerStatus` shape is not normalized. Difftastic and rclone
     use objects at `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:306` and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:477`; Gitoxide and Quadrable
     use long strings at `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18` and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`; markerPDF uses the
     string `not-executed` at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:266`; Pandoc has no
     `runnerStatus` key.
   - Evidence: `latestCommit` fields are not clean commit IDs in several lane
     statuses, including `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.

5. **Medium - runner, oracle, and CLI evidence is still over-mixed with native
   PHP progress.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:266`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/rclone/lane-status.json:10`-`12`, and
     `lanes/readability/lane-status.json:10`-`12`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require bridge/shell-out evidence not to count as native
     implementation progress, upstream tests as source of truth where possible,
     and hard gaps to be recorded explicitly.
   - Evidence: Dolt's denominator prose mixes executable file counts, hydrated
     sparse counts, selected BATS passes, and a direct cache-local Dolt CLI
     probe in one field. Gitoxide's runner-status string combines bounded
     upstream evidence, native PHP assertions, lane PHP results, root PHP
     results, and full-runner deferral in one value. markerPDF records
     `runnerStatus` as `not-executed` while still accumulating static/package
     behavior units. Rclone and Readability lane statuses include bounded or
     live-provider/static upstream evidence next to root/lane PHP pass counts.
   - Audit judgment: this evidence can be useful, but it needs typed fields for
     upstream denominator, focused upstream-runner pass, temporary oracle/CLI
     probe, native PHP behavior count, assertions, failures, blocker, and
     accepted commit.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed exact root harness during the audit:

```text
3481431 php tools/run-tests.php
```

Owner evidence:

```text
3481431 claude 3481430 00:18 R php tools/run-tests.php
3502370 claude 3475806 00:12 Rs php tools/run-tests.php
```

Parent command evidence:

```text
3481430 claude 3371453 00:21 Ss /bin/bash -c tmp=/home/claude/port-libs/.upstream-cache/dolt/tmp/root-php-final-current-worktree-20260523T1155.log; php tools/run-tests.php > "$tmp" 2>&1; status=$?; printf 'status=%s\nlog=%s\n' "$status" "$tmp"; rg -n -C 3 '^FAIL|Expected:|Actual:|failures|^Tests:' "$tmp" | sed -n '1,200p'; tail -n 50 "$tmp"; exit $status
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -o pid,user,ppid,etime,stat,args -p 3481431
pgrep -af 'run-(team-watchdog|capacity-controller|dashboard-updater|evaluator|integrator|auditor)|scripts/run-tmux-agent\.sh port-|tools/run-tests\.php'
git log --oneline --decorate -n 30
git show --stat --oneline --decorate --no-renames -n 8
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. Latest samples
reported `1404` default status rows, `132` tracked changed files, and
`132 files changed, 39434 insertions(+), 2774 deletions(-)`. The process sample
matched active watchdog/agent/status/root-test processes, including root PID
`3481431` while it was active. A later exact duplicate-root gate returned no
rows, a pre-commit gate briefly found PID `3496270` before it exited, and a
later pre-commit gate found active root PID `3502370` owned by `claude`. No
root run was started because the stability gate still failed.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped,
PHP pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
