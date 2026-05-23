# Independent Audit - 2026-05-23T12:22:26Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for status alignment, recent Git history, dirty-tree state,
active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `0f1444c1c8c0` (`Refresh independent audit
status`). Recent history reviewed includes `0f1444c1`, `efa4e0c2`, `f8bd46e4`,
`09995598`, `a04f2c8b`, `5b6d5a84`, `957f8587`, `f40a591b`, `e6182a8c`,
`9f4f13f6`, `e77d40c2`, and `13c0daf8`.

## Findings

1. **Critical - the repository still has no stable integration snapshot to
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json`, `porting.html`, `porting-summary.json`, and
     all `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallel work
     capped to VM capacity, `goal.md:29` requires small reviewable slices with
     passing tests, `goal.md:44` requires accurate owner/session status, and
     `goal.md:49` requires honest repo-wide test recording.
   - Evidence: `progress.md:25` still says the launch target is 2
     implementation lanes plus 1 auditor, while `progress.md:31`-`42` reports
     all 12 lanes as `stopped`. Process sampling found 21 active matching
     repo worker/status/test processes, including team watchdog,
     capacity-controller, dashboard-updater, evaluator, integrator, auditor,
     lane-agent, and capacity jobs.
   - Evidence: the latest dirty-tree sample reported `1473` default
     `git status --short --untracked-files=all` rows, `158` tracked changed
     files, and `158 files changed, 43888 insertions(+), 2842 deletions(-)`.
   - Evidence: the required duplicate-root gate initially returned no rows, but
     a later exact gate returned active root PID `3782892`; owner evidence was
     `3782892 claude 3758230 00:08 Rs php tools/run-tests.php`. A subsequent
     validation gates briefly returned PIDs `3788117` and `3796506`, which
     exited before owner sampling. The latest exact gate returned active root
     PID `3797650`; owner evidence was
     `3797650 claude 3773513 00:30 Rs php tools/run-tests.php`. I did not
     start a duplicate root harness.
   - Audit judgment: lane percentages, green lane-local claims, root anecdotes,
     blockers, and latest-commit fields remain non-acceptance evidence until
     active writers/status publishers are frozen and one regenerated snapshot
     is validated.

2. **High - the public dashboard is stale and still violates the required
   dashboard contract.**
   - Paths: `porting.html:32`-`36`, `porting.html:41`-`50`,
     `porting-summary.json:2`-`8`, and all lane manifests.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require tracking
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `0f1444c1c8c0`.
   - Evidence: `porting.html:41`-`50` still exposes compact `Benchmark` and
     `Mapped` columns instead of separate benchmark source, upstream
     denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifests. Examples:
     Difftastic publishes `160 / 417` at `porting.html:54`, but the manifest is
     `260` mapped over a `587...` denominator at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Dolt publishes
     `242 / 613` at `porting.html:55`, but the manifest is `473 / 613` at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Gitoxide publishes
     `1432 / 2877` at `porting.html:57`, but the manifest is `1915 / 2877` at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`15`; libsqlite publishes
     `149 / 1454` at `porting.html:58`, but the manifest is `211 / 1589` at
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15`-`16`; markerPDF
     publishes `159 / 78` at `porting.html:60`, but the manifest is
     `218 / 272` at `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`;
     rclone publishes `291 / 327` at `porting.html:63`, but the manifest is
     `448 / 2553` at `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`; and
     Readability publishes `1031 / 1984` at `porting.html:64`, but the
     manifest is `1563 / 1984` at
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`.

3. **High - repo-wide PHP test records are mutually non-comparable.**
   - Paths: `tools/run-tests.php`, all `lanes/*/lane-status.json`,
     `progress.md`, and the root-test notes embedded in several manifests.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, and `goal.md:49`
     require passing tests to be tied to accepted native slices and recorded
     honestly.
   - Evidence: Difftastic records a root pass with `225` files and `26061`
     assertions at `lanes/difftastic/lane-status.json:10`; Gitoxide records a
     root pass with `225` files and `26043` assertions at
     `lanes/gitoxide/lane-status.json:10`; Readability records a root pass with
     `225` files and `26019` assertions at
     `lanes/readability/lane-status.json:10`; Dolt, LightningCSS, and Pandoc
     record root passes with `226` files and `26113` assertions at
     `lanes/dolt/lane-status.json:10`, `lanes/lightningcss/lane-status.json:10`,
     and `lanes/pandoc/lane-status.json:10`; Esbuild records an aggregate root
     failure with `225` files, `26020` assertions, and 3 Difftastic failures at
     `lanes/esbuild/lane-status.json:10`; and Syncthing, rclone, Quadrable,
     markerPDF, and libsqlite currently record pending or duplicate-gated root
     status at their `lane-status.json:10`-`13` fields.
   - Audit judgment: the next accepted root result must be one quiesced
     `php tools/run-tests.php` run from one accepted tree, not lane-by-lane
     anecdotes gathered while active writers mutate the checkout.

