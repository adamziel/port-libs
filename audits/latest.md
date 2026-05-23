# Independent Audit - 2026-05-23T12:29:04Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for status alignment, recent Git history, dirty-tree state,
active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `694050635727` (`Refresh independent audit
status`). Recent history reviewed includes `69405063`, `0f1444c1`,
`efa4e0c2`, `f8bd46e4`, `09995598`, `a04f2c8b`, `5b6d5a84`, `957f8587`,
`f40a591b`, `e6182a8c`, `9f4f13f6`, and `e77d40c2`.

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
     all 12 lanes as `stopped`. Current process sampling still found 18 active
     repo worker/status processes, including team watchdog,
     capacity-controller, dashboard-updater, evaluator, auditor, integrator,
     lane-agent, capacity, and runner jobs.
   - Evidence: the latest dirty-tree sample reported `1505` default
     `git status --short --untracked-files=all` rows, `163` tracked changed
     files, and `163 files changed, 44945 insertions(+), 2835 deletions(-)`.
   - Audit judgment: the lane percentages, green lane-local claims, latest
     commit fields, and root-test anecdotes are still non-acceptance evidence
     until active writers/status publishers are frozen and one regenerated
     snapshot is validated.

2. **High - `porting.html` and `porting-summary.json` remain stale and do not
   satisfy the dashboard contract.**
   - Paths: `porting.html:32`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, and `porting-summary.json:2`-`8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     tracking of benchmark source, upstream denominator, mapped tests, PHP
     pass/fail, WordPress scenarios, phase, audit, current work, blocker, and
     commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `694050635727`.
   - Evidence: `porting.html:41`-`50` still exposes compact `Benchmark` and
     `Mapped` columns instead of separate benchmark source, upstream
     denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifests. Examples:
     Difftastic publishes `160 / 417` at `porting.html:54`, but the manifest
     is `263` mapped over `587...` at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Dolt publishes
     `242 / 613` at `porting.html:55`, but the manifest is `481 / 613...` at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Gitoxide publishes
     `1432 / 2877` at `porting.html:57`, but the manifest is `1918 / 2877` at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`15`; markerPDF publishes
     `159 / 78` at `porting.html:60`, but the manifest is `219 / 273` at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`; rclone publishes
     `291 / 327` at `porting.html:63`, but the manifest is `448 / 2553` at
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Readability publishes
     `1031 / 1984` at `porting.html:64`, but the manifest is `1564 / 1984` at
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`; and Syncthing
     publishes `235 / 658` at `porting.html:65`, but the manifest is
     `332 / 658` at `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15`.

3. **High - repo-wide PHP test records remain mutually non-comparable.**
   - Paths: `tools/run-tests.php`, all `lanes/*/lane-status.json`, and
     `progress.md`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, and `goal.md:49`
     require passing tests to be tied to accepted native slices and recorded
     honestly.
   - Evidence: current lane status records mix green root claims, red root
     claims, duplicate-gated pending root claims, and different suite sizes.
     Dolt records an earlier root pass followed by a final red Difftastic
     failure at `lanes/dolt/lane-status.json:10`-`12`. Esbuild records a root
     pass with `226` files and `26,286` assertions at
     `lanes/esbuild/lane-status.json:10`; Gitoxide records `226` files and
     `26,249` assertions at `lanes/gitoxide/lane-status.json:10`; libsqlite
     records `226` files and `26,209` assertions at
     `lanes/libsqlite/lane-status.json:10`; Readability records `225` files
     and `26,019` assertions at `lanes/readability/lane-status.json:10`;
     Difftastic, rclone, and Syncthing record duplicate-gated pending root
     states at their `lane-status.json:10`-`13` fields.
   - Audit judgment: the next accepted test result must be one quiesced
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
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`; Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing use numeric
     totals.
   - Evidence: `runnerStatus` is still non-normalized: objects in several
     manifests, long strings in Gitoxide and Quadrable at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18` and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, the string
     `not-executed` in markerPDF at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:273`, and no comparable
     Pandoc runner-status value in the denominator block.
   - Evidence: `latestCommit` remains dirty-batch prose or `pending` in many
     lane statuses, for example `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`, `lanes/libsqlite/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`, `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`, `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.

5. **Medium - upstream runner, oracle, generated-fixture, and CLI evidence is
   still over-mixed with native PHP progress.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:273`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require bridge/shell-out evidence not to
     count as native implementation progress, upstream tests as source of truth
     where possible, meaningful fixture parity, and hard gaps recorded
     explicitly.
   - Evidence: Dolt's denominator field combines static executable counts,
     focused runner passes, selected source references, and skipped suite
     classes in one prose value. Gitoxide's runner status combines focused
     upstream runner shards, static source inventory, PHP assertion counts, and
     root PHP anecdotes in one string. markerPDF still reports
     `runnerStatus: not-executed` while the same status surface includes CI
     archive, benchmark, package/runtime, supplied-document, OCR, and
     memory-profile planning evidence. Quadrable repeatedly folds C++ runner
     passes, LMDB dump/load oracles, command-output wrappers, and opt-in
     benchmark exclusions into one field. Rclone and Readability have useful
     bounded upstream evidence, but their visible fields still do not separate
     upstream runner pass, temporary oracle/CLI probe, native PHP behavior
     count, assertions, failures, blocker, and accepted commit.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed output at audit samples before the audit commit: no rows.

A post-commit handoff gate then returned:

```text
3806745 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
3806745 claude  3781729  00:17   Rs   php tools/run-tests.php
```

A later handoff gate returned another active root:

```text
3811600 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
3811600 claude  3811220  00:11   R    php tools/run-tests.php
```

No duplicate root harness was started by this audit. Before the post-commit
handoff root appeared, the tree was already not stable enough for accepted
aggregate evidence because active repo worker/status processes and a broad
dirty tree remained present.

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
reported `1505` default status rows, `163` tracked changed files, and
`163 files changed, 44945 insertions(+), 2835 deletions(-)`. No root run was
started because the stability gate failed.

## Next Intervention

Freeze active writers/status publishers and duplicate focused PHP loops first.
Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
