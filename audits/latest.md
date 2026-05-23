# Independent Audit - 2026-05-23T12:35:08Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for dashboard/status alignment, recent Git history, dirty
tree state, active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `81419ac3232c` (`Refresh independent audit
status`). Recent history reviewed includes `81419ac3`, `69405063`,
`0f1444c1`, `efa4e0c2`, `f8bd46e4`, `09995598`, `a04f2c8b`,
`5b6d5a84`, `957f8587`, `f40a591b`, `e6182a8c`, `9f4f13f6`,
`e77d40c2`, `13c0daf8`, `55605cb0`, `07221e76`, `6c13ace2`,
`2960351d`, `275ec497`, and `3032d35b`.

During commit handoff, implementation history advanced once more to
`90d1fa3b` (`Port rclone OneDrive multipart upload metadata`) before this
audit-only update landed. That reinforces the finding that this checkout was
not a frozen integration snapshot.

## Findings

1. **Critical - the repository is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json`, `porting.html`, and
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallel work
     capped to VM capacity, `goal.md:29` requires small reviewable slices with
     passing tests, `goal.md:44` requires current owner/session/status
     tracking, and `goal.md:49` requires honest repo-wide test recording.
   - Evidence: `progress.md:25` still documents a launch target of 2
     implementation lanes plus 1 auditor, and `progress.md:31`-`42` reports
     all 12 lanes as `stopped`.
   - Evidence: process sampling still found 18 active repo worker/status
     processes, including `run-team-watchdog`, `run-evaluator-loop`,
     `run-capacity-controller-loop`, `run-dashboard-updater-loop`, lane agents
     for rclone, esbuild, dolt-runner, libsqlite, readability, gitoxide,
     quadrable, lightningcss, markerPDF, difftastic, dolt, plus integrator and
     auditor agents.
   - Evidence: the latest dirty-tree sample reported `1519` default
     `git status --short --untracked-files=all` rows, `164` tracked changed
     files, and `164 files changed, 46039 insertions(+), 2848 deletions(-)`.
   - Audit judgment: lane percentages, green lane-local claims, latest commit
     fields, and root-test anecdotes are still non-acceptance evidence until
     active writers/status publishers are frozen and one regenerated snapshot
     is validated.

2. **High - `porting.html` and `porting-summary.json` remain stale and do not
   satisfy the dashboard contract.**
   - Paths: `porting.html:32`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, and `porting-summary.json:2`-`8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     tracking of benchmark source, upstream denominator, mapped tests, PHP
     pass/fail, WordPress scenarios, phase, audit, current work, blocker, and
     latest commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `81419ac3232c`.
   - Evidence: `porting.html:41`-`50` still exposes compact `Benchmark` and
     `Mapped` columns instead of separate benchmark source, upstream
     denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifests. Examples:
     Difftastic publishes `160 / 417` at `porting.html:54`, but the manifest is
     `263` mapped over `587...` at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Dolt publishes
     `242 / 613` at `porting.html:55`, but the manifest is `481 / 613...` at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Esbuild publishes
     `164 / 2,567` at `porting.html:56`, but the manifest is `226 / 2,567` at
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Gitoxide publishes
     `1432 / 2877` at `porting.html:57`, but the manifest is `1918 / 2877` at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`15`; libsqlite publishes
     `149 / 1454` at `porting.html:58`, but the manifest is `212 / 1589` at
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15`-`16`; LightningCSS
     publishes `773 / 3532` at `porting.html:59`, but the manifest is
     `1188 / 3532` at
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`-`15`; markerPDF
     publishes `159 / 78` at `porting.html:60`, but the manifest is
     `219 / 273` at `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`;
     Pandoc publishes `426 / 2028` at `porting.html:61`, but the manifest is
     `645 / 2276...` at `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`;
     rclone publishes `291 / 327` at `porting.html:63`, but the manifest is
     `453 / 2553` at `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`;
     Readability publishes `1031 / 1984` at `porting.html:64`, but the
     manifest is `1564 / 1984` at
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`; and Syncthing
     publishes `235 / 658` at `porting.html:65`, but the manifest is
     `335 / 658` at `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15`.

3. **High - repo-wide PHP test records remain mutually non-comparable.**
   - Paths: `tools/run-tests.php`, `progress.md`,
     `lanes/*/lane-status.json`, especially
     `lanes/dolt/lane-status.json`, `lanes/gitoxide/lane-status.json`,
     `lanes/libsqlite/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     and `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, and `goal.md:49`
     require passing tests to be tied to accepted native slices and recorded
     honestly.
   - Evidence: current lane statuses mix incompatible root claims. Dolt records
     an aggregate root red outside Dolt in
     `lanes/rclone/tests/OneDrivePermissionPlannerTest.php`; Gitoxide,
     libsqlite, Pandoc, and Syncthing record green root harness anecdotes with
     different assertion totals; Difftastic, rclone, Readability, markerPDF,
     and LightningCSS record pending or duplicate-gated root states. Several
     `latestCommit` fields remain `pending`, `uncommitted`, `current`, or
     dirty-batch prose at `lanes/*/lane-status.json:13`.
   - Evidence: the exact duplicate-root gate was clear at the two audit
     samples, but the tree failed the stability gate because active writers and
     status publishers were still running and the dirty tree was broad.
   - Audit judgment: the next accepted test result must be one quiesced
     `php tools/run-tests.php` run from one accepted tree, not lane-by-lane
     anecdotes gathered while active workers mutate the checkout.

4. **High - manifest/status schemas still cannot produce trustworthy
   portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `porting-summary.json`, and `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, `goal.md:44`, and
     `goal.md:45` require real upstream denominators, explicit slices,
     comparable mapped-test/PHP pass-fail counts, precise blockers, and a
     dashboard generated from those fields.
   - Evidence: denominator units remain mixed. Difftastic, Dolt, Esbuild,
     Pandoc, and Quadrable store prose-string totals at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, while Gitoxide,
     libsqlite, LightningCSS, markerPDF, rclone, Readability, and Syncthing use
     numeric totals.
   - Evidence: `runnerStatus` is still non-normalized: objects in several
     manifests, long strings in Gitoxide and Quadrable at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18` and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, the string
     `not-executed` in markerPDF at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:273`, and no comparable
     Pandoc runner-status value in the denominator block.
   - Evidence: current manifests have no single typed field set for PHP
     pass/fail counts, latest accepted commit, runner execution status, and
     native-progress classification that the dashboard can safely aggregate.

