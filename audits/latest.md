# Independent Audit - 2026-05-23T22:00Z

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

Sampled `HEAD`: `10eba61cd25e`.

Latest sampled worktree/process state:

```text
5702 total git status rows
267 tracked dirty files
267 files changed, 109815 insertions(+), 11680 deletions(-)
121 tmux sessions
30 active repo worker/status/test-control process matches
135 commits since sampled implementation commit b75226d1
```

The exact root-harness gate was checked before any possible root run:

```text
2005954 php tools/run-tests.php
2020061 php tools/run-tests.php lanes/quadrable/tests
2020261 php tools/run-tests.php lanes/readability/tests
2020309 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
2020323 php tools/run-tests.php lanes/syncthing/tests/DeviceDownloadStateTest.php ...
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
2005954 claude  2005742  00:30   R+   php tools/run-tests.php
2020061 claude  2019747  00:03   R+   php tools/run-tests.php lanes/quadrable/tests
2020261 claude  2019957  00:03   R+   php tools/run-tests.php lanes/readability/tests
2020309 claude  2020021  00:03   R+   php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
2020323 claude  2020087  00:03   R+   php tools/run-tests.php lanes/syncthing/tests/DeviceDownloadStateTest.php ...
```

Because an exact no-argument root harness was active, I did not start
`php tools/run-tests.php`.

A later pre-commit handoff gate also found active no-argument root PID
`2054047` owned by `claude`:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
2054047 claude  2053828  00:03   R+   php tools/run-tests.php
```

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:25`,
     `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `.tmux-team/*`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision,
     reviewable committed slices, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the gate found active no-argument root PID `2005954`
     owned by `claude`, plus focused Quadrable, Readability, and Syncthing
     shards. The repo is not quiescent: `121` tmux sessions, `30` active
     worker/status/test-control matches, `5702` status rows, and `267`
     tracked dirty files. `progress.md:25` still documents a two-worker plus
     auditor target, and `progress.md:31` through `progress.md:42` still
     report all lane sessions as `stopped`.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict the current manifests/status files.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:10` through `porting-summary.json:120`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current coordination files and
     a visible dashboard with denominator, mapped tests, PHP pass/fail,
     phase, audit, blocker, and commit fields.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 04:57:16 UTC` and source snapshot `bda83c6b93d4`, while
     sampled `HEAD` is `10eba61cd25e`. Current manifests report materially
     different mapped/denominator values: Difftastic `357 / prose-720`,
     Dolt `613 / prose/latest-slice`, esbuild `299 / prose-2567`,
     Gitoxide `2710 / 2877`, libsqlite `277 / 1589`, LightningCSS
     `1721 / 3532`, markerPDF `273 / 323`, Pandoc `989 / prose-2276`,
     Quadrable `55 / prose-55`, rclone `668 / 1601`, Readability
     `1984 / 1984`, and Syncthing `564 / 658`. The dashboard still shows
     older rows such as rclone `291 / 327`, markerPDF `159 / 78`, and
     Syncthing `235 / 658`.

3. **High - lane progress is mostly pending dirty handoff, not accepted
   implementation history.**
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
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`,
     `goal.md:48`, and `goal.md:49` require small correct slices, passing
     tests, verified handoff, and committed work.
   - Evidence: every sampled `latestCommit` field is `pending`,
     `uncommitted`, `not committed`, or dirty-batch prose. There are `135`
     commits since the sampled implementation commit `b75226d1`, and recent
     history remains dominated by audit/status/integration-hold records
     instead of accepted lane implementation commits.

4. **High - near-complete percentages overstate full native-port parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/dolt/lane-status.json:4`, `lanes/esbuild/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/libsqlite/lane-status.json:4`,
     `lanes/lightningcss/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/quadrable/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/readability/lane-status.json:4`, and
     `lanes/syncthing/lane-status.json:4`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native ports, prohibit
     counting oracle/bridge evidence as progress, and require explicit
     hard-feature gaps.
   - Evidence: most lanes now report `94%` to `99%` while root verification
     is pending, commits are unaccepted, and full upstream/live paths remain
     open. Examples: Difftastic is `99%` but full Cargo runner parity is not
     available; Gitoxide is `98%` with full Cargo workspace unexecuted and
     major live/TTY/network gaps; markerPDF is `98%` while live Python model,
     FastAPI, Streamlit, benchmark, publish, and CLA paths are unexecuted;
     rclone is `99%` while live provider/mount/FUSE cases remain open; and
     Syncthing is `99%` while full `go test ./...` remains unexecuted.

5. **High - manifest denominator and pass-count schemas remain
   non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` is a prose string in
     Difftastic, esbuild, Pandoc, and Quadrable; Dolt has no numeric
     top-level `total` and puts the executable denominator under inventory;
     markerPDF uses a static behavior/reference-unit denominator because
     upstream has no committed Python tests. Lane-status `phpPass` alternates
     between assertions, behavior tests, PASS cases, and mapped behavior
     checks. These units cannot safely drive a single average progress
     number.

6. **Medium - blocker fields still blur slice-local green checks with
   full-port blockers.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/quadrable/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond local passing
     tests, and explicit hard-feature gaps.
   - Evidence: blocker fields often start with `No ... blocker` while the
     same field admits pending root verification, uncommitted work,
     unexecuted full upstream runners, or excluded live/provider/model/server
     paths. Slice-local green state needs a separate field from full-port
     blocker state.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root PID `2005954` owned
by `claude`, plus focused Quadrable, Readability, and Syncthing PHP shards. A
later pre-commit gate matched active no-argument root PID `2054047` owned by
`claude`. Starting another aggregate root run would duplicate an existing
harness and would not produce an accepted baseline while the worktree remains
broad and non-quiescent.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
