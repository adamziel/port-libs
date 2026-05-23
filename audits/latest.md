# Independent Audit - 2026-05-23T12:10:16Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for status alignment, recent Git history, dirty-tree state,
active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `099955980eb7` (`Refresh independent audit
status`). Recent history reviewed includes `09995598`, `a04f2c8b`, `5b6d5a84`,
`957f8587`, `f40a591b`, `e6182a8c`, `9f4f13f6`, `e77d40c2`, `13c0daf8`,
`55605cb0`, `07221e76`, `6c13ace2`, `2960351d`, `275ec497`, `3032d35b`,
`8b0f5af1`, `613b4eff`, `bdce6ef2`, and `fabad4ea`.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`, `porting.html:32`-`36`,
     `porting-summary.json:2`-`8`, all `lanes/*/lane-status.json`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallel work
     capped to VM capacity; `goal.md:29` requires small reviewable slices with
     passing tests; `goal.md:44` requires current owner/session status; and
     `goal.md:49` requires honest repo-wide test recording.
   - Evidence: `progress.md:25` still says the launch target is 2
     implementation lanes plus 1 auditor, while `progress.md:31`-`42` reports
     every lane as `stopped`. Process sampling found active team-watchdog,
     capacity-controller, dashboard-updater, evaluator, integrator, auditor,
     capacity, lane-agent, and root-test processes.
   - Evidence: the required duplicate-root gate found an active exact root
     harness, PID `3657726`, owned by `claude`.
   - Evidence: the dirty tree is still broad. Latest samples reported `1459`
     default `git status --short --untracked-files=all` rows, `145` tracked
     changed files, and
     `145 files changed, 42069 insertions(+), 2843 deletions(-)`.
   - Audit judgment: lane percentages, root-test anecdotes, blockers, and
     latest-commit fields are not acceptance evidence until active
     writers/status publishers are frozen and one regenerated snapshot is
     validated.

2. **High - the public dashboard is stale and still does not satisfy the
   required dashboard contract.**
   - Paths: `porting.html:32`-`65`, `porting-summary.json:2`-`80`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track benchmark source, upstream denominator, mapped tests,
     PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker,
     and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4865c7edddaf7a680378f347eb4e6`, while the audit sampled
     `HEAD` as `099955980eb7`.
   - Evidence: the table still collapses required fields into `Benchmark` and
     `Mapped` columns at `porting.html:41`-`50`; it does not expose separate
     benchmark source, denominator, mapped tests, and PHP pass/fail columns as
     required by `goal.md:45`.
   - Evidence: dashboard rows disagree with current manifests for every lane
     except Quadrable's mapped count. Examples: Difftastic is published as
     `160 / 417` at `porting.html:54` and
     `porting-summary.json:15`-`18`, but the current manifest is `257 / 587`
     at `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`. Gitoxide is
     published as `1432 / 2877` at `porting.html:57`, but the current manifest
     is `1902 / 2877` at `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
     markerPDF is published as `159 / 78` at `porting.html:60`, but the current
     manifest is `217 / 271` at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`. Rclone is
     published as `291 / 327` at `porting.html:63`, but the current manifest is
     `439 / 2553` at `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
     Syncthing is published as `235 / 658` at `porting.html:65`, but the
     current manifest is `326 / 658` at
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15`.

3. **High - repo-wide PHP test records are mutually non-comparable.**
   - Paths: `tools/run-tests.php`, all `lanes/*/lane-status.json`, and
     `progress.md`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, and `goal.md:49`
     require passing tests to be tied to accepted native slices and recorded
     honestly.
   - Evidence: lane status files record incompatible aggregate root states from
     different moving snapshots. Dolt records a green root with `224` files and
     `25,835` assertions at `lanes/dolt/lane-status.json:10`; libsqlite records
     green with `224` files and `25,764` assertions at
     `lanes/libsqlite/lane-status.json:10`; LightningCSS records green with
     `224` files and `25,901` assertions at
     `lanes/lightningcss/lane-status.json:10`; Pandoc and rclone record green
     with `224` files and `25,874` assertions at
     `lanes/pandoc/lane-status.json:10` and `lanes/rclone/lane-status.json:10`;
     Quadrable records a red root with one libsqlite failure at
     `lanes/quadrable/lane-status.json:10`; and Difftastic, esbuild, Gitoxide,
     markerPDF, Readability, and Syncthing record root status as pending because
     another root harness was active.
   - Evidence: the required duplicate-root gate returned an active exact root
     harness, so this audit did not start another root run:

     ```text
     3657726 php tools/run-tests.php
     ```

     Owner evidence:

     ```text
     3657726 claude 3542302 00:26 Rs php tools/run-tests.php
     ```

   - Audit judgment: the next accepted root result must be one quiesced
     `php tools/run-tests.php` run from one accepted tree, not per-lane
     anecdotes collected while writers continue to mutate the checkout.

4. **High - manifest/status schemas still cannot produce trustworthy portfolio
   math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `porting-summary.json`, and `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     `goal.md:44`, and `goal.md:45` require real upstream denominators,
     comparable mapped-test/PHP pass-fail counts, precise blockers, and a
     dashboard generated from those fields.
   - Evidence: denominator units are still mixed. Difftastic, Dolt, esbuild,
     Pandoc, and Quadrable store prose denominators at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`; other lanes use numeric
     totals.
   - Evidence: `runnerStatus` shape is still non-normalized. Difftastic, Dolt,
     esbuild, libsqlite, LightningCSS, rclone, Readability, and Syncthing use
     objects; Gitoxide and Quadrable use long strings at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18` and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`; markerPDF uses the
     string `not-executed` at `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:269`;
     Pandoc has no `runnerStatus` key.
   - Evidence: `latestCommit` fields are still not clean accepted commit IDs in
     every lane status file, including `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.

5. **Medium - bridge, runner, oracle, and CLI evidence is still over-mixed with
   native PHP progress.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`24`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:269`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/readability/lane-status.json:10`-`12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require bridge/shell-out evidence not to
     count as native implementation progress, upstream tests as source of truth
     where possible, and hard gaps to be recorded explicitly.
   - Evidence: Dolt's denominator prose mixes executable-file denominator,
     selected BATS passes, targeted references, and direct cache-local Dolt CLI
     probes in one field. Gitoxide's runner status mixes focused upstream
     runners, static source inventory, PHP assertion counts, and full-runner
     deferral in one string. markerPDF maps package/CLI planning while the
     upstream runner remains `not-executed`. Quadrable's runner status mixes
     repeated C++ runner passes with raw LMDB dump/load oracle fixtures. Rclone
     and Readability include useful bounded upstream/oracle evidence, but the
     dashboard-visible fields still do not separate temporary oracle evidence
     from native PHP behavior counts.
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

Observed active exact root harness during the audit:

```text
3657726 php tools/run-tests.php
```

Owner evidence:

```text
3657726 claude 3542302 00:26 Rs php tools/run-tests.php
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -o pid,user,ppid,etime,stat,args -p 3657726
pgrep -af 'run-(team-watchdog|capacity-controller|dashboard-updater|evaluator|integrator|auditor)|scripts/run-tmux-agent\.sh port-|tools/run-tests\.php'
git log --oneline --decorate -n 20
git show --stat --oneline --decorate --no-renames -n 12
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. Latest samples
reported `1459` default status rows, `145` tracked changed files, and
`145 files changed, 42069 insertions(+), 2843 deletions(-)`. No root run was
started because the exact duplicate-root gate was active and the stability gate
still failed.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