5. **Medium - upstream runner, oracle, generated-fixture, and CLI evidence is
   still over-mixed with native PHP progress.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:273`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`18`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:16`-`17`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require bridge/shell-out evidence not to
     count as native implementation progress, upstream tests as source of truth
     where possible, meaningful fixture parity, and hard gaps recorded
     explicitly.
   - Evidence: Gitoxide's runner status combines bounded upstream runner
     shards, static source inventory, PHP assertion counts, and root PHP
     anecdotes in one string. markerPDF still reports `runnerStatus:
     not-executed` while the same status surface includes CI archive,
     benchmark, package/runtime, supplied-document, OCR, and memory-profile
     planning evidence. Quadrable folds C++ runner passes, LMDB dump/load
     oracles, command-output wrappers, and opt-in benchmark exclusions into one
     field. Rclone and Syncthing include useful bounded upstream evidence, but
     the visible fields still do not separate upstream runner pass, temporary
     oracle/CLI probe, native PHP behavior count, assertions, failures,
     blocker, and accepted commit.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed output at audit samples `2026-05-23T12:33:31Z` and
`2026-05-23T12:35:08Z`: no rows.

A later pre-commit handoff gate returned:

```text
3841151 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
3841151 claude  3802977  00:03   Rs   php tools/run-tests.php
```

No duplicate root harness was active at those samples, but no root run was
started because the tree was not stable enough for accepted aggregate evidence;
the later handoff sample confirmed duplicate-root prevention was active again.
Active repo worker/status processes persisted and the dirty tree remained broad.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af 'run-(team-watchdog|capacity-controller|dashboard-updater|evaluator|integrator|auditor)|scripts/run-tmux-agent\.sh port-|tools/run-tests\.php'
git log --oneline --decorate -n 20
git show --stat --oneline --decorate --no-renames -n 12
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. Latest samples
reported `1519` default status rows, `164` tracked changed files, and
`164 files changed, 46039 insertions(+), 2848 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and duplicate focused PHP loops first.
Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
