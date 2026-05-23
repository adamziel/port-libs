# Independent Audit - 2026-05-23T08:06:51Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane-status summaries needed to check alignment, recent Git history through
`0d905966`, dirty-tree status, active process/test state, and PHP shell-out
surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - the repository still has no stable integration snapshot to
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:267`-`273`, `.tmux-team/prompts/*`,
     `.tmux-team/logs/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and the current dirty `lanes/*` files.
   - Requirement at risk: `goal.md:20`, `goal.md:44`, `goal.md:48`,
     `goal.md:49`, and `goal.md:52` require a practical concurrency cap,
     accurate owner/session state, deliberate integration, cleanup,
     repo-wide verification, and visible current progress.
   - Evidence: `progress.md:25` still documents a two-implementation-lane plus
     one-auditor target, while `progress.md:31`-`42` lists every lane as
     `stopped`.
   - Evidence: active process sampling found long-running coordination/writer
     loops, including `216170 claude ... run-team-watchdog.sh`,
     `788795 claude ... run-capacity-controller-loop.sh`,
     `1344437 claude ... run-capacity-controller-loop.sh`,
     `1984073 claude ... run-tmux-agent.sh port-auditor`,
     `2004747 claude ... run-tmux-agent.sh port-integrator`,
     `2018633`/`2018643` SQLite capacity agents, `2018654` rclone capacity,
     `2018666` esbuild capacity, `2018680` Syncthing capacity,
     `2018706` Rust upstream capacity, `2018749` artifact acceptance,
     `2018812` dashboard freshness gate,
     `2399239 claude ... run-dashboard-updater-loop.sh`, and
     `2424048 claude ... run-evaluator-loop.sh`.
   - Evidence: latest samples reported `968` default `git status --short`
     entries, `94` tracked changed files, and `94 files changed, 23112
     insertions(+), 663 deletions(-)` at `HEAD` `0d905966`.
   - Audit judgment: freeze active writers and status publishers before
     accepting any root run, dashboard, lane-status, manifest percentage, or
     progress estimate.

2. **High - `porting.html` and `porting-summary.json` are stale and still fail
   the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:16`, `porting-summary.json:33`,
     `porting-summary.json:50`, `porting-summary.json:67`,
     `porting-summary.json:84`, `porting-summary.json:101`,
     `porting-summary.json:118`, `porting-summary.json:135`,
     `porting-summary.json:152`, `porting-summary.json:169`,
     `porting-summary.json:186`, `porting-summary.json:203`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require
     current dashboard fields for benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while the
     reviewed `HEAD` is `0d905966`.
   - Evidence: `porting.html:41`-`50` still has compound `Benchmark` and
     `Mapped` columns instead of separate benchmark source, upstream
     denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifests: Difftastic
     `160 / 417` versus `205 / 559`; Dolt `242 / 613` versus `342 / 613`;
     esbuild `164 / 2567` versus `190 / 2567`; Gitoxide `1432 / 2877` versus
     `1639 / 2877`; libsqlite `149 / 1454` versus `185 / 1454`;
     LightningCSS `773 / 3532` versus `886 / 3532`; markerPDF `159 / 78`
     versus `182 / 241`; Pandoc `426 / 2028` versus `521 / 2276`; rclone
     `291 / 327` versus `373 / 2553`; Readability `1031 / 1984` versus
     `1264 / 1984`; and Syncthing `235 / 658` versus `278 / 658`.
     Quadrable's mapped count still agrees at `55 / 55`, but the dashboard PHP
     count is stale against the lane status `121`.
   - Audit judgment: the public dashboard is an old publish snapshot, not the
     current coordination surface.

3. **High - root-test state is contradictory and cannot be treated as the
   current repo result.**
   - Paths: `lanes/difftastic/lane-status.json:10`,
     `lanes/esbuild/lane-status.json:10`,
     `lanes/gitoxide/lane-status.json:10`,
     `lanes/libsqlite/lane-status.json:10`,
     `lanes/lightningcss/lane-status.json:10`,
     `lanes/markerpdf/lane-status.json:10`,
     `lanes/pandoc/lane-status.json:10`,
     `lanes/quadrable/lane-status.json:10`,
     `lanes/rclone/lane-status.json:10`,
     `lanes/readability/lane-status.json:5`,
     `lanes/readability/lane-status.json:10`, and
     `lanes/syncthing/lane-status.json:10`.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices with passing tests, verified integration,
     cleanup, and honest failure records.
   - Evidence: Difftastic, esbuild, Gitoxide, markerPDF, Pandoc, and Syncthing
     record green aggregate root runs with different file/assertion counts.
     Rclone records an aggregate root failure in Readability. Dolt, libsqlite,
     LightningCSS, Quadrable, and Readability record root verification as
     pending because duplicate-root gates found active root runners.
   - Evidence: this audit observed an exact root process
     `2017973 php tools/run-tests.php` with owner evidence
     `2017973 claude 1983296 00:09 Rs php tools/run-tests.php`; a later exact
     gate was clear, but active writers and `HEAD` movement still made a new
     root run untrustworthy.
   - Audit judgment: collapse root status to one repo-level integration record
     from a frozen tree, then regenerate lane statuses from that single record.

4. **High - manifest denominator, runner-status, and PHP-count schemas still
   cannot support trustworthy portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:230`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     and `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators, meaningful fixture
     parity, explicit slices for huge suites, and dashboard separation of
     denominator, mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` mixes objects, prose
     strings, null, and missing fields. Gitoxide line 18 also embeds a root
     PHP green run inside upstream runner status, which blends upstream parity
     and local PHP verification.
   - Evidence: manifest-level PHP behavior counts are inconsistent or absent:
     Dolt has `mapped: 342` and `phpBehaviorTests: 221`; markerPDF has
     `mapped: 182` and `phpBehaviorTests: 291`; rclone has both `mapped: 373`
     and `phpBehaviorTests: 373`; Readability has `mapped: 1264` and
     `phpBehaviorTests: 121`; several other manifests rely on lane-status
     prose instead of a direct manifest-level PHP pass/fail field.
   - Audit judgment: normalize the manifest/status schema before publishing
     average progress or comparing lane percentages.

5. **Medium - `progress.md` no longer reflects the active lane estimates or
   current audit state.**
   - Paths: `progress.md:31`-`42`, `progress.md:265`-`273`,
     and every `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:44` requires current owner/session, next
     task per lane, and percentage estimates.
   - Evidence: `progress.md:31`-`42` still reports all lanes as stopped with
     estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`,
     rclone `9%`, and esbuild `8%`. Current lane-status estimates report
     materially different values, including Gitoxide `98%`, LightningCSS
     `71%`, markerPDF `75%`, rclone `87%`, esbuild `61%`, Pandoc `82%`,
     Quadrable `92%`, and Syncthing `90%`.
   - Audit judgment: update `progress.md` from the same accepted snapshot as
     the dashboard; until then the lane table remains a stale handoff, not a
     coordination source of truth.

6. **Medium - bounded, supplied, skipped, generated, or oracle-backed evidence
   remains too easy to misread as native parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:236`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:230`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/rclone/lane-status.json:12`,
     and `lanes/syncthing/lane-status.json:10`-`12`.
   - Requirement at risk: `goal.md:30`, `goal.md:37`, `goal.md:39`, and
     `goal.md:40` require upstream tests as source of truth, reproducible
     generated artifacts, hard-feature blockers, and no credit for generated
     fixtures, bridge calls, or shell-outs as native implementation progress.
   - Evidence: Difftastic still lacks full Rust runner parity; Gitoxide relies
     on bounded focused cargo probes and static evidence for several major
     surfaces; markerPDF still records runner status as `not-executed` and
     leans on supplied document/model-output boundaries; rclone excludes live
     provider and mount parity; Syncthing has no full `go test ./...` parity
     and its newest Windows-specific upstream probe is skipped on this Linux
     host; Quadrable has strong C++ runner evidence but still carries large
     generated LMDB/raw cursor oracle fixture slices.
   - Audit judgment: keep these artifacts as evidence, but separate them from
     native implementation progress and aggregate percentages.

7. **Low - no lane PHP shell-out deliverable surfaced, but coordination
   tooling still shells out.**
   - Paths: `tools/generate-dashboard.php:183`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` prohibit wrappers around
     JS/Rust/Go/C binaries as the deliverable and disallow shell-outs from
     counting as native implementation progress.
   - Evidence: `rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'`
     found the actionable shell-out at `tools/generate-dashboard.php:183`;
     other matches were comments, strings, or regular PHP parsing code.
   - Audit judgment: no lane implementation shell-out surfaced in this audit;
     the dashboard shell-out is coordination-only and must not be counted as
     lane progress.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root check before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Result:

```text
2017973 php tools/run-tests.php
```

Owner evidence:

```text
2017973 claude 1983296 00:09 Rs php tools/run-tests.php
```

A later exact duplicate-root sample was clear, but no duplicate root run was
started. The tree was also not stable enough for a trustworthy aggregate run
because active writer/update loops were present, `HEAD` moved during the audit,
and the worktree remained a large dirty aggregate.

Latest dirty-tree samples:

```text
git status --short: 968 entries
git status --short --untracked-files=no: 94 entries
git diff --shortstat: 94 files changed, 23112 insertions(+), 663 deletions(-)
```

Active process evidence:

```text
216170 claude 2747 03:16:23 Ss+ bash /home/claude/port-libs/scripts/run-team-watchdog.sh
788795 claude 2747 02:12:55 Ss+ bash /home/claude/port-libs/scripts/run-capacity-controller-loop.sh
1344437 claude 2747 01:06:28 Ss+ bash scripts/run-capacity-controller-loop.sh
1984073 claude 1925250 07:04 S+ bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-auditor /home/claude/port-libs/.tmux-team/prompts/auditor.md /home/claude/port-libs/.tmux-team/logs/port-auditor-watchdog-20260523T075959Z.log
2004747 claude 1960304 04:58 S+ bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-integrator /home/claude/port-libs/.tmux-team/prompts/integrator.md /home/claude/port-libs/.tmux-team/logs/port-integrator-watchdog-20260523T080205Z.log
2018633 claude 2747 01:08 Ss+ bash scripts/run-tmux-agent.sh port-capacity-sqlite-tcl-24-20260523T080230Z .tmux-team/prompts/capacity-sqlite-tcl-24-20260523T080230Z.md .tmux-team/logs/port-capacity-sqlite-tcl-24-20260523T080230Z.log
2018643 claude 2747 01:08 Ss+ bash scripts/run-tmux-agent.sh port-capacity-sqlite-tcl-25-20260523T080230Z .tmux-team/prompts/capacity-sqlite-tcl-25-20260523T080230Z.md .tmux-team/logs/port-capacity-sqlite-tcl-25-20260523T080230Z.log
2018654 claude 2747 01:08 Ss+ bash scripts/run-tmux-agent.sh port-capacity-rclone-local-10-20260523T080230Z .tmux-team/prompts/capacity-rclone-local-10-20260523T080230Z.md .tmux-team/logs/port-capacity-rclone-local-10-20260523T080230Z.log
2018666 claude 2747 01:08 Ss+ bash scripts/run-tmux-agent.sh port-capacity-esbuild-go-11-20260523T080230Z .tmux-team/prompts/capacity-esbuild-go-11-20260523T080230Z.md .tmux-team/logs/port-capacity-esbuild-go-11-20260523T080230Z.log
2018680 claude 2747 01:08 Ss+ bash scripts/run-tmux-agent.sh port-capacity-syncthing-local-9-20260523T080230Z .tmux-team/prompts/capacity-syncthing-local-9-20260523T080230Z.md .tmux-team/logs/port-capacity-syncthing-local-9-20260523T080230Z.log
2018706 claude 2747 01:08 Ss+ bash scripts/run-tmux-agent.sh port-capacity-rust-upstream-17-20260523T080230Z .tmux-team/prompts/capacity-rust-upstream-17-20260523T080230Z.md .tmux-team/logs/port-capacity-rust-upstream-17-20260523T080230Z.log
2018749 claude 2747 01:08 Ss+ bash scripts/run-tmux-agent.sh port-artifact-acceptance-20260523T080230Z .tmux-team/prompts/artifact-acceptance-20260523T080230Z.md .tmux-team/logs/port-artifact-acceptance-20260523T080230Z.log
2018812 claude 2747 01:08 Ss+ bash scripts/run-tmux-agent.sh port-dashboard-freshness-gate-20260523T080230Z .tmux-team/prompts/dashboard-freshness-gate-20260523T080230Z.md .tmux-team/logs/port-dashboard-freshness-gate-20260523T080230Z.log
2399239 claude 1190494 06:15:11 S+ bash /home/claude/port-libs/scripts/run-dashboard-updater-loop.sh
2424048 claude 2747 06:11:14 Ss+ bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
```

## Recent Git History

Recent commits reviewed:

```text
0d905966 Record syncthing lane status
f0e5a196 Port syncthing scanner error cancellation
3f420eb9 difftastic map tree-sitter error ANSI styling
b7035442 Update esbuild lane status
47a382c4 Port esbuild namespace using slices
dd1724ff Refresh independent audit status
c9acf8b0 pandoc: map literate haskell lhs boundary
580e81a9 Record active root audit handoff
580a2a76 Refresh independent audit status
f4d7e836 Update syncthing lane status
6804a824 Port syncthing scanner Windows exec bits
fddbb567 difftastic map ansi syntax highlight controls
463189ff readability: map Atlas Obscura article body fixture
d91836c9 Refresh independent audit status
4f597d6f Refresh independent audit status
119d9916 Update Syncthing lane status commit pointer
25e8b8b8 Add Syncthing scanner progress events
3446f9fc pandoc record raw html list status
52ce98b3 pandoc map raw html list item slice
baddfe23 difftastic map guarded json display command
9764081e Refresh independent audit status
fcb1c75b Refresh independent audit status
68f04dbf Record syncthing normalization lane status
8a8bf56e Port syncthing scanner normalization slice
37c9d3bf readability: map additional Mozilla fixtures
9ef2cca7 Port libsqlite index leaf split insert planning
```
