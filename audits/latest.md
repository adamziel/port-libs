# Independent Audit - 2026-05-23T08:21:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane-status summaries needed to check alignment, recent Git history through
`fde2332a`, dirty-tree status, active process/test state, and PHP shell-out
surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - the repository still has no stable integration snapshot to
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:266`-`274`, `.tmux-team/prompts/*`,
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
   - Evidence: active process sampling found team-watchdog, two
     capacity-controller loops, dashboard-updater, evaluator, auditor,
     integrator, artifact/capacity jobs, and many lane agents running at once,
     including `port-dolt`, `port-readability`, `port-difftastic`,
     `port-markerpdf`, `port-rclone`, `port-libsqlite`, `port-quadrable`,
     `port-syncthing`, `port-lightningcss`, `port-pandoc`, `port-gitoxide`,
     and `port-esbuild`.
   - Evidence: latest samples at `HEAD` `fde2332a` reported `1002`
     default `git status --short` entries, `101` tracked changed files, and
     `101 files changed, 23127 insertions(+), 776 deletions(-)`.
   - Audit judgment: freeze active writers and status publishers before
     accepting any root run, dashboard, lane-status, manifest percentage, or
     progress estimate.

2. **Critical - root-test state remains unaccepted and cannot be treated as
   the current repo result.**
   - Paths: `lanes/difftastic/lane-status.json:10`,
     `lanes/dolt/lane-status.json:10`, `lanes/esbuild/lane-status.json:10`,
     `lanes/gitoxide/lane-status.json:10`, `lanes/libsqlite/lane-status.json:10`,
     `lanes/lightningcss/lane-status.json:10`,
     `lanes/markerpdf/lane-status.json:10`,
     `lanes/pandoc/lane-status.json:10`,
     `lanes/quadrable/lane-status.json:10`,
     `lanes/rclone/lane-status.json:10`,
     `lanes/readability/lane-status.json:10`, and
     `lanes/syncthing/lane-status.json:10`.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices with passing tests, verified integration,
     cleanup, and honest failure records.
   - Evidence: the latest required duplicate-root gate returned no active exact
     `php tools/run-tests.php` process, but no root run was started because
     `HEAD` had moved repeatedly during this audit and active writer/update
     loops were still present.
   - Evidence: lane statuses mix green aggregate anecdotes with pending or
     duplicate-gated root states: examples include Difftastic `197` files,
     Gitoxide `198` files, LightningCSS/libsqlite `197` files, Pandoc `198`
     files, Rclone/Dolt `198` files, and libsqlite now records a red root run
     at `198` files with `1` failure, while esbuild, markerPDF, Quadrable, and
     Readability record active root PIDs instead of accepted root verification.
   - Audit judgment: do not start a root run until active writers are frozen.
     Collapse root status to one repo-level record from a frozen tree, then
     regenerate lane statuses from that single record.

3. **High - `porting.html` and `porting-summary.json` are stale and still fail
   the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:3`-`8`, `porting-summary.json:14`-`205`, and
     every `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require
     current dashboard fields for benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while the
     reviewed `HEAD` is `fde2332a`. Both `porting.html` and
     `porting-summary.json` are also dirty in the worktree.
   - Evidence: `porting.html:41`-`50` still has compound `Benchmark` and
     `Mapped` columns instead of separate benchmark source, upstream
     denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifests: Difftastic
     `160 / 417` versus `208 / 583`; Dolt `242 / 613` versus `348 / 613`;
     esbuild `164 / 2567` versus `193 / 2567`; Gitoxide `1432 / 2877` versus
     `1649 / 2877`; libsqlite `149 / 1454` versus `187 / 1454`;
     LightningCSS `773 / 3532` versus `890 / 3532`; markerPDF `159 / 78`
     versus `184 / 243`; Pandoc `426 / 2028` versus `523 / 2276`; rclone
     `291 / 327` versus `377 / 2553`; Readability `1031 / 1984` versus
     `1279 / 1984`; and Syncthing `235 / 658` versus `279 / 658`.
     Quadrable's mapped count still agrees at `55 / 55`, but dashboard PHP
     count `108` is stale against lane status `122`.
   - Audit judgment: the public dashboard is an old publish snapshot, not the
     current coordination surface.

4. **High - manifest denominator, runner-status, and PHP-count schemas still
   cannot support trustworthy portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:1265`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:232`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:424`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:380`-`386`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators, meaningful fixture
     parity, explicit slices for huge suites, and dashboard separation of
     denominator, mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` mixes objects, prose
     strings, null, and missing fields. Gitoxide and Quadrable use long strings;
     markerPDF uses `"not-executed"`; Pandoc has no structured runner status.
   - Evidence: manifest-level PHP behavior counts are inconsistent or absent:
     Dolt has `mapped: 348` and `phpBehaviorTests: 221`; markerPDF has
     `mapped: 184` and `phpBehaviorTests: 292`; rclone has both `mapped: 373`
     and `phpBehaviorTests: 373`; Readability warning text says `122` local
     behavior tests while `phpBehaviorTests` is `121`; LightningCSS has
     `mapped: 890` while its warning says native PHP maps `886` checks.
   - Audit judgment: normalize the manifest/status schema before publishing
     average progress or comparing lane percentages.

5. **Medium - `progress.md` and lane statuses disagree about current ownership,
   estimates, and commits.**
   - Paths: `progress.md:31`-`42`, `progress.md:266`-`274`, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:44` requires current owner/session, next
     task per lane, and percentage estimates.
   - Evidence: `progress.md:31`-`42` still reports all lanes as stopped with
     estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`,
     rclone `9%`, and esbuild `8%`. Current lane statuses report materially
     different values: Gitoxide `98`, LightningCSS `73`, markerPDF `75`,
     rclone `89`, esbuild `62`, Pandoc `84`, Quadrable `93`, Syncthing `90`,
     libsqlite `90`, Difftastic `70`, Dolt `71`, and Readability `73`.
   - Evidence: several `latestCommit` fields are not actionable commit ids:
     `lanes/gitoxide/lane-status.json:13` says `eaccd414 - current repository
     HEAD` even though current `HEAD` is `fde2332a`; rclone, Readability,
     Quadrable, libsqlite, markerPDF, and Dolt explicitly describe uncommitted
     or pending lane batches.
   - Audit judgment: update `progress.md` from the same accepted snapshot as
     the dashboard; until then the lane table remains a stale handoff, not a
     coordination source of truth.

6. **Medium - bounded, supplied, skipped, generated, or oracle-backed evidence
   remains too easy to misread as native parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:445`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:20`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:417`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:648`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:897`.
   - Requirement at risk: `goal.md:30`, `goal.md:37`, `goal.md:39`, and
     `goal.md:40` require upstream tests as source of truth, reproducible
     generated artifacts, hard-feature blockers, and no credit for generated
     fixtures, bridge calls, or shell-outs as native implementation progress.
   - Evidence: Difftastic still lacks full Rust runner parity; Gitoxide relies
     on bounded focused cargo probes and static evidence for several major
     surfaces; markerPDF still stops at supplied model/output boundaries;
     Pandoc has no upstream Haskell runner parity; rclone excludes live
     provider and mount parity; Syncthing has no full `go test ./...` parity;
     Quadrable has strong C++ runner evidence but still carries large generated
     LMDB/raw cursor oracle fixture slices and an intentionally skipped
     500-trial sync-fuzzer fast-suite boundary.
   - Audit judgment: keep these artifacts as evidence, but separate them from
     native implementation progress and aggregate percentages.

7. **Low - no lane PHP shell-out deliverable surfaced, but coordination tooling
   still shells out.**
   - Paths: `tools/generate-dashboard.php:183`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` prohibit wrappers around
     JS/Rust/Go/C binaries as the deliverable and disallow shell-outs from
     counting as native implementation progress.
   - Evidence: `rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'`
     found the actionable shell-out at `tools/generate-dashboard.php:183`;
     no lane PHP implementation shell-out surfaced in this audit.
   - Audit judgment: the dashboard shell-out is coordination-only and must not
     be counted as lane progress.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root check before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Result:

```text
(no output)
```

Owner evidence:

```text
not applicable; no exact root process was returned by pgrep
```

No root run was started. Although the latest duplicate-root gate was clear, the
tree was not stable enough for a trustworthy aggregate run because active
writer/update loops were present, `HEAD` moved repeatedly during the audit, and
the worktree remained a large dirty aggregate.

Latest dirty-tree samples:

```text
git status --short: 1002 entries
git status --short --untracked-files=no: 101 entries
git diff --shortstat: 101 files changed, 23127 insertions(+), 776 deletions(-)
```

Active process evidence:

```text
216170 bash /home/claude/port-libs/scripts/run-team-watchdog.sh
788795 bash /home/claude/port-libs/scripts/run-capacity-controller-loop.sh
1344437 bash scripts/run-capacity-controller-loop.sh
2004209 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-readability ...
2009697 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-difftastic ...
2083822 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-esbuild ...
2087818 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-auditor ...
2101079 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-markerpdf ...
2101276 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt-runner ...
2105085 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-rclone ...
2105124 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt ...
2110510 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-gitoxide ...
2110539 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-lightningcss ...
2110729 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-libsqlite ...
2111064 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-pandoc ...
2111247 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-quadrable ...
2111390 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-syncthing ...
2112503 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-capacity-sqlite-tcl-26-20260523T081900Z ...
2112535 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-capacity-sqlite-tcl-27-20260523T081900Z ...
2112552 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-capacity-sqlite-tcl-28-20260523T081900Z ...
2112576 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-capacity-rclone-local-11-20260523T081900Z ...
2112628 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-capacity-syncthing-local-10-20260523T081900Z ...
2399239 bash /home/claude/port-libs/scripts/run-dashboard-updater-loop.sh
2424048 bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
```

## Recent Git History

Recent commits reviewed:

```text
fde2332a difftastic map parser syntax highlights
571fc383 Record syncthing scanner skip status
f9c992a6 Map syncthing Windows symlink scanner skip
eaccd414 Record cycling root handoff
683a6879 Update audit handoff after moving root
5ac25c1e Record audit handoff refresh
0d05da47 Record pandoc backslash slice status
dabc8d3a Refresh independent audit status
548abf86 Port pandoc backslash escape markdown slice
2a2e891d Record lightningcss keyframes status
a0d51832 Port lightningcss keyframes minifier slice
8e9501c4 Advance libsqlite index split planning
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
```
