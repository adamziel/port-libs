# Independent Audit - 2026-05-23T08:26:19Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane-status summaries needed to check alignment, recent Git history through
`a0620911`, dirty-tree status, active process/test state, and PHP shell-out
surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - the repository still has no stable integration snapshot to
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:267`-`275`, `.tmux-team/prompts/*`,
     `.tmux-team/logs/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and the current dirty `lanes/*` files.
   - Requirement at risk: `goal.md:20`, `goal.md:29`, `goal.md:48`,
     `goal.md:49`, and `goal.md:52` require a practical concurrency cap,
     reviewable committed slices, verified integration, cleanup, repo-wide
     verification, and visible current progress.
   - Evidence: `progress.md:25` documents a two-implementation-lane plus
     one-auditor launch target, while `progress.md:31`-`42` lists every lane
     as `stopped`.
   - Evidence: active process sampling found the team watchdog, two capacity
     controllers, dashboard updater, evaluator, integrator, auditor, capacity
     jobs, and many lane agents running at once, including markerPDF, Dolt,
     Rclone, Gitoxide, LightningCSS, libsqlite, Pandoc, Quadrable, Syncthing,
     Difftastic, Readability, and esbuild sessions.
   - Evidence: latest samples at `HEAD` `a0620911` reported `1020` default
     `git status --short` entries, `102` tracked changed files, and
     `102 files changed, 23840 insertions(+), 714 deletions(-)`.
   - Audit judgment: freeze active writers and status publishers before
     accepting any root run, dashboard, lane-status, manifest percentage, or
     progress estimate.

2. **Critical - root-test state remains unaccepted, and a duplicate root run
   was active during this audit.**
   - Paths: `lanes/difftastic/lane-status.json:10`,
     `lanes/dolt/lane-status.json:10`,
     `lanes/esbuild/lane-status.json:10`,
     `lanes/gitoxide/lane-status.json:10`,
     `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`12`,
     `lanes/markerpdf/lane-status.json:10`-`12`,
     `lanes/pandoc/lane-status.json:10`-`12`,
     `lanes/quadrable/lane-status.json:10`-`12`,
     `lanes/rclone/lane-status.json:10`-`12`,
     `lanes/readability/lane-status.json:5`, `:10`-`:12`, and
     `lanes/syncthing/lane-status.json:10`-`12`.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require passing tests on reviewable slices, integration verification,
     cleanup, and honest failure records.
   - Evidence: the required duplicate-root gate returned active exact root PID
     `2207148 php tools/run-tests.php`; owner evidence was
     `2207148 claude 2105154 00:25 Rs php tools/run-tests.php`. I did not
     start another root harness.
   - Evidence: lane statuses mix incompatible root states. Esbuild, Gitoxide,
     Difftastic, Dolt, and Syncthing record green aggregate runs; libsqlite
     records a red root run with `198` files, `21844` assertions, and `1`
     failure; Readability records a red root run with `6` Dolt failures; and
     markerPDF, LightningCSS, Pandoc, Quadrable, and Rclone record pending,
     duplicate-gated, or concurrent-root anecdotes.
   - Evidence: a later exact duplicate-root sample was clear, but the tree was
     still not stable enough for a trustworthy aggregate run because active
     writer/status loops and the dirty aggregate persisted.
   - Audit judgment: collapse root status to one repo-level record from a
     frozen tree, then regenerate lane statuses from that single record.

3. **High - `porting.html` and `porting-summary.json` are stale and still do
   not meet the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`8`, `porting-summary.json:12`-`212`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require
     current dashboard fields for benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`5`
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while reviewed `HEAD` is `a0620911`.
   - Evidence: `porting.html:41`-`50` still has compound `Benchmark` and
     `Mapped` columns instead of separate benchmark source, upstream
     denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifests: Difftastic
     `160 / 417` versus `208 / 583`; Dolt `242 / 613` versus `348 / 613`;
     esbuild `164 / 2567` versus `193 / 2567`; Gitoxide `1432 / 2877` versus
     `1649 / 2877`; libsqlite `149 / 1454` versus `187 / 1454`;
     LightningCSS `773 / 3532` versus `890 / 3532`; markerPDF `159 / 78`
     versus `185 / 244`; Pandoc `426 / 2028` versus `523 / 2276`; rclone
     `291 / 327` versus `379 / 2553`; Readability `1031 / 1984` versus
     `1279 / 1984`; and Syncthing `235 / 658` versus `279 / 658`.
     Quadrable's mapped count still agrees at `55 / 55`, but dashboard PHP
     count `108` is stale against lane status `122`.
   - Audit judgment: the public dashboard is an old publish snapshot, not the
     current coordination surface.

4. **High - manifest/status schemas still cannot support trustworthy
   portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`20`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15`-`16`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:658`-`665`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:386`-`392`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators, meaningful fixture
     parity, explicit slices for huge suites, and dashboard separation of
     denominator, mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `runnerStatus` mixes objects, strings, and missing fields.
     Gitoxide, markerPDF, and Quadrable use strings; Pandoc has no structured
     `runnerStatus` and carries the runner explanation in `warning`.
   - Evidence: manifest-level PHP behavior counts are inconsistent or absent.
     markerPDF's manifest says `total: 244`, `mapped: 185`, and
     `phpBehaviorTests: 293`, while `lanes/markerpdf/lane-status.json:5`-`6`
     still says denominator `243`, mapped `184`, and PHP pass `292`.
     esbuild's warning still says native PHP maps `192` focused tests at
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:173`, while
     `phpBehaviorTests` is `193` at `:179`.
   - Audit judgment: normalize manifest/status schema before publishing
     average progress or comparing lane percentages.

5. **Medium - `progress.md` and lane statuses disagree about current
   ownership, estimates, and commits.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:267`-`275`, and `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:44` requires current owner/session, next
     task per lane, and percentage estimates.
   - Evidence: `progress.md:31`-`42` reports all lanes stopped with estimates
     such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, rclone `9%`,
     and esbuild `8%`. Current lane statuses report materially different
     estimates: Gitoxide `98`, LightningCSS `73`, markerPDF `75`, rclone `89`,
     esbuild `62`, Pandoc `84`, Quadrable `93`, Syncthing `90`, libsqlite
     `90`, Difftastic `70`, Dolt `71`, and Readability `73`.
   - Evidence: several `latestCommit` fields are prose or pending states, not
     accepted commit ids: examples include `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`, `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`, `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`, and
     `lanes/readability/lane-status.json:13`.
   - Audit judgment: update `progress.md` from the same accepted snapshot as
     the dashboard; until then the lane table remains a stale handoff.

6. **Medium - bounded, supplied, skipped, generated, or oracle-backed evidence
   remains too easy to misread as native parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:445`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:20`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:419`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:658`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:897`.
   - Requirement at risk: `goal.md:30`, `goal.md:37`, `goal.md:39`, and
     `goal.md:40` require upstream tests as source of truth, reproducible
     generated artifacts, hard-feature blockers, and no credit for generated
     fixtures, bridge calls, or shell-outs as native implementation progress.
   - Evidence: Difftastic still lacks full Rust runner parity; Gitoxide relies
     on bounded focused cargo probes and static evidence for large surfaces;
     markerPDF stops at supplied model/output boundaries; Pandoc has no
     Haskell runner parity; rclone excludes live provider and mount parity;
     Syncthing has no full `go test ./...` parity; Quadrable has strong C++
     runner evidence but still carries large generated LMDB/raw cursor oracle
     fixture slices and an intentionally skipped 500-trial sync-fuzzer
     boundary.
   - Audit judgment: keep these artifacts as evidence, but separate them from
     native implementation progress and aggregate percentages.

7. **Low - no lane PHP shell-out deliverable surfaced, but coordination
   tooling still shells out.**
   - Paths: `tools/generate-dashboard.php:183`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` prohibit wrappers around
     JS/Rust/Go/C binaries as the deliverable and disallow shell-outs from
     counting as native implementation progress.
   - Evidence:
     `rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process\(' lanes tools scripts --glob '*.php'`
     found the actionable shell-out at `tools/generate-dashboard.php:183`; no
     lane PHP implementation shell-out surfaced in this audit.
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
2207148 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
2207148 claude  2105154  00:25   Rs   php tools/run-tests.php
```

No duplicate root run was started. A later exact duplicate-root sample was
clear, but the tree was still not stable enough for a trustworthy aggregate run
because active writer/status loops and the dirty aggregate persisted.

Latest dirty-tree samples:

```text
git status --short: 1020 entries
git status --short --untracked-files=no: 102 entries
git diff --shortstat: 102 files changed, 23840 insertions(+), 714 deletions(-)
```

Recent history reviewed:

```text
a0620911 Record audit root handoff
5147a889 Port esbuild decorator helper slice
015cebea Refresh independent audit status
fde2332a difftastic map parser syntax highlights
571fc383 Record syncthing scanner skip status
f9c992a6 Map syncthing Windows symlink scanner skip
eaccd414 Record cycling root handoff
683a6879 Update audit handoff after moving root
```

Active process evidence included watchdog/controller/update loops and lane
agents:

```text
216170 bash /home/claude/port-libs/scripts/run-team-watchdog.sh
788795 bash /home/claude/port-libs/scripts/run-capacity-controller-loop.sh
1344437 bash scripts/run-capacity-controller-loop.sh
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
2175480 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-auditor ...
2199478 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-readability ...
2200044 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-esbuild ...
2200346 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-integrator ...
2399239 bash /home/claude/port-libs/scripts/run-dashboard-updater-loop.sh
2424048 bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
```

## Next Intervention

Freeze active writers/status publishers and duplicate root loops first. Then
rerun the exact duplicate-root gate and capture one quiesced
`php tools/run-tests.php` result from a single accepted snapshot. Only after
that should the supervisor accept or reject dirty lane batches one lane at a
time, normalize manifest/status schema fields, collapse root-test state to one
repo-level record, and regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the same accepted snapshot.
