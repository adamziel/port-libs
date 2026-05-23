# Independent Audit - 2026-05-23T08:53:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane status files needed for alignment checks, recent Git history through
observed implementation `HEAD` `f5a0cbee`, dirty-tree state, active process
state, and the PHP process-launch surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:270`-`278`, `.tmux-team/prompts/*`,
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
     reports every lane as `stopped`. Process sampling during this audit still
     found team-watchdog, capacity-controller, dashboard-updater, evaluator,
     integrator, auditor, capacity, and lane-agent loops active for Dolt,
     markerPDF, Readability, Pandoc, libsqlite, rclone, LightningCSS,
     Quadrable, Syncthing, Gitoxide, and esbuild.
   - Evidence: `HEAD` moved during this audit from `5e3bb5ff` through
     `5b0672aa` to `f5a0cbee`. Dirty-tree samples moved while being read; the
     latest samples showed `1087` default `git status --short` entries, `125`
     tracked changed files, and `125 files changed, 27804 insertions(+), 841
     deletions(-)`.
   - Audit judgment: do not accept any dashboard, lane-status, manifest
     percentage, or root-test anecdote as the current portfolio baseline until
     active writers/status publishers are frozen and one snapshot is tested.

2. **Critical - root-test state remains untrustworthy and a duplicate root run
   was not allowed.**
   - Paths: `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`13`,
     `lanes/pandoc/lane-status.json:10`-`13`,
     `lanes/quadrable/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`, and
     `progress.md:273`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the required duplicate-root gate initially returned no exact
     root process, but later gates returned active root PIDs `2494536`,
     `2511963`, `2521537`, and `2522248`. Owner evidence captured without inspecting process
     environments: `2494536 claude 2452099 00:04 Rs php tools/run-tests.php`
     and `2511963 claude 2484081 00:25 Rs php tools/run-tests.php`; latest
     owner evidence was `2521537 claude 2492443 00:24 Rs php tools/run-tests.php`
     and `2522248 claude 2484500 00:21 Ss php tools/run-tests.php`. Another
     gate briefly returned focused lane PID `2513296 php tools/run-tests.php
     lanes/quadrable/tests`, which exited before owner sampling. The tree was
     still not stable enough because active writer loops and dirty lane batches
     persisted.
   - Evidence: lane statuses still carry incompatible root-test stories:
     Syncthing records one moving-aggregate red root followed by a later green
     rerun, Dolt records an aggregate pass with `199` files, rclone and Pandoc
     record duplicate-root/pending root gates, and other lanes record green
     root runs from different snapshots.
   - Audit judgment: collapse root-test state into one repo-level record from
     a frozen snapshot; lane-local root anecdotes should remain diagnostic only.

3. **High - `porting.html` and `porting-summary.json` are stale and still do
   not satisfy the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`8`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52` require current dashboard fields for benchmark source,
     upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while the observed implementation `HEAD` is `f5a0cbee`.
   - Evidence: `porting.html:41`-`50` still combines benchmark source and
     denominator in one `Benchmark` column and combines PHP pass/fail with
     mapped tests in one `Mapped` column, instead of the separate fields
     required by `goal.md:45`.
   - Evidence: dashboard rows are old against current manifests. Current
     manifest mapped counts include Difftastic `214 / 583`, Dolt `370 / 613`,
     esbuild `197 / 2567`, Gitoxide `1680 / 2877`, libsqlite `191 / 1454`,
     LightningCSS `953 / 3532`, markerPDF `189 / 245`, Pandoc `536 / 2276`,
     Quadrable `55 / 55`, rclone `389 / 2553`, Readability `1317 / 1984`,
     and Syncthing `282 / 658`; `porting.html:54`-`65` still publishes older
     values such as Difftastic `160 / 417`, markerPDF `159 / 78`, rclone
     `291 / 327`, and Syncthing `235 / 658`.

4. **High - manifest/status schemas still cannot support trustworthy portfolio
   percentages.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15`-`16`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`16`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     and `goal.md:45` require real denominators, meaningful fixture parity,
     explicit slices for huge suites, and dashboard separation of denominator,
     mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `runnerStatus` mixes structured objects and strings:
     Gitoxide and Quadrable use long string fields at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18` and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, markerPDF uses the
     string `not-executed` at `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:235`,
     while Difftastic, Dolt, esbuild, libsqlite, LightningCSS, rclone,
     Readability, and Syncthing use objects.
   - Evidence: mapped upstream units and PHP behavior-test units are different
     concepts but are repeatedly displayed together. Examples:
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15` has `mapped: 370` while
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:1644` has `phpBehaviorTests:
     235`; markerPDF has `mapped: 189` at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15` but
     `phpBehaviorTests: 298` at `:429`; Readability has `mapped: 1317` at
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15` but
     `phpBehaviorTests: 125` at `:400`.

5. **Medium - `progress.md`, lane statuses, and active processes disagree
   about ownership, estimates, commits, and next work.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:270`-`278`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:44` requires current owner/session,
     next task per lane, and percentage estimates.
   - Evidence: `progress.md:31`-`42` still reports all lanes stopped with
     estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`,
     rclone `9%`, and esbuild `8%`. Current lane-status estimates report
     Gitoxide `98`, LightningCSS `75`, markerPDF `77`, rclone `91`, esbuild
     `64`, Pandoc `87`, Quadrable `95`, Syncthing `91`, libsqlite `93`,
     Difftastic `72`, Dolt `74`, and Readability `75`.
   - Evidence: several `latestCommit` fields are prose or pending states
     rather than accepted commit ids, including Difftastic, Dolt, Gitoxide,
     LightningCSS, markerPDF, Pandoc, Quadrable, rclone, and Syncthing.

6. **Medium - bounded, supplied, skipped, generated, and oracle-backed
   evidence remains too easy to over-credit as native parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:235`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:427`-`428`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:396`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:366`-`378`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:714`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:37`,
     `goal.md:39`, and `goal.md:40` prohibit crediting bridge/shell/generated
     artifacts as native implementation progress and require hard gaps to be
     explicit.
   - Evidence: Gitoxide still says full workspace cargo parity was not
     executed and the latest SHA-256 receive-pack behavior is static
     source/test evidence plus native PHP checks rather than exact upstream
     runner parity. markerPDF records `runnerStatus: not-executed` and a
     large supplied-boundary current slice. Pandoc has a static file/artifact
     denominator, not Haskell runner parity. rclone excludes live provider and
     mount parity. Syncthing explicitly says full `go test ./...` was not run
     and many entries are bounded selected package slices.

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
sample 2: 2494536 php tools/run-tests.php
owner evidence: 2494536 claude 2452099 00:04 Rs php tools/run-tests.php
sample 3: 2511963 php tools/run-tests.php
owner evidence: 2511963 claude 2484081 00:25 Rs php tools/run-tests.php
sample 4: 2513296 php tools/run-tests.php lanes/quadrable/tests
owner evidence: process exited before owner sampling
sample 5: 2521537 php tools/run-tests.php
sample 5: 2522248 php tools/run-tests.php
owner evidence: 2521537 claude 2492443 00:24 Rs php tools/run-tests.php
owner evidence: 2522248 claude 2484500 00:21 Ss php tools/run-tests.php
```

No duplicate root run was started. The earlier clear gates were not enough for
a trustworthy aggregate run because active writers, status publishers, and
dirty lane batches were still moving; an intervening gate then found an active
root harness.

Latest dirty-tree samples:

```text
git status --short --untracked-files=no: 125 tracked entries
git status --short: 1087 entries
git diff --shortstat: 125 files changed, 27804 insertions(+), 841 deletions(-)
```

Recent history reviewed:

```text
f5a0cbee libsqlite grow table root on option replacement
5b0672aa Port esbuild static method decorator helpers
5e3bb5ff syncthing: skip sub walks below symlinks
70952f6e Refresh independent audit status
a0c76ade libsqlite record table split status
02e9846e libsqlite support table leaf split replacement
f79dce40 Record active root audit handoff
4167493e Record readability lane status
1491041c Port esbuild method decorator helper slice
c855e157 Advance readability Wikipedia fixture parity
0eecf963 syncthing: clean stale scanner temp files
```

Active process evidence included:

```text
2347911 bash scripts/run-team-watchdog.sh
2424048 bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
2436371 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt ...
2452081 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-markerpdf ...
2452220 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-readability ...
2452997 bash scripts/run-capacity-controller-loop.sh
2465340 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-pandoc ...
2477427 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-libsqlite ...
2479222 bash scripts/run-dashboard-updater-loop.sh
2479724 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-rclone ...
2484030 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-lightningcss ...
2484432 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-quadrable ...
2484946 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-auditor ...
2492427 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-syncthing ...
2492615 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-integrator ...
2498161 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-gitoxide ...
2498921 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-esbuild ...
```

## Recommended Intervention

Freeze active writers/status publishers and duplicate root loops first. Then
rerun the exact duplicate-root gate and capture one quiesced
`php tools/run-tests.php` run from a single accepted snapshot. Only after that
should the supervisor accept or reject dirty lane batches, collapse root-test
state to one repo-level record, normalize manifest/status schema fields, and
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from that same snapshot.
