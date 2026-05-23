# Independent Audit - 2026-05-23T09:01:45Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status
files needed for alignment checks, recent Git history through initial audit
`HEAD` `702734a0`, a post-commit handoff after `HEAD` advanced to `987cea06`,
dirty-tree state, active process state, and the PHP process-launch surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - the portfolio still has no stable integration snapshot to
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:273`-`279`, `.tmux-team/prompts/*`,
     `.tmux-team/logs/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and the current dirty `lanes/*` files.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require capped
     supervision, small committed slices with passing tests, integration
     verification, cleanup, and visible current progress.
   - Evidence: `progress.md:25` still documents a two-worker-plus-auditor
     launch target, and `progress.md:31`-`42` still reports every lane as
     `stopped`. Process sampling found active team-watchdog,
     capacity-controller, dashboard-updater, evaluator, auditor, capacity, and
     lane-agent loops for LightningCSS, Quadrable, Syncthing, Gitoxide,
     esbuild, Difftastic, Dolt, markerPDF, Readability, libsqlite, Pandoc, and
     rclone.
   - Evidence: after the audit-only commit, another worker advanced `HEAD` to
     `987cea06`. The latest dirty-tree samples then showed `1111` default
     `git status --short` entries, `136` tracked changed files, and
     `136 files changed, 28871 insertions(+), 870 deletions(-)`.
   - Audit judgment: do not accept any dashboard, lane-status, manifest
     percentage, or root-test anecdote as the current portfolio baseline until
     active writers/status publishers are frozen and one snapshot is tested.

2. **Critical - a duplicate root run was explicitly disallowed by the required
   gate.**
   - Paths: `tools/run-tests.php`, `progress.md:273`-`279`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/quadrable/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the exact duplicate-root gate first observed active root PIDs
     `2541140` and `2541185`, which exited before owner sampling. A later gate
     observed active exact root PID `2542369`; owner evidence captured without
     inspecting process environments was:

     ```text
     2542369 claude 2510498 00:13 R php tools/run-tests.php
     ```

   - Evidence: lane statuses still carry incompatible root stories: some lanes
     cite green aggregate roots, others are pending behind active root PIDs,
     and Quadrable records a root attempt that failed outside its lane.
   - Audit judgment: root-test state must be collapsed to one repo-level
     record from a frozen snapshot; lane-local aggregate anecdotes should
     remain diagnostic only.

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
     `bda83c6b93d4`, while the inspected `HEAD` is `702734a0`.
   - Evidence: `porting.html:41`-`50` still combines benchmark source and
     denominator in one `Benchmark` column and combines PHP pass/fail with
     mapped tests in one `Mapped` column, instead of the separate fields
     required by `goal.md:45`.
   - Evidence: latest sampled manifest mapped counts disagree with the
     dashboard:
     Difftastic `217 / 583` vs dashboard `160 / 417`, Dolt `370 / 613` vs
     `242 / 613`, esbuild `199 / 2567` vs `164 / 2567`, Gitoxide
     `1692 / 2877` vs `1432 / 2877`, libsqlite `192 / 1454` vs `149 / 1454`,
     LightningCSS `978 / 3532` vs `773 / 3532`, markerPDF `190 / 246` vs
     `159 / 78`, Pandoc `541 / 2276` vs `426 / 2028`, rclone `390 / 2553`
     vs `291 / 327`, Readability `1334 / 1984` vs `1031 / 1984`, and
     Syncthing `283 / 658` vs `235 / 658`.

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
   - Evidence: `runnerStatus` mixes strings, objects, and absence: Gitoxide
     and Quadrable store long strings, markerPDF stores `not-executed`,
     Pandoc has no `runnerStatus`, and most other lanes store objects.
   - Evidence: mapped upstream units and PHP behavior-test units are different
     concepts but are repeatedly displayed together. Current examples:
     Dolt maps `370` upstream units but has `237` PHP behavior tests,
     markerPDF maps `190` upstream units but has `299` PHP behavior tests,
     Readability maps `1317` upstream units but has `125` PHP behavior tests,
     and esbuild manifest/lane-status counts already disagree at `199` vs
     `197` PHP-pass style values.

5. **Medium - `progress.md`, lane statuses, and active processes disagree about
   ownership, estimates, commits, and next work.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:273`-`279`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:44` requires current owner/session,
     next task per lane, and percentage estimates.
   - Evidence: `progress.md:31`-`42` still reports all lanes stopped with
     estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`,
     rclone `9%`, and esbuild `8%`. Current lane-status estimates report
     Gitoxide `98`, LightningCSS `76`, markerPDF `77`, rclone `91`, esbuild
     `64`, Pandoc `87`, Quadrable `96`, Syncthing `91`, libsqlite `93`,
     Difftastic `73`, Dolt `74`, and Readability `75`.
   - Evidence: every `latestCommit` sampled from lane statuses is prose,
     pending state, or dirty-batch text rather than a single accepted commit
     id.

6. **Medium - bounded, supplied, generated, and oracle-backed evidence remains
   too easy to over-credit as native parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:237`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:432`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:401`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:368`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` prohibit crediting bridge/shell/generated
     artifacts as native implementation progress and require hard gaps to be
     explicit.
   - Evidence: Gitoxide still lacks full Cargo parity and the latest SHA-256
     receive-pack path is bounded source/probe evidence. markerPDF records
     `runnerStatus: not-executed` while increasing supplied-document behavior
     counts. Pandoc remains a static inventory. rclone excludes live providers,
     mount, Docker serve, and `fstest/test_all`. Syncthing full `go test ./...`
     is still not run. Quadrable has strong upstream runner evidence, but much
     of the new breadth is raw LMDB/oracle fixture parity rather than a reason
     to treat all remaining sync-fuzzer or CLI behavior as complete.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed during this audit:

```text
sample 1: 2541140 php tools/run-tests.php
sample 1: 2541185 php tools/run-tests.php
owner evidence: both exited before owner sampling
sample 2: 2542369 php tools/run-tests.php
owner evidence: 2542369 claude 2510498 00:13 R php tools/run-tests.php
sample 3: no exact root-harness process after HEAD advanced to 987cea06
sample 4: 2555291 php tools/run-tests.php
owner evidence: 2555291 claude 2518279 00:10 R php tools/run-tests.php
```

No duplicate root run was started. A temporary clear gate was not enough for a
trustworthy aggregate run because `HEAD` had just moved again and the dirty
tree/status surface remained non-quiescent; the latest handoff gate found
another active exact root harness.

Latest dirty-tree samples:

```text
git status --short --untracked-files=no: 136 tracked entries
git status --short: 1111 entries
git diff --shortstat: 136 files changed, 28871 insertions(+), 870 deletions(-)
```

Recent history reviewed:

```text
987cea06 Port syncthing scanner sub-walk diagnostics
2345b8a6 Refresh independent audit status
702734a0 Refresh independent audit status
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
2452997 bash scripts/run-capacity-controller-loop.sh
2479222 bash scripts/run-dashboard-updater-loop.sh
2484030 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-lightningcss ...
2484432 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-quadrable ...
2492427 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-syncthing ...
2498161 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-gitoxide ...
2498921 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-esbuild ...
2509036 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-difftastic ...
2510481 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-markerpdf ...
2510542 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-readability ...
2512144 bash scripts/run-tmux-agent.sh port-libsqlite ...
2518216 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-pandoc ...
2537043 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt ...
2539394 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-rclone ...
2539497 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-auditor ...
```

## Next Intervention

Freeze active writers/status publishers and duplicate root loops first. Then
rerun the exact duplicate-root gate, capture one quiesced
`php tools/run-tests.php` run from a single accepted snapshot if the gate stays
empty, accept or reject dirty lane batches one lane at a time, collapse root
test status to one repo-level record, normalize manifest/status schemas, and
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from that same accepted snapshot.
