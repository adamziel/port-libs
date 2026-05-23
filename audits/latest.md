# Independent Audit - 2026-05-23T22:42:30Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files for cross-checking, recent Git history, current worktree/process
state, and the required pre-root harness gate.

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
HEAD: 1c5a4a1f57a8
6986 total git status rows
276 tracked dirty files
6710 untracked paths
276 files changed, 115357 insertions(+), 12027 deletions(-)
128 tmux sessions
active dashboard/watchdog/evaluator/integrator/capacity/auditor/lane-agent loops
```

A required pre-root gate sample matched an active no-argument root harness:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3102662 php tools/run-tests.php
3104461 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,user,ppid,etime,state,comm,args -p 3102662,3104461
3102662 claude 3101944 00:15 R php php tools/run-tests.php
3104461 claude 2821107 00:14 R php php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php`: the duplicate-root gate was blocked
at that sample, and the tree is not stable enough for accepted aggregate
evidence.

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:25`,
     `progress.md:31`, `progress.md:42`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and `.tmux-team/`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     committed slices with passing tests, integration cleanup, and honest
     repo-wide verification.
   - Evidence: the repo has `126` tmux sessions, active
     dashboard/watchdog/evaluator/integrator/capacity/auditor/lane-agent loops,
     active root PID `3102662` owned by `claude`, `6986` status rows,
     `276` tracked dirty files, and a `276`-file diff with `115357`
     insertions. This contradicts the active-lane table, which still reports
     every lane as `stopped`.

2. **Critical - `progress.md`, `porting.html`, and `porting-summary.json` are
   materially stale relative to current manifests and lane statuses.**
   - Paths: `porting.html:30`, `porting.html:33`,
     `porting.html:54`, `porting.html:65`,
     `porting-summary.json:2`, `progress.md:31`,
     `progress.md:42`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require durable coordination files and a
     visible dashboard with current denominator, mapped tests, PHP pass/fail,
     phase, audit, blocker, and commit fields.
   - Evidence: `porting.html` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and snapshot
     `bda83c6b93d4`, while sampled `HEAD` is `1c5a4a1f57a8`. Current files
     disagree with the dashboard: Difftastic is `363 / 731` mapped in
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` and `:15` versus
     dashboard `160 / 417`; Gitoxide is `2713 / 2877` versus `1432 / 2877`;
     libsqlite is `281 / 1589` versus `149 / 1454`; markerPDF is
     `276 / 326` versus `159 / 78`; rclone is `679 / 1601` versus
     `291 / 327`; Readability is `1984 / 1984` versus `1031 / 1984`;
     Syncthing is `650 / 658` versus `235 / 658`.

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
     `not committed`, or dirty-batch prose. Recent history is dominated by
     `Refresh independent audit status` and `Record integration hold status`,
     not accepted lane implementation commits.

4. **High - near-complete parity signals overstate what is actually proven.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/lane-status.json:5`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/readability/lane-status.json:5`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/syncthing/lane-status.json:5`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require meaningful native parity,
     upstream tests as source of truth, and explicit hard-feature blockers.
   - Evidence: Gitoxide maps `2713 / 2877` but full cargo workspace runner is
     still not executed; markerPDF maps `276 / 326` while explicitly avoiding
     Python/model/PDF/OCR/Streamlit/FastAPI/multiprocessing execution;
     Readability claims `1984 / 1984` mapped while local PHP evidence is only
     `199` behavior tests plus copied fixture/oracle evidence; Syncthing maps
     `650 / 658` while full `go test ./...` remains unexecuted.

5. **High - manifest and status schemas remain non-normalized, so portfolio
   math is not comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/lane-status.json:6`,
     `lanes/lightningcss/lane-status.json:6`,
     `lanes/readability/lane-status.json:6`, and
     `lanes/syncthing/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: some denominators are numeric, while Difftastic, esbuild,
     Pandoc, and Quadrable store prose strings; Pandoc also has both prose
     `total` and numeric `totalCount`. `phpPass` mixes behavior counts and
     assertions: Gitoxide reports `5557`, LightningCSS `2171`, Readability
     `199`, and Syncthing `4284`.

6. **Medium - blocker language still conflates slice-local green checks with
   full-port blockers.**
   - Paths: `lanes/difftastic/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: several lanes say "no blocker" because focused PHP tests are
     green, then list major unexecuted upstream suites, live provider/service
     gaps, full cargo/go/Haskell runners, model/PDF/OCR/runtime gaps, or root
     aggregate verification as pending. That is not precise enough to decide
     whether a lane is implementation-ready, audit-ready, or merely
     slice-green.

## Test Gate

I did not run `php tools/run-tests.php`.

A required pre-root gate sample matched active no-argument root PID
`3102662 php tools/run-tests.php`, owned by `claude`, plus focused Syncthing
runner PID `3104461`, also owned by `claude`. I did not start a no-argument
root run because the duplicate-root gate was blocked and the stability gate
failed: active writer/status/test-control loops persist and the worktree
remains a large dirty aggregate.

A post-commit handoff gate then matched a newer active no-argument root PID:
`3121853 php tools/run-tests.php`, owned by `claude`
(`3121853 claude 3121789 00:28 R php php tools/run-tests.php`).

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
