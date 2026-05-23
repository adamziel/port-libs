# Independent Audit - 2026-05-23T08:48:47Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane status files needed for alignment checks, recent Git history through
final observed `HEAD` `a0c76ade2ffc`, dirty-tree state, active process state,
and PHP shell-out surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless explicitly
temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:273`-`278`, `.tmux-team/prompts/*`,
     `.tmux-team/logs/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and the current dirty `lanes/*` files.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require capped
     supervision, small committed slices with passing tests, integration
     verification, cleanup, and visible current progress.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, while `progress.md:31`-`42`
     reports every lane as `stopped`. Process sampling found active
     team-watchdog, capacity-controller, dashboard-updater, evaluator,
     integrator, auditor, and lane agents for Gitoxide, Quadrable, libsqlite,
     LightningCSS, rclone, Difftastic, Syncthing, Dolt, esbuild, markerPDF,
     Readability, and Pandoc.
   - Evidence: `HEAD` moved during this audit from `f79dce40058b` to
     `a0c76ade2ffc`. The dirty tree moved from `105 files changed, 25406
     insertions(+), 667 deletions(-)` to `117 files changed, 26322
     insertions(+), 697 deletions(-)`, with `119` tracked changed files and
     `1073` default `git status --short` entries at the final sample.
   - Audit judgment: do not accept any dashboard, lane-status, root-test, or
     manifest percentage as the current portfolio baseline until active
     writers/status publishers are frozen and one snapshot is tested.

2. **Critical - root-test state remains untrustworthy even when the duplicate
   gate is momentarily clear.**
   - Paths: `lanes/dolt/lane-status.json`, `lanes/lightningcss/lane-status.json`,
     `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`, `lanes/gitoxide/lane-status.json`,
     `lanes/libsqlite/lane-status.json`, and `progress.md:273`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: `pgrep -af '^php tools/run-tests\.php( |$)'` returned no exact
     root process in earlier audit samples, but handoff gates found active
     root PIDs `2477932`, `2477945`, and later `2479573`. Latest owner
     evidence: `2479573 claude 2479572 00:19 R php tools/run-tests.php`. No
     duplicate run was started. Several lane statuses also record incompatible
     root stories: Dolt records an aggregate libsqlite failure,
     LightningCSS/rclone/Pandoc/Quadrable record duplicate-gated or pending
     root runs, while Gitoxide/libsqlite/markerPDF/Readability/Syncthing
     record green roots from different moving snapshots.
   - Audit judgment: collapse root-test state into one repo-level record from a
     frozen snapshot; lane-local root anecdotes should remain diagnostic only.

3. **High - `porting.html` and `porting-summary.json` are stale and still do
   not satisfy the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`8`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`
     require current dashboard fields for benchmark source, upstream
     denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase,
     audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while final observed `HEAD` is `a0c76ade2ffc`.
   - Evidence: `porting.html:41`-`50` still combines benchmark source and
     denominator in one `Benchmark` column and combines PHP pass/fail with
     mapped tests in one `Mapped` column, instead of the separate fields
     required by `goal.md:45`.
   - Evidence: dashboard rows are old against current manifests. Current
     manifest mapped counts include Difftastic `211 / 583`, Dolt `362 / 613`,
     esbuild `195 / 2567`, Gitoxide `1680 / 2877`, libsqlite `190 / 1454`,
     LightningCSS `953 / 3532`, markerPDF `186 / 245`, Pandoc `529 / 2276`,
     Quadrable `55 / 55`, rclone `382 / 2553`, Readability `1298 / 1984`,
     and Syncthing `281 / 658`; `porting.html:54`-`65` still publishes older
     values such as Difftastic `160 / 417`, markerPDF `159 / 78`, rclone
     `291 / 327`, and Syncthing `235 / 658`.

4. **High - manifest/status schemas still cannot support trustworthy portfolio
   percentages.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`21`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15`-`16`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     and `goal.md:45` require real denominators, meaningful fixture parity,
     explicit slices for huge suites, and dashboard separation of denominator,
     mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `runnerStatus` mixes structured objects, strings, and missing
     fields: Gitoxide, markerPDF, and Quadrable use strings; Pandoc has only a
     warning string; the rest use objects.
   - Evidence: mapped upstream units and PHP behavior-test units are different
     concepts but are repeatedly displayed together. Examples:
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15` has `mapped: 362` while
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:1645` has `phpBehaviorTests:
     235`; Readability has `mapped: 1298` at
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15` but
     `phpBehaviorTests: 124` at `:398`.

5. **Medium - `progress.md`, lane statuses, and active processes disagree
   about ownership, estimates, and commits.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:273`-`278`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:44` requires current owner/session,
     next task per lane, and percentage estimates.
   - Evidence: `progress.md:31`-`42` still reports all lanes stopped with
     estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`,
     rclone `9%`, and esbuild `8%`. Current lane-status estimates report
     Gitoxide `98`, LightningCSS `74`, markerPDF `76`, rclone `90`, esbuild
     `63`, Pandoc `86`, Quadrable `94`, Syncthing `91`, libsqlite `91`,
     Difftastic `71`, Dolt `73`, and Readability `74`.
   - Evidence: several `latestCommit` fields are prose/pending states rather
     than accepted commit ids, including Dolt, Gitoxide, LightningCSS,
     markerPDF, Pandoc, Quadrable, rclone, and Syncthing.

6. **Medium - bounded, supplied, skipped, generated, and oracle-backed
   evidence remains too easy to over-credit as native parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`-`21`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:235`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:421`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:663`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:392`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:901`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:37`, `goal.md:39`,
     and `goal.md:40` prohibit crediting bridge/shell/generated artifacts as
     native implementation progress and require hard gaps to be explicit.
   - Evidence: Gitoxide still excludes full workspace cargo parity and
     shell-backed external-driver execution; markerPDF stops at supplied
     model/output/debug/render/preview boundaries; Pandoc has no Haskell
     runner parity; Quadrable explicitly leaves full 500-trial sync-fuzzer
     probes outside the fast suite; rclone excludes live provider and mount
     parity; Syncthing is bounded package/static evidence rather than full
     `go test ./...` parity. Readability has strong upstream JS runner parity
     but only `124` native PHP behavior tests against `1984` upstream tests.

7. **Low - no lane PHP implementation shell-out surfaced, but coordination
   tooling still shells out.**
   - Path: `tools/generate-dashboard.php:183`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` prohibit
     JS/Rust/Go/C wrappers as deliverables and disallow shell-outs from
     counting as native implementation progress.
   - Evidence:
     `rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|exec\(' lanes tools scripts --glob '*.php'`
     found only the dashboard generator's `shell_exec()` among real PHP
     process-launch calls. The other hits were comments, strings, or regex/code
     examples.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed during this audit:

```text
sample 1: no exact root-harness process
sample 2: no exact root-harness process
final handoff sample: 2477932 php tools/run-tests.php
final handoff sample: 2477945 php tools/run-tests.php
owner evidence: 2477932 claude 2436569 00:20 Rs php tools/run-tests.php
owner evidence: 2477945 claude 2477944 00:18 S php tools/run-tests.php
latest handoff sample: 2479573 php tools/run-tests.php
owner evidence: 2479573 claude 2479572 00:19 R php tools/run-tests.php
```

No duplicate root run was started. The earlier clear gates were not enough for
a trustworthy aggregate run because active writers, status publishers, and dirty
lane batches were still moving; the final gate then found active root harnesses.

Latest dirty-tree samples:

```text
git status --short --untracked-files=no: 119 tracked entries
git status --short: 1073 entries
git diff --shortstat: 117 files changed, 26322 insertions(+), 697 deletions(-)
```

Recent history reviewed:

```text
a0c76ade libsqlite record table split status
02e9846e libsqlite support table leaf split replacement
f79dce40 Record active root audit handoff
4167493e Record readability lane status
1491041c Port esbuild method decorator helper slice
c855e157 Advance readability Wikipedia fixture parity
0eecf963 syncthing: clean stale scanner temp files
e00a2981 Refresh independent audit status
```

Active process evidence included:

```text
2101276 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt-runner ...
2248335 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-gitoxide ...
2305419 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-quadrable ...
2306809 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-libsqlite ...
2347911 bash scripts/run-team-watchdog.sh
2348057 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-lightningcss ...
2360922 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-rclone ...
2385902 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-difftastic ...
2399239 bash /home/claude/port-libs/scripts/run-dashboard-updater-loop.sh
2419126 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-syncthing ...
2424048 bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
2436371 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt ...
2436483 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-esbuild ...
2436748 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-auditor ...
2452081 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-markerpdf ...
2452220 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-readability ...
2452963 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-integrator ...
2452997 bash scripts/run-capacity-controller-loop.sh
2465340 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-pandoc ...
2476709 bash scripts/run-tmux-agent.sh port-capacity-dolt-bats-small-sql-misc-20260523T084525Z ...
2476717 bash scripts/run-tmux-agent.sh port-capacity-dolt-bats-filter-branch-local-20260523T084525Z ...
2476733 bash scripts/run-tmux-agent.sh port-artifact-acceptance-20260523T084525Z ...
2477427 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-libsqlite ...
```

## Recommended Intervention

Freeze active writers/status publishers and duplicate root loops first. Then
rerun the exact duplicate-root gate and capture one quiesced
`php tools/run-tests.php` run from a single accepted snapshot. Only after that
should the supervisor accept or reject dirty lane batches, collapse root-test
state to one repo-level record, normalize manifest/status schema fields, and
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from that same snapshot.
