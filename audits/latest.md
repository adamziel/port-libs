# Independent Audit - 2026-05-23T09:11:43Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status
files needed for alignment checks, recent Git history through `HEAD`
`75bddbff6f57`, dirty-tree state, active process state, and the required PHP
test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - the portfolio still has no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:274`-`280`, `.tmux-team/prompts/*`,
     `.tmux-team/logs/*`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and the current dirty `lanes/*` files.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require capped
     supervision, small committed slices with passing tests, integration
     verification, cleanup, and visible current progress.
   - Evidence: `progress.md:25` still documents a two-worker-plus-auditor
     target and `progress.md:31`-`42` still reports every lane as `stopped`.
     Process sampling found active capacity, dashboard, evaluator, auditor,
     and lane-agent loops for the portfolio; the broad process query returned
     26 matches including the sampler line.
   - Evidence: the latest samples show `1140` `git status --short` entries,
     `144` tracked changed files, and `144 files changed, 30468 insertions(+),
     1090 deletions(-)`.
   - Audit judgment: do not accept any dashboard, manifest percentage,
     lane-status root-test anecdote, or current `latestCommit` field as a
     portfolio baseline until active writers/status publishers are frozen and
     one snapshot is tested.

2. **Critical - repo-wide test status is contradictory and was not rerun from this audit.**
   - Paths: `tools/run-tests.php`, `progress.md:267`, `progress.md:274`,
     `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/libsqlite/lane-status.json`, `lanes/quadrable/lane-status.json`,
     `lanes/rclone/lane-status.json`, and `lanes/readability/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the required pre-run gate
     `pgrep -af '^php tools/run-tests\.php( |$)'` was briefly clear earlier,
     but the final gate returned active root PIDs `2611443` and `2611450`.
     Owner evidence captured without inspecting environments:
     `2611443 claude 2611442 00:13 R php tools/run-tests.php` and
     `2611450 claude 2569257 00:12 Ss php tools/run-tests.php`. A
     post-commit handoff sample later found active PID `2612277` with owner
     evidence `2612277 claude 2569759 00:21 Rs php tools/run-tests.php`. I did
     not start a duplicate root run.
   - Evidence: lane statuses now mix green root runs, pending duplicate-root
     gates, and red aggregate anecdotes. Examples: Difftastic records a
     completed aggregate run with 3 failures outside the lane; Quadrable records
     a root rerun with 14 failures in other lanes; libsqlite, markerPDF,
     rclone, Readability, Pandoc, LightningCSS, and Syncthing record green
     aggregate runs from their own lane turns.
   - Audit judgment: root-test state must be collapsed to one repo-level
     record from a frozen snapshot. Lane-local aggregate anecdotes are
     diagnostic only.

3. **High - `porting.html` and `porting-summary.json` are stale and still miss the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`8`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`
     require current dashboard fields for benchmark source, upstream
     denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase,
     audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while the inspected `HEAD` is `75bddbff6f57`.
   - Evidence: `porting.html:43` combines benchmark source and denominator in
     one column, and `porting.html:44` combines PHP pass/fail with mapped tests
     in one column. `goal.md:45` asks for separate upstream denominator, mapped
     tests, and PHP pass/fail columns.
   - Evidence: current manifest mapped counts disagree with the dashboard for
     nearly every lane: Difftastic `217 / 583` vs `160 / 417`, Dolt `377 / 613`
     vs `242 / 613`, esbuild `199 / 2567` vs `164 / 2567`, Gitoxide
     `1692 / 2877` vs `1432 / 2877`, libsqlite `192 / 1454` vs `149 / 1454`,
     LightningCSS `984 / 3532` vs `773 / 3532`, markerPDF `190 / 246` vs
     `159 / 78`, Pandoc `541 / 2276` vs `426 / 2028`, rclone `390 / 2553` vs
     `291 / 327`, Readability `1334 / 1984` vs `1031 / 1984`, and Syncthing
     `283 / 658` vs `235 / 658`. Quadrable still maps `55 / 55`, but its PHP
     pass count is stale on the dashboard (`108`) versus lane status (`125`).

4. **High - manifest and lane-status schemas still cannot support trustworthy portfolio percentages.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     and `goal.md:45` require real denominators, meaningful fixture parity,
     explicit slices for huge suites, and dashboard separation of denominator,
     mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable, but numeric in several other lanes.
     `runnerStatus` is an object in many lanes, a string in Gitoxide,
     markerPDF, and Quadrable, and absent from Pandoc.
   - Evidence: mapped upstream units and PHP behavior tests are different
     concepts but are still cross-displayed. Current examples: Dolt maps `377`
     upstream units but has `237` manifest PHP behavior tests and `240` lane
     PHP pass count; markerPDF maps `190` upstream units but has `299` PHP
     behavior tests; Readability maps `1334` upstream units but has `126` PHP
     behavior tests; Pandoc maps `541` upstream units while
     `nativeImplementation.phpBehaviorTests` is null.

5. **Medium - `progress.md`, lane statuses, and active processes disagree about ownership, estimates, commits, and next work.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:274`-`280`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:44` requires current owner/session,
     next task per lane, and percentage estimates.
   - Evidence: `progress.md:31`-`42` reports all lanes stopped with estimates
     from `5%` to `66%`, while lane statuses now report Difftastic `73`,
     Dolt `75`, esbuild `65`, Gitoxide `98`, libsqlite `93`,
     LightningCSS `76`, markerPDF `77`, Pandoc `87`, Quadrable `96`,
     rclone `92`, Readability `76`, and Syncthing `92`.
   - Evidence: every sampled `latestCommit` value is prose, pending state, or
     dirty-batch text rather than a single accepted commit id for the current
     lane batch.

6. **Medium - bounded, supplied, generated, and oracle-backed evidence remains too easy to over-credit as native parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`,
     and `goal.md:40` prohibit crediting bridge/shell/generated artifacts as
     native implementation progress and require hard gaps to be explicit.
   - Evidence: Gitoxide still lacks full Cargo runner parity, Difftastic still
     lacks full Cargo runner parity, Pandoc remains a static inventory,
     markerPDF continues to grow supplied-document/OCR plan coverage without a
     full upstream benchmark runner, rclone's bounded evidence still excludes
     provider integration/mount/live surfaces, Syncthing full `go test ./...`
     remains unrun, and Quadrable's strong C++ runner evidence should not make
     raw LMDB/oracle fixture breadth count as completion of remaining sync/CLI
     behavior.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Final audit sample:

```text
2611443 php tools/run-tests.php
2611450 php tools/run-tests.php
```

Owner evidence:

```text
2611443 claude 2611442 00:13 R php tools/run-tests.php
2611450 claude 2569257 00:12 Ss php tools/run-tests.php
```

Post-commit handoff sample:

```text
2612277 php tools/run-tests.php
owner evidence: 2612277 claude 2569759 00:21 Rs php tools/run-tests.php
```

No duplicate root run was started. Earlier clear gates were not sufficient for a
trustworthy aggregate run because the repository remained non-quiescent, and the
final gate found active matching root harnesses:

```text
git rev-parse --short=12 HEAD: 75bddbff6f57
git status --short --untracked-files=no: 144 tracked entries
git status --short: 1140 entries
git diff --shortstat: 144 files changed, 30468 insertions(+), 1090 deletions(-)
active automation matches: 26 including the sampler process
```

Recent history reviewed:

```text
75bddbff Record latest moving-head audit gate
c6fad4e2 Record audit handoff after moving head
7902f910 libsqlite add composite replacement root split
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
```

Active process evidence included:

```text
2424048 bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
2452997 bash scripts/run-capacity-controller-loop.sh
2479222 bash scripts/run-dashboard-updater-loop.sh
2546039 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-lightningcss ...
2553878 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-syncthing ...
2553990 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-esbuild ...
2554092 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt-runner ...
2556175 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-libsqlite ...
2556353 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-quadrable ...
2558985 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-gitoxide ...
2569229 bash scripts/run-tmux-agent.sh port-readability ...
2569238 bash scripts/run-tmux-agent.sh port-rclone ...
2569628 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-pandoc ...
2569713 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-difftastic ...
2591751 bash scripts/run-tmux-agent.sh port-auditor ...
2593828 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt ...
```

## Next Intervention

Freeze active writers/status publishers and duplicate root loops first. Then
rerun the exact duplicate-root gate, capture one quiesced
`php tools/run-tests.php` run from a single accepted snapshot if the gate stays
empty, accept or reject dirty lane batches one lane at a time, collapse root
test status to one repo-level record, normalize manifest/status schemas, and
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from that same accepted snapshot.
