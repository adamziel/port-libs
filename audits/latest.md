# Independent Audit - 2026-05-23T11:43:57Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
lane status files needed for alignment checks, recent Git history, dirty-tree
state, active process state, and the required root-test gate.

Initial sampled `HEAD`: `2960351d726c` (`Refresh independent audit status`).
During validation and commit handoff, implementation history moved through
`6c13ace2`, `07221e76`, `55605cb0`, `e77d40c2`, and `9f4f13f6` before this
audit commit. Recent history reviewed includes `9f4f13f6`, `e77d40c2`,
`55605cb0`, `07221e76`, `6c13ace2`, `2960351d`, `275ec497`, `3032d35b`,
`8b0f5af1`, `613b4eff`, `bdce6ef2`, `fabad4ea`, `2514e22e`, `3a42b2d8`,
`29f817eb`, `53588555`, `ae8aadcf`, `3c042169`, `5dddc1ed`, `b529b1ee`,
`c9254a88`, `0319eb91`, `64f06d33`, `3227da76`, `ab141f82`, `873879be`,
`5f2ae4bd`, `37f77f2e`, `64e9fcf1`, and `6c135b81`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - the repository still has no stable integration snapshot to
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`, `progress.md:304`,
     `porting.html:32`-`36`, `porting-summary.json:2`-`4`, all
     `lanes/*/lane-status.json`, and all `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:20` requires a supervised lane/auditor
     workflow with a practical concurrency cap; `goal.md:29` requires small
     reviewable slices with passing tests; `goal.md:44` requires current
     owner/session and next-task status; `goal.md:49` requires honest repo-wide
     test recording; `goal.md:52` requires visible current dashboard progress.
   - Evidence: `progress.md:25` still says the launch target is 2
     implementation lanes plus 1 auditor, and `progress.md:31`-`42` marks all
     12 lanes `stopped`. A process sample found 21 active watchdog, capacity,
     dashboard, evaluator, integrator, auditor, and lane-agent processes,
     including `port-markerpdf`, `port-difftastic`, `port-pandoc`,
     `port-quadrable`, `port-syncthing`, `port-libsqlite`, `port-gitoxide`,
     `port-readability`, `port-lightningcss`, `port-rclone`, `port-dolt`, and
     `port-esbuild`.
   - Evidence: dirty-tree samples reported `1394` default
     `git status --short --untracked-files=all` rows, `129` tracked changed
     files, and `129 files changed, 38858 insertions(+), 2784 deletions(-)`.
   - Audit judgment: root-test anecdotes, lane percentages, blockers, and
     latest-commit fields remain non-acceptance evidence until writers/status
     publishers are frozen and one regenerated snapshot is validated.

2. **High - `porting.html` and `porting-summary.json` are stale and do not meet
   the dashboard contract.**
   - Paths: `porting.html:32`-`65`, `porting-summary.json:2`-`212`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard tracking of benchmark source, upstream denominator, mapped
     tests, PHP pass/fail, WordPress scenarios, phase, audit, current work,
     blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`4` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4865c7edddaf7a680378f347eb4e6`, while implementation history
     has already advanced through `9f4f13f6` plus this audit commit.
   - Evidence: the dashboard table still collapses required fields:
     `porting.html:41`-`50` has `Benchmark` and `Mapped` columns instead of
     separate benchmark source, upstream denominator, mapped tests, and PHP
     pass/fail columns.
   - Evidence: published counts disagree with current manifests. Difftastic is
     published as `160 / 417` at `porting.html:54` and
     `porting-summary.json:15`-`18`, but the current manifest is `253 / 587`
     at `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`. Rclone is
     published as `291 / 327` at `porting.html:63` and
     `porting-summary.json:168`-`171`, while the current manifest is
     `429 / 2553` at `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
     markerPDF is published as `159 / 78` at `porting.html:60` and
     `porting-summary.json:117`-`120`, while the current manifest is
     `213 / 267` at `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`.

3. **High - root-test records are mutually contradictory, so there is no
   comparable baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:304`,
     `lanes/difftastic/lane-status.json:10`-`13`,
     `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`13`,
     `lanes/readability/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`, and
     `lanes/syncthing/lane-status.json:10`-`13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, and `goal.md:49`
     require passing tests to be tied to accepted native slices and recorded
     honestly.
   - Evidence: lane statuses currently claim incompatible aggregate states:
     Dolt records a green root run with `221` files and `25,451` assertions at
     `lanes/dolt/lane-status.json:10`; rclone and LightningCSS record green
     root runs with `221` files and `25,463` assertions at
     `lanes/rclone/lane-status.json:10` and
     `lanes/lightningcss/lane-status.json:10`; esbuild records a red aggregate
     run with `221` files, `25,455` assertions, and `2` failures in Difftastic
     fixture/example coverage at `lanes/esbuild/lane-status.json:10`-`12`;
     Difftastic, Readability, and Syncthing record root pending due active
     duplicate-root PIDs at `lanes/difftastic/lane-status.json:10`-`12`,
     `lanes/readability/lane-status.json:10`-`12`, and
     `lanes/syncthing/lane-status.json:10`-`12`.
   - Evidence: this audit's required exact duplicate-root gate returned no rows
     at the first sample, but I still did not run `php tools/run-tests.php`
     because the stability gate failed: active writer/status processes
     persisted and the dirty tree remained broad. Handoff gates later found
     active exact root harness PIDs `3469680`, `3472162`, and `3475008` owned
     by `claude`.
   - Audit judgment: the next accepted test result must be one quiesced root
     run from one accepted tree, not one of the current per-lane anecdotes.

4. **High - manifest/status schemas still cannot produce trustworthy portfolio
   math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `porting-summary.json`, and `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     `goal.md:44`, and `goal.md:45` require real upstream denominators,
     comparable mapped-test/PHP pass-fail counts, precise blockers, and a
     dashboard generated from those fields.
   - Evidence: denominator units remain mixed. Difftastic uses prose at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`, Pandoc uses prose at
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, Quadrable uses prose at
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, while rclone,
     libsqlite, LightningCSS, markerPDF, Readability, and Syncthing use
     numbers at their `:14` lines.
   - Evidence: runner-status shape is not normalized. Difftastic and rclone use
     objects at `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:300` and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:471`; Gitoxide and Quadrable use
     strings at `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18` and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`; markerPDF uses the
     string `not-executed` at `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:262`;
     Pandoc has no `runnerStatus` in the denominator object.
   - Evidence: `latestCommit` fields are not clean commit IDs in several lane
     statuses, including pending or prose values at
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/gitoxide/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`, and
     `lanes/readability/lane-status.json:13`.

5. **Medium - runner, oracle, and CLI evidence are still over-mixed with native
   PHP progress.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`-`21`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:262` and `:469`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`-`20`, and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:760`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require bridge/shell-out evidence not to count as native
     implementation progress, upstream tests as source of truth where possible,
     and hard gaps to be recorded explicitly.
   - Evidence: Dolt's denominator field mixes executable file counts, BATS case
     counts, Go test functions, direct CLI probes, runner-only refreshes, and
     native mapped behavior in one prose field. esbuild's status explicitly
     uses a cache-local upstream CLI oracle for the current slice and then
     records aggregate root failure in another lane. Gitoxide's runner status
     combines bounded upstream evidence, static inventories, native PHP counts,
     and a root PHP pass in one string. markerPDF maps `213 / 267` static
     behavior/reference units while the full upstream runner is
     `not-executed`.
   - Audit judgment: these can be useful as temporary oracle or static evidence,
     but they need separate typed fields for upstream denominator, focused
     upstream-runner pass, temporary oracle/CLI probe, native PHP behavior count,
     assertions, failures, and accepted commit.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed result at the first audit sample: no rows. I still did not start a root
run because the tree was not stable enough: active writer/status loops were
present, `progress.md` still reported all lanes stopped, and the dirty tree
remained broad.

Later handoff gates found active exact root harnesses:

```text
3469680 php tools/run-tests.php
3472162 php tools/run-tests.php
3475008 php tools/run-tests.php
```

Owner evidence:

```text
3469680 claude 3371098 00:27 Rs php tools/run-tests.php
3472162 claude 3382543 00:16 Rs php tools/run-tests.php
3475008 claude 3408212 00:09 Rs php tools/run-tests.php
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af 'run-(team-watchdog|capacity-controller|dashboard-updater|evaluator|integrator|auditor)|scripts/run-tmux-agent\.sh port-|tools/run-tests\.php'
git log --oneline --decorate -n 30
git show --stat --oneline --decorate --no-renames -n 5
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. Latest samples
reported `1394` default status rows, `129` tracked changed files, and
`129 files changed, 38858 insertions(+), 2784 deletions(-)`. The process sample
matched 21 active watchdog/agent/status/test-management processes. The exact
duplicate-root gate was clear at the first sample, but the stability gate
failed; later handoff gates found active root PIDs `3469680`, `3472162`, and
`3475008` owned by `claude`. A later gate matched transient focused-lane PID
`3476238 php tools/run-tests.php lanes`, which exited before owner sampling,
and the final exact gate was clear.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped,
PHP pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one quiesced
`php tools/run-tests.php` result.