4. **High - manifest/status schemas still cannot produce trustworthy
   portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `porting-summary.json`, and `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, `goal.md:44`, and
     `goal.md:45` require real upstream denominators, explicit slices,
     comparable mapped-test/PHP pass-fail counts, precise blockers, and a
     dashboard generated from those fields.
   - Evidence: denominator units remain mixed. Difftastic, Dolt, Esbuild,
     Pandoc, and Quadrable store prose strings at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`; other lanes store
     numeric totals.
   - Evidence: `runnerStatus` shape remains non-normalized: objects in several
     manifests, long strings in Gitoxide and Quadrable at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18` and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, the string
     `not-executed` in markerPDF at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:271`, and no
     `runnerStatus` value in Pandoc's denominator block.
   - Evidence: `latestCommit` is still not a clean accepted commit field in
     multiple lane statuses. Examples include pending or prose dirty-batch
     states at `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.

5. **Medium - bridge, runner, oracle, and CLI evidence is still over-mixed
   with native PHP progress.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:271`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require bridge/shell-out evidence not to
     count as native implementation progress, upstream tests as source of truth
     where possible, meaningful fixture parity, and hard gaps recorded
     explicitly.
   - Evidence: Dolt's denominator mixes static executable files, focused BATS
     passes, selected references, local utility rebuilds, and skipped suite
     classes in one field. Gitoxide's runner status mixes focused upstream
     runner shards, static source inventory, PHP assertion counts, and root PHP
     anecdotes in one long string. markerPDF still reports `runnerStatus:
     not-executed` while the same status surface includes CI archive,
     package/runtime planning, supplied-document, benchmark, and memory-profile
     evidence. Quadrable's runner status repeatedly folds C++ runner passes,
     LMDB dump/load oracles, command-output wrappers, and opt-in benchmark
     exclusions into one field. Rclone and Readability have useful bounded
     upstream evidence, but their dashboard-visible fields still do not
     separate upstream runner pass, temporary oracle/CLI probe, native PHP
     behavior count, assertions, failures, blocker, and accepted commit.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed output at the first audit sample: no rows.

Later exact duplicate-root gate:

```text
3782892 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
3782892 claude  3758230  00:08   Rs   php tools/run-tests.php
```

A subsequent validation gate briefly returned:

```text
3788117 php tools/run-tests.php
```

`ps -o pid,user,ppid,etime,stat,args -p 3788117` returned only the header
because that process had already exited. A later validation gate briefly
returned:

```text
3796506 php tools/run-tests.php
```

`ps -o pid,user,ppid,etime,stat,args -p 3796506` also returned only the header
because that process had already exited. The latest exact gate returned:

```text
3797650 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
3797650 claude  3773513  00:30   Rs   php tools/run-tests.php
```

No duplicate root harness was started by this audit, and the stability gate
also failed because active repo worker/status processes and a broad dirty tree
remained present.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -o pid,user,ppid,etime,stat,args -p 3782892
ps -o pid,user,ppid,etime,stat,args -p 3788117
ps -o pid,user,ppid,etime,stat,args -p 3796506
ps -o pid,user,ppid,etime,stat,args -p 3797650
pgrep -af 'run-(team-watchdog|capacity-controller|dashboard-updater|evaluator|integrator|auditor)|scripts/run-tmux-agent\.sh port-|tools/run-tests\.php'
git log --oneline --decorate -n 12
git show --stat --oneline --decorate --no-renames -n 16
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. Latest samples
reported `1473` default status rows, `158` tracked changed files, and
`158 files changed, 43888 insertions(+), 2842 deletions(-)`. No root run was
started because exact root harnesses were active during validation and the tree
was not stable enough for accepted aggregate evidence.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
