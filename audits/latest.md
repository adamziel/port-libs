# Independent Audit - 2026-05-23T12:05:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
lane status files needed for status alignment, recent Git history, dirty-tree
state, active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Initial sampled `HEAD` was `957f858700cd`; implementation `HEAD` moved during
the audit through `5b6d5a84e4f3` (`Port libsqlite multi-child index parent
merge`) and `a04f2c8b` (`Port rclone OneDrive permission refresh flow`) before
this audit commit. Recent history reviewed includes `a04f2c8b`, `5b6d5a84`,
`957f8587`, `f40a591b`, `e6182a8c`, `9f4f13f6`, `e77d40c2`, `13c0daf8`,
`55605cb0`, `07221e76`, `6c13ace2`, `2960351d`, `275ec497`, `3032d35b`,
`8b0f5af1`, `613b4eff`, `bdce6ef2`, and `fabad4ea`.

## Findings

1. **Critical - the repository still has no stable integration snapshot to
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`, `progress.md:31` in the
     current-owner section, `porting.html:32`-`36`,
     `porting-summary.json:2`-`8`, all `lanes/*/lane-status.json`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallel work
     capped to VM capacity; `goal.md:29` requires small reviewable slices with
     passing tests; `goal.md:44` requires current owner/session status; and
     `goal.md:49` requires honest repo-wide test recording.
   - Evidence: `progress.md:25` still says the launch target is 2
     implementation lanes plus 1 auditor, and `progress.md:31`-`42` still
     reports every lane as `stopped`. Process sampling found active
     team-watchdog, capacity-controller, dashboard-updater, evaluator,
     integrator, auditor, capacity, lane-agent, and root-test processes.
   - Evidence: the dirty tree remained broad while `HEAD` moved. Latest samples
     reported `1443` default `git status --short --untracked-files=all` rows,
     `140` tracked changed files, and
     `140 files changed, 41107 insertions(+), 2799 deletions(-)`.
   - Audit judgment: lane percentages, root-test anecdotes, blockers, and
     latest-commit fields are still not acceptance evidence until active
     writers/status publishers are frozen and one regenerated snapshot is
     validated.

2. **High - the public dashboard is stale and still does not satisfy the
   required dashboard contract.**
   - Paths: `porting.html:32`-`65`, `porting-summary.json:2`-`213`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track benchmark source, upstream denominator, mapped tests,
     PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker,
     and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4865c7edddaf7a680378f347eb4e6`, while current `HEAD` sampled
     as `c34da8aee81d` after this audit commit, with implementation commit
     `a04f2c8b` immediately below it.
   - Evidence: the table still collapses required fields into `Benchmark` and
     `Mapped` columns at `porting.html:41`-`50`; it does not expose separate
     benchmark source, denominator, mapped tests, and PHP pass/fail columns as
     required by `goal.md:45`.
   - Evidence: current manifest counts disagree with the published dashboard.
     Examples: Difftastic is published as `160 / 417` at `porting.html:54` and
     `porting-summary.json:15`-`18`, but the current manifest is `257 / 587` at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`. Dolt is published
     as `242 / 613` at `porting.html:55` and `porting-summary.json:32`-`35`,
     but the current manifest is `466 / 613` at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`. Rclone is published as
     `291 / 327` at `porting.html:63` and `porting-summary.json:168`-`171`,
     but the current manifest is `439 / 2553` at
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`. Readability is
     published as `1031 / 1984` at `porting.html:64` and
     `porting-summary.json:185`-`188`, but the current manifest is
     `1548 / 1984` at `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`.

3. **High - repo-wide PHP test records are mutually non-comparable.**
   - Paths: `tools/run-tests.php`, `lanes/difftastic/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/pandoc/lane-status.json:10`-`13`,
     `lanes/quadrable/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/readability/lane-status.json:10`-`13`, and
     `lanes/syncthing/lane-status.json:10`-`13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, and `goal.md:49`
     require passing tests to be tied to accepted native slices and recorded
     honestly.
   - Evidence: lane status files currently record incompatible aggregate root
     states from different moving snapshots. Gitoxide records root green with
     `223` files and `25545` assertions at
     `lanes/gitoxide/lane-status.json:10`; libsqlite records root green with
     `224` files and `25764` assertions at `lanes/libsqlite/lane-status.json:10`;
     rclone records root green with `224` files and `25662` assertions at
     `lanes/rclone/lane-status.json:10`; pandoc and quadrable record a red
     root with `224` files, `25731` assertions, and one libsqlite failure at
     `lanes/pandoc/lane-status.json:10`-`12` and
     `lanes/quadrable/lane-status.json:10`-`12`; difftastic records a red root
     with three failures outside difftastic at
     `lanes/difftastic/lane-status.json:10`-`12`.
   - Evidence: the required duplicate-root gate returned active exact root
     harnesses, so this audit did not start another root run:

     ```text
     3590340 php tools/run-tests.php
     3613707 php tools/run-tests.php
     3625897 php tools/run-tests.php
     ```

     Owner evidence:

     ```text
     3590340 claude 3482097 00:02 Rs php tools/run-tests.php
     3613707 claude 3613701 00:20 R php tools/run-tests.php
     3625897 claude 3496767 00:14 Rs php tools/run-tests.php
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
     pandoc, and Quadrable store prose denominators at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`; Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing use numeric
     totals.
   - Evidence: `runnerStatus` shape is still non-normalized. Difftastic, Dolt,
     esbuild, libsqlite, LightningCSS, rclone, Readability, and Syncthing use
     objects; Gitoxide and Quadrable use long strings at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18` and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`; markerPDF uses the
     string `not-executed` at `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:266`;
     Pandoc has no `runnerStatus` key.
   - Evidence: some manifests now contain internal count drift. LightningCSS
     has `mapped: 1180` at
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`, while its warning
     still says native progress maps `1177` checks at
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:1473`. Difftastic has
     stale and current warning text in the same manifest: one warning says
     `253` focused lane tests at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:522`, while the later
     warning and `mapped` field report `255`/`257`-level newer counts at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:532` and `:15`.
   - Evidence: `latestCommit` fields are still not clean accepted commit IDs
     in several lane statuses, including `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.

5. **Medium - bridge, runner, oracle, and CLI evidence is still over-mixed with
   native PHP progress.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`31`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`-`21`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require bridge/shell-out evidence not to
     count as native implementation progress, upstream tests as source of truth
     where possible, and hard gaps to be recorded explicitly.
   - Evidence: Dolt's denominator prose mixes executable-file denominator,
     hydrated sparse counts, selected BATS passes, and direct cache-local Dolt
     CLI probes in one field. Gitoxide's runner status mixes upstream runner
     evidence, PHP assertion counts, and full-runner deferral in a single long
     string. markerPDF maps supplied documents, downloaded dependency archives,
     and plan-only model/CLI boundaries without a live upstream conversion
     runner. Quadrable's runner status mixes repeated C++ runner passes with
     oracle fixtures and native command-output mappings. Rclone and Readability
     mix upstream runner/oracle evidence with native PHP behavior counts in
     dashboard-visible fields.
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

Observed active exact root harnesses during the audit:

```text
3590340 php tools/run-tests.php
3613707 php tools/run-tests.php
3625897 php tools/run-tests.php
```

Owner evidence:

```text
3590340 claude 3482097 00:02 Rs php tools/run-tests.php
3613707 claude 3613701 00:20 R php tools/run-tests.php
3625897 claude 3496767 00:14 Rs php tools/run-tests.php
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -o pid,user,ppid,etime,stat,args -p 3590340
ps -o pid,user,ppid,etime,stat,args -p 3613707
ps -o pid,user,ppid,etime,stat,args -p 3625897
pgrep -af 'run-(team-watchdog|capacity-controller|dashboard-updater|evaluator|integrator|auditor)|scripts/run-tmux-agent\.sh port-|tools/run-tests\.php'
git log --oneline --decorate -n 30
git show --stat --oneline --decorate --no-renames -n 10
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. Latest samples
reported `1443` default status rows, `140` tracked changed files, and
`140 files changed, 41107 insertions(+), 2799 deletions(-)`. No root run was
started because the exact duplicate-root gate was active and the stability gate
still failed.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped,
PHP pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
