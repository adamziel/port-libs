# Independent Audit - 2026-05-23T12:16:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for status alignment, recent Git history, dirty-tree state,
active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `f8bd46e4f473` (`Refresh independent audit
status`). Recent history reviewed includes `f8bd46e4`, `09995598`, `a04f2c8b`,
`5b6d5a84`, `957f8587`, `f40a591b`, `e6182a8c`, `9f4f13f6`, `e77d40c2`,
`13c0daf8`, `55605cb0`, `07221e76`, `6c13ace2`, `2960351d`, `275ec497`,
`3032d35b`, `8b0f5af1`, `613b4eff`, `bdce6ef2`, and `fabad4ea`.

## Findings

1. **Critical - the repository still has no stable integration snapshot to
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`, all
     `lanes/*/lane-status.json`, all `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `porting.html`, and `porting-summary.json`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallel work
     capped to VM capacity; `goal.md:29` requires small reviewable slices with
     passing tests; `goal.md:44` requires current owner/session status; and
     `goal.md:49` requires honest repo-wide test recording.
   - Evidence: `progress.md:25` still says the launch target is 2
     implementation lanes plus 1 auditor, while `progress.md:31`-`42` reports
     every lane as `stopped`. Process sampling still found active
     team-watchdog, capacity-controller, dashboard-updater, evaluator, auditor,
     lane-agent, capacity, and `port-capacity-php-clean-head-root` processes.
   - Evidence: the current dirty-tree sample reported `1493` default
     `git status --short --untracked-files=all` rows, `158` tracked changed
     files, and `158 files changed, 43125 insertions(+), 2873 deletions(-)`.
   - Evidence: the exact duplicate-root gate returned no rows at this audit
     sample, but the tree is not stable enough for a meaningful root
     acceptance run. I did not start `php tools/run-tests.php`.
   - Audit judgment: lane percentages, green lane-local claims, root anecdotes,
     blockers, and latest-commit fields remain non-acceptance evidence until
     active writers/status publishers are frozen and one regenerated snapshot
     is validated.

2. **High - the public dashboard is stale and violates the required dashboard
   contract.**
   - Paths: `porting.html:32`-`36`, `porting.html:41`-`50`,
     `porting-summary.json:2`-`8`, and all lane manifests.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track benchmark source, upstream denominator, mapped tests,
     PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker,
     and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4865c7edddaf7a680378f347eb4e6`, while current `HEAD` is
     `f8bd46e4f473`.
   - Evidence: `porting.html:41`-`50` still exposes only compact `Benchmark`
     and `Mapped` columns instead of separate benchmark source, denominator,
     mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifests. Examples:
     Difftastic is published as `160 / 417` at `porting.html:54`, but the
     manifest is `257 / 587` at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Gitoxide is
     published as `1432 / 2877` at `porting.html:57`, but the manifest is
     `1902 / 2877` at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`15`; LightningCSS is
     published as `773 / 3532` at `porting.html:59`, but the manifest is
     `1180 / 3532` at
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`-`15`; markerPDF is
     published as `159 / 78` at `porting.html:60`, but the manifest is
     `218 / 272` at `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`;
     rclone is published as `291 / 327` at `porting.html:63`, but the manifest
     is `444 / 2553` at `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`;
     and Syncthing is published as `235 / 658` at `porting.html:65`, but the
     manifest is `330 / 658` at
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15`.

3. **High - repo-wide PHP test records are mutually non-comparable.**
   - Paths: `tools/run-tests.php`, `lanes/*/lane-status.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, and `progress.md`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, and `goal.md:49`
     require passing tests to be tied to accepted native slices and recorded
     honestly.
   - Evidence: lane status files report incompatible aggregate states from
     different moving snapshots. Difftastic records root green with `225` files
     and `26061` assertions at `lanes/difftastic/lane-status.json:10`;
     Gitoxide records root green with `225` files and `26043` assertions at
     `lanes/gitoxide/lane-status.json:10`; LightningCSS records root green with
     `224` files and `25901` assertions at
     `lanes/lightningcss/lane-status.json:10`; Pandoc records root green with
     `224` files and `25874` assertions at `lanes/pandoc/lane-status.json:10`;
     Readability records root green with `225` files and `26019` assertions at
     `lanes/readability/lane-status.json:10`; Syncthing records a root failure
     with `225` files, `25905` assertions, and `9` failures at
     `lanes/syncthing/lane-status.json:10`; rclone records root pending because
     PID `3663662` was active at `lanes/rclone/lane-status.json:10`-`13`; and
     Quadrable records root pending because PID `3716969` was active at
     `lanes/quadrable/lane-status.json:10`-`13`.
   - Evidence: Dolt's manifest contains historical root results with different
     denominators, including `193` files at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:952`, `198` files at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:1004`, `224` files at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:254`, and a later outside-Dolt
     failure at `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:1176`.
   - Audit judgment: the next accepted root result must be one quiesced
     `php tools/run-tests.php` run from one accepted tree, not lane-by-lane
     anecdotes gathered while active writers mutate the checkout.

4. **High - manifest/status schemas still cannot produce trustworthy portfolio
   math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `porting-summary.json`, and `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, `goal.md:44`, and
     `goal.md:45` require real upstream denominators, explicit slices,
     comparable mapped-test/PHP pass-fail counts, precise blockers, and a
     dashboard generated from those fields.
   - Evidence: denominator units remain mixed. Difftastic, Dolt, Pandoc, and
     Quadrable keep prose totals at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, while Gitoxide,
     libsqlite, LightningCSS, markerPDF, rclone, Readability, Syncthing, and
     some esbuild fields are numeric.
   - Evidence: `runnerStatus` shape remains non-normalized: objects in many
     manifests, a long string in Gitoxide at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, a long string in
     Quadrable at `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, the string
     `not-executed` in markerPDF at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:271`, and no `runnerStatus`
     key in Pandoc's denominator block.
   - Evidence: `latestCommit` is still not a clean accepted commit field in
     multiple lane statuses. Examples include prose or pending states at
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`, `lanes/rclone/lane-status.json:13`,
     and `lanes/syncthing/lane-status.json:13`.

5. **Medium - bridge, runner, oracle, and CLI evidence is still over-mixed
   with native PHP progress.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:16`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:16`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require bridge/shell-out evidence not to
     count as native implementation progress, upstream tests as source of
     truth where possible, meaningful fixture parity, and hard gaps recorded
     explicitly.
   - Evidence: Dolt's denominator mixes static file counts, focused BATS
     passes, targeted references, and direct cache-local Dolt CLI probes in one
     field. Gitoxide's runner status mixes upstream runner shards, static
     source inventory, PHP assertion counts, duplicate-root deferral, and
     cargo-runner gaps in one string. markerPDF's source field includes
     downloaded wheels/tarballs, CI archive probes, supplied-document fixtures,
     package metadata, dependency graph planning, and runtime install plans in
     one progress surface while `runnerStatus` remains `not-executed`.
     Quadrable's runner status repeatedly folds upstream C++ runner passes,
     LMDB dump/load oracles, command-output mappings, and opt-in benchmark
     exclusions into one long status field. Rclone and Readability have useful
     bounded upstream evidence, but their dashboard-visible fields still do not
     separate upstream runner pass, temporary oracle/CLI probe, native PHP
     behavior count, assertions, failures, blocker, and accepted commit.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed output during the audit sample: no rows. A later handoff check found
transient exact root harness PIDs before owner sampling:

```text
3776814 php tools/run-tests.php
3776871 php tools/run-tests.php
```

`ps -o pid,user,ppid,etime,stat,args -p 3776814,3776871` returned only the
header because both processes had already exited. A final exact gate then
returned no rows. No duplicate root harness was started by this audit, and the
stability gate failed because active repo worker/status processes and a broad
dirty tree remained present.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -o pid,user,ppid,etime,stat,args -p 3776814,3776871
pgrep -af 'run-(team-watchdog|capacity-controller|dashboard-updater|evaluator|integrator|auditor)|scripts/run-tmux-agent\.sh port-|tools/run-tests\.php'
git log --oneline --decorate -n 24
git show --stat --oneline --decorate --no-renames -n 12
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. Latest samples
reported `1493` default status rows, `158` tracked changed files, and
`158 files changed, 43125 insertions(+), 2873 deletions(-)`. No root run was
started because the tree was not stable enough for accepted aggregate evidence.

## Next Intervention

Freeze active writers/status publishers and any root/focused PHP loops first.
Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped,
PHP pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
