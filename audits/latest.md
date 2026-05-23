# Independent Audit - 2026-05-23T08:33:12Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane status files needed to check alignment, recent Git history through
pre-audit `HEAD` `002f1c95`, dirty-tree status, active process state, and PHP
shell-out surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless explicitly
temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:268`-`276`, `.tmux-team/prompts/*`,
     `.tmux-team/logs/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and the current dirty `lanes/*` files.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require capped
     supervision, small committed slices with passing tests, integration
     verification, cleanup, and visible current progress.
   - Evidence: `progress.md:25` says the launch target is two implementation
     lanes plus one auditor, while `progress.md:31`-`42` lists every lane as
     `stopped`.
   - Evidence: active process sampling found watchdog/controller/update loops
     and lane agents for Dolt runner, LightningCSS, libsqlite, Pandoc,
     Difftastic, Readability, esbuild, artifact acceptance, rclone, Gitoxide,
     markerPDF, Dolt, auditor, Syncthing, integrator, dashboard updater, and
     evaluator.
   - Evidence: `HEAD` moved during this audit from `74d55284` to
     `3a5cb66b`, `3d986983`, `4446edc9`, and then `002f1c95`. Dirty-tree
     samples moved from `100 files changed, 23863 insertions(+), 661
     deletions(-)` to `120 files changed, 24909 insertions(+), 739
     deletions(-)`, with `120` tracked changed files and `1050` default
     `git status --short` entries.
   - Audit judgment: do not accept any dashboard, lane-status, root-test, or
     manifest percentage as the current portfolio baseline until active
     writers/status publishers are frozen and one accepted snapshot is tested.

2. **Critical - root-test evidence is still contradictory and was not safe to
   refresh.**
   - Paths: `lanes/difftastic/lane-status.json:12`-`13`,
     `lanes/dolt/lane-status.json:12`-`13`,
     `lanes/esbuild/lane-status.json:12`-`13`,
     `lanes/gitoxide/lane-status.json:12`-`13`,
     `lanes/libsqlite/lane-status.json:12`-`13`,
     `lanes/lightningcss/lane-status.json:12`-`13`,
     `lanes/markerpdf/lane-status.json:12`-`13`,
     `lanes/pandoc/lane-status.json:12`-`13`,
     `lanes/quadrable/lane-status.json:12`-`13`,
     `lanes/rclone/lane-status.json:12`-`13`,
     `lanes/readability/lane-status.json:12`-`13`, and
     `lanes/syncthing/lane-status.json:12`-`13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the required duplicate-root gate was initially clear, then
     returned PID `2291638 php tools/run-tests.php lanes/esbuild/tests`.
     The process exited before `ps` owner sampling, so owner evidence was not
     recoverable; a later exact sample was clear. A handoff sanity sample then
     found PID `2350046 php tools/run-tests.php lanes/syncthing/tests`, with
     owner evidence `2350046 claude 2287669 00:09 Rs php tools/run-tests.php
     lanes/syncthing/tests`. I did not start a duplicate or replacement root
     run because the tree was non-quiescent.
   - Evidence: lane statuses currently mix incompatible root stories:
     libsqlite, rclone, esbuild, Dolt, Syncthing, and Difftastic claim green
     root evidence; Readability records a root failure in Dolt conflict tests;
     Pandoc records a red root run from LightningCSS and Syncthing failures;
     Gitoxide, markerPDF, LightningCSS, and Quadrable record pending or
     duplicate-gated root runs.
   - Audit judgment: collapse root-test state into one repo-level record from
     a frozen snapshot; lane-local root anecdotes should remain diagnostic
     only.

3. **High - `porting.html` and `porting-summary.json` are stale and still do
   not satisfy the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json`, and every `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`
     require current dashboard fields for benchmark source, upstream
     denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase,
     audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` publishes generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while
     reviewed `HEAD` moved through `002f1c95`.
   - Evidence: `porting.html:41`-`50` still has compound `Benchmark` and
     `Mapped` columns instead of separate benchmark source, upstream
     denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows are old compared with current manifests. Current
     mapped counts include Difftastic `211 / 583`, Dolt `356 / 613`,
     Gitoxide `1655 / 2877`, libsqlite `188 / 1454`, LightningCSS
     `938 / 3532`, markerPDF `185 / 244`, Pandoc `526 / 2276`, rclone
     `379 / 2553`, Readability `1298 / 1984`, and Syncthing `280 / 658`;
     `porting.html:54`-`65` still publishes older values such as Difftastic
     `160 / 417`, Gitoxide `1432 / 2877`, markerPDF `159 / 78`, rclone
     `291 / 327`, Readability `1031 / 1984`, and Syncthing `235 / 658`.
   - Audit judgment: the dashboard is a historical publish snapshot, not the
     current coordination source.

4. **High - manifest/status schemas still cannot support trustworthy
   portfolio percentages.**
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
     and `goal.md:45` require real denominators, meaningful parity evidence,
     explicit slices for huge suites, and dashboard separation of denominator,
     mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `runnerStatus` mixes objects, strings, and missing/null fields:
     Gitoxide, markerPDF, and Quadrable use strings; Pandoc has no structured
     `runnerStatus`; the rest use objects.
   - Evidence: manifest mapped counts and lane PHP counts are not the same
     concept but are repeatedly displayed as if they were comparable. Examples:
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15` has `mapped: 356` while
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:1622` has `phpBehaviorTests:
     232`; Readability has `mapped: 1298` at
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15` but
     `phpBehaviorTests: 123` at `:392`.
   - Audit judgment: normalize schema before publishing average progress or
     comparing lane percentages.

5. **Medium - `progress.md`, lane statuses, and active processes disagree
   about ownership, estimates, and commits.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:268`-`276`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:44` requires current owner/session,
     next task per lane, and percentage estimates.
   - Evidence: `progress.md:31`-`42` reports all lanes stopped with estimates
     such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, rclone
     `9%`, and esbuild `8%`. Current lane-status estimates report Gitoxide
     `98`, LightningCSS `73`, markerPDF `75`, rclone `89`, esbuild `62`,
     Pandoc `85`, Quadrable `94`, Syncthing `90`, libsqlite `91`,
     Difftastic `70`, Dolt `72`, and Readability `73`.
   - Evidence: several `latestCommit` fields are prose/pending states rather
     than accepted commit ids, including Gitoxide, Dolt, esbuild, markerPDF,
     Pandoc, Quadrable, rclone, Readability, and Syncthing.
   - Audit judgment: regenerate `progress.md` from the same accepted snapshot
     as the dashboard after writer freeze and root-test acceptance.

6. **Medium - bounded, supplied, skipped, generated, and oracle-backed
   evidence remains too easy to over-credit as native parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`-`21`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:419`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:658`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:386`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:897`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:37`, `goal.md:39`,
     and `goal.md:40` prohibit crediting bridge/shell/generated artifacts as
     native implementation progress and require hard gaps to be explicit.
   - Evidence: Gitoxide still excludes full workspace cargo parity and marks
     shell-backed external-driver execution as excluded; markerPDF stops at
     supplied model/output boundaries; Pandoc has no Haskell runner parity;
     Quadrable carries large LMDB/raw cursor oracle slices; rclone excludes
     live provider and mount parity; Readability has upstream JS runner
     oracles but only `123` native PHP behavior tests; Syncthing has bounded
     package runners, not full `go test ./...` parity.
   - Audit judgment: keep this evidence, but separate it from native
     implementation progress and aggregate percentages.

7. **Low - no lane PHP implementation shell-out surfaced, but coordination
   tooling still shells out.**
   - Paths: `tools/generate-dashboard.php:183`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` prohibit
     JS/Rust/Go/C wrappers as deliverables and disallow shell-outs from
     counting as native implementation progress.
   - Evidence:
     `rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process\(' lanes tools scripts --glob '*.php'`
     found only `tools/generate-dashboard.php:183`.
   - Audit judgment: the shell-out is coordination-only and should not be
     counted as lane progress.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed during this audit:

```text
initial sample: no exact root-harness process
later sample: 2291638 php tools/run-tests.php lanes/esbuild/tests
owner sample for 2291638: process exited before ps owner evidence could be collected
later sample: no exact root-harness process
handoff sample: 2350046 php tools/run-tests.php lanes/syncthing/tests
owner evidence: 2350046 claude 2287669 00:09 Rs php tools/run-tests.php lanes/syncthing/tests
```

No duplicate root run was started. Even when the exact gate was clear, the tree
was not stable enough for a trustworthy aggregate run because active writers,
status publishers, and dirty lane batches were still moving.

Latest dirty-tree samples:

```text
git status --short --untracked-files=no: 120 tracked entries
git status --short: 1050 entries
git diff --shortstat: 120 files changed, 24909 insertions(+), 739 deletions(-)
```

Recent history reviewed:

```text
002f1c95 libsqlite record implementation commit
4446edc9 Add Syncthing scanner resume checkpoints
651b34e3 libsqlite support multi-page wp_options replacement
74d55284 Refresh independent audit handoff
a0620911 Record audit root handoff
5147a889 Port esbuild decorator helper slice
015cebea Refresh independent audit status
fde2332a difftastic map parser syntax highlights
571fc383 Record syncthing scanner skip status
f9c992a6 Map syncthing Windows symlink scanner skip
```

Active process evidence included:

```text
216170 bash /home/claude/port-libs/scripts/run-team-watchdog.sh
788795 bash /home/claude/port-libs/scripts/run-capacity-controller-loop.sh
2101276 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt-runner ...
2110539 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-lightningcss ...
2110729 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-libsqlite ...
2111064 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-pandoc ...
2128990 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-difftastic ...
2199478 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-readability ...
2200044 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-esbuild ...
2236317 bash scripts/run-tmux-agent.sh port-artifact-acceptance-20260523T082718Z ...
2238445 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-rclone ...
2248335 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-gitoxide ...
2248475 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-markerpdf ...
2264481 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt ...
2264693 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-auditor ...
2287613 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-syncthing ...
2288104 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-integrator ...
2399239 bash /home/claude/port-libs/scripts/run-dashboard-updater-loop.sh
2424048 bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
```

## Next Intervention

Freeze active writers, status publishers, and duplicate root loops first. Then
rerun the exact duplicate-root gate and capture one quiesced
`php tools/run-tests.php` result from a single accepted snapshot. After that,
accept or reject dirty lane batches one lane at a time, collapse root-test
state to one repo-level record, normalize manifest/status schema fields, and
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from the same accepted snapshot.
