# Independent Audit - 2026-05-23T21:24:25Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files for cross-checking, recent Git history, current worktree state,
process state, and the required duplicate root-harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

Sampled `HEAD` moved during this audit from `0ab5043622dd` to
`4638876c73fb`.

Latest sampled worktree/process state:

```text
4764 total git status rows
264 tracked dirty files
264 files changed, 105429 insertions(+), 11652 deletions(-)
115 tmux sessions
latest 40 commits are audit/status/integration-hold records
```

The required exact root-harness gate was checked before any possible root run:

```text
1232384 php tools/run-tests.php lanes/syncthing/tests/IndexHandlerTest.php ...
1236085 php tools/run-tests.php
```

Owner evidence for the no-argument root harness:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
1236085 claude  1236031  00:27   R+   php tools/run-tests.php
```

Because an exact no-argument root harness was active, I did not start
`php tools/run-tests.php`.

A post-edit handoff gate found the root harness had rolled again:

```text
1272830 php tools/run-tests.php
1289124 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
1272830 claude  1272397  00:13   R+   php tools/run-tests.php
1289124 claude  1288998  00:09   R+   php tools/run-tests.php
```

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:25`, `progress.md:31` through
     `progress.md:42`, `progress.md:421`, `.tmux-team/*`, and
     `scripts/run-php-dirty-root.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable committed slices, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the required duplicate-root gate found active no-argument root
     PID `1236085` owned by `claude`. The tree is not quiescent: `git status`
     reported 4764 rows, 264 tracked dirty files, and 264 changed tracked
     files with 105429 insertions and 11652 deletions. `tmux list-sessions`
     reported 115 sessions while `progress.md` still documents a 2-worker plus
     auditor target and every Active Lanes row says `stopped`.

2. **Critical - the public dashboard remains stale and materially contradicts
   the current manifests.**
   - Paths: `porting.html:32`, `porting.html:33`, `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json:3`, `porting-summary.json:16` through
     `porting-summary.json:212`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current coordination files and a
     visible dashboard with denominator, mapped tests, PHP pass/fail, phase,
     audit, blocker, and commit fields.
   - Evidence: `porting.html` still publishes generated time
     `2026-05-23 04:57:16 UTC` and snapshot commit `bda83c6b93d4`, while the
     current sampled `HEAD` is `4638876c73fb`. Current manifests disagree with
     the dashboard rows: Difftastic is `354 / prose 713` while the dashboard is
     `160 / 417`; Gitoxide is `2706 / 2877` while the dashboard is
     `1432 / 2877`; LightningCSS is `1717 / 3532` while the dashboard is
     `773 / 3532`; markerPDF is `266 / 316` while the dashboard is
     `159 / 78`; Pandoc is `954 / prose 2276` while the dashboard is
     `426 / 2028`; rclone is `655 / 1601` while the dashboard is
     `291 / 327`; Readability is `1984 / 1984` while the dashboard is
     `1031 / 1984`; Syncthing is `552 / 658` while the dashboard is
     `235 / 658`.

3. **High - lane claims are still pending dirty handoffs rather than accepted
   implementation commits.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:36`, and `goal.md:48` require passing, reviewable commits,
     meaningful tests, and verified handoffs before assigning more work.
   - Evidence: latestCommit fields say `pending`, `uncommitted`,
     `not committed`, or dirty-batch prose across lanes. The latest 40 commits
     are audit/status/integration-hold records, not accepted implementation
     slices.

4. **High - manifest count schemas remain non-normalized and unsafe to average.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2220`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` is sometimes a number and
     sometimes prose. In Dolt, `benchmarkDenominator.mapped` is `613` near the
     top while `benchmarkDenominator.total` appears as long latest-slice prose
     near line 2220. `mapped` still mixes executable upstream tests, static
     behavior artifacts, copied fixture inventories, targeted source
     references, and PHP behavior tests.

5. **High - markerPDF and Readability still overstate native parity from
   static, supplied, copied, and upstream-oracle evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:613`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:756`, and
     `lanes/readability/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, `goal.md:37`, and `goal.md:40` require native
     standard-PHP ports, generated/bridge evidence to not count as
     implementation progress, meaningful fixture parity, upstream tests as
     source of truth, and explicit hard-feature gaps.
   - Evidence: markerPDF records `266 / 316` mapped units and 400 PHP behavior
     tests while its status still says full upstream benchmark, Streamlit,
     FastAPI, multiprocessing, pdftext, and model-backed execution remain not
     executed. Readability records `1984 / 1984` mapped with only 192 PHP
     behavior tests and status language based on copied Mozilla fixtures plus
     local upstream JS oracle checks.

6. **Medium - blocker language still blurs slice-local green checks with
   full-port blockers.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond local passing tests,
     and explicit hard-feature gaps.
   - Evidence: blocker fields begin with `No ... blocker` while the same field
     admits pending aggregate root verification, uncommitted work, unexecuted
     full upstream runners, excluded live providers/servers/model paths, or
     broad parity gaps. Slice-local green status needs a separate field from
     full-port blocker status.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root PID `1236085` owned
by `claude`, plus a focused Syncthing shard. Post-edit handoff gates matched
active no-argument root PIDs `1272830` and `1289124`, also owned by `claude`.
Starting another aggregate root run would duplicate an existing harness and
would not produce an accepted baseline while the worktree remains broad and
non-quiescent.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
