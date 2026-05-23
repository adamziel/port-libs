# Independent Audit - 2026-05-23T22:42Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files for cross-checking, recent Git history, current worktree/process
state, and the required duplicate root-harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle
fixtures, and shell-outs are treated as non-progress unless explicitly
temporary oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

Sampled repository state:

```text
HEAD: bafd740a1a73
6717 total git status rows
271 tracked dirty files
271 files changed, 114205 insertions(+), 11839 deletions(-)
125 tmux sessions
active dashboard/watchdog/evaluator/integrator/capacity/auditor/lane-agent loops
```

The required pre-root gate matched an active no-argument root harness:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
2847089 php tools/run-tests.php
2848275 php tools/run-tests.php lanes/syncthing/tests/...
2862340 php tools/run-tests.php lanes/quadrable/tests

ps -o pid,user,ppid,etime,state,comm,args -p 2847089
2847089 claude 2846943 00:48 R php php tools/run-tests.php
```

I did not start `php tools/run-tests.php`: the required duplicate-root gate was
not clear, and the tree is not stable enough for accepted aggregate evidence.

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:31` through
     `progress.md:42`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `scripts/run-evaluator-loop.sh`,
     and `.tmux-team/`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     committed slices with passing tests, integration cleanup, and honest
     repo-wide verification.
   - Evidence: the repo has `125` tmux sessions, active
     dashboard/watchdog/evaluator/integrator/capacity/auditor/lane-agent loops,
     active root PID `2847089` owned by `claude`, `6717` status rows,
     `271` tracked dirty files, and a `271`-file diff with `114205`
     insertions. This contradicts the documented active-lane table that still
     reports every lane as `stopped`.

2. **Critical - `progress.md`, `porting.html`, and `porting-summary.json` are
   materially stale relative to the manifests and lane statuses.**
   - Paths: `porting.html:30` through `porting.html:33`,
     `porting.html:54` through `porting.html:65`, `porting-summary.json`,
     `progress.md:31` through `progress.md:42`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require durable coordination files and a
     visible dashboard with current denominator, mapped tests, PHP pass/fail,
     phase, audit, blocker, and commit fields.
   - Evidence: `porting.html` still publishes generated time
     `2026-05-23 04:57:16 UTC` and snapshot `bda83c6b93d4`, while sampled
     `HEAD` is `bafd740a1a73`. Current files disagree with the dashboard:
     Difftastic is `363 / 731` mapped versus dashboard `160 / 417`; Dolt is
     `613 / 613` versus `242 / 613`; Gitoxide is `2712 / 2877` versus
     `1432 / 2877`; markerPDF is `275 / 325` versus `159 / 78`; rclone is
     `679 / 1601` versus `291 / 327`; Syncthing is `650 / 658` versus
     `235 / 658`.

3. **High - every lane handoff is still pending dirty integration rather than
   accepted implementation history.**
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
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small,
     reviewable committed slices and cleanup before reassignment.
   - Evidence: the lane `latestCommit` fields are `pending`, `uncommitted`,
     `not committed`, dirty-batch prose, or `HEAD ... at status update`. The
     recent history sample is dominated by `Refresh independent audit status`
     and `Record integration hold status` commits, not accepted lane
     implementation commits.

4. **High - near-complete parity signals overstate what is actually proven.**
   - Paths: `lanes/gitoxide/lane-status.json:5`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/readability/lane-status.json:5`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/syncthing/lane-status.json:5`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require meaningful native parity,
     upstream tests as source of truth, and explicit hard-feature blockers.
   - Evidence: Gitoxide maps `2712 / 2877` but full cargo workspace runner is
     still not executed; markerPDF maps `275 / 325` while explicitly avoiding
     Python/model/PDF/OCR/Streamlit/FastAPI/multiprocessing execution;
     Readability claims `1984 / 1984` mapped while local PHP evidence is
     `198` behavior tests and copied fixture/oracle evidence; Syncthing maps
     `650 / 658` while full `go test ./...` remains unexecuted from a
     blob-filter/no-checkout cache.

5. **High - manifest and status schemas remain non-normalized, so portfolio
   math is not comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2252`, and
     `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: some denominators are numeric, while others are prose strings;
     Pandoc has both prose `total` and numeric `totalCount`; Dolt has
     `benchmarkDenominator.mapped` at line 14 but stores a prose `total` under
     native implementation evidence at line 2252. `phpPass` mixes behavior
     counts and assertions: Gitoxide reports `5544`, LightningCSS `2171`,
     markerPDF `409`, Readability `198`, and Syncthing `4284`.

6. **Medium - blocker language still conflates slice-local green checks with
   full-port blockers.**
   - Paths: `lanes/difftastic/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers and no silent skipping of hard
     features.
   - Evidence: several lanes say "no blocker" because focused PHP tests are
     green, then list major unexecuted upstream suites, live provider/service
     gaps, full cargo/go/Haskell runners, model/PDF/OCR/runtime gaps, or root
     aggregate verification as pending. That language is not precise enough
     for deciding whether the lane is implementation-ready, audit-ready, or
     merely slice-green.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root PID
`2847089 php tools/run-tests.php`, owned by `claude`, plus focused lane PHP
shards at the same sample. Starting another no-argument root run would violate
the duplicate-root constraint. The broader stability gate also failed because
active writer/status/test-control loops persist and the worktree remains a
large dirty aggregate.

Post-commit handoff samples matched later active no-argument root PIDs,
including `2903759 php tools/run-tests.php`, owned by `claude`
(`2903759 claude 2903582 00:35 R php php tools/run-tests.php`), and then
`2946219 php tools/run-tests.php`, owned by `claude`
(`2946219 claude 2946088 00:32 R php php tools/run-tests.php`). The
duplicate-root condition persisted after the audit commit.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
