# Independent Audit - 2026-05-23T21:40:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files for cross-checking,
recent Git history, current worktree state, process state, and the required
duplicate root-harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

Sampled `HEAD`: `395219edeca1`.

Latest sampled worktree/process state:

```text
5107 total git status rows
262 tracked dirty files
262 files changed, 106533 insertions(+), 11655 deletions(-)
116 tmux sessions
129 commits since sampled implementation commit b75226d1
latest 40 commits are audit/status/integration-hold records
```

The required exact root-harness gate was checked before any possible root run:

```text
1602856 php tools/run-tests.php
1603025 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
1603132 php tools/run-tests.php lanes/syncthing/tests/IndexHandlerTest.php ...
1603914 php tools/run-tests.php lanes/syncthing/tests
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
1602856 claude  1602495  16      R+   php tools/run-tests.php
1603025 claude  1602762  16      R+   php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
1603132 claude  1602905  16      R+   php tools/run-tests.php lanes/syncthing/tests/IndexHandlerTest.php ...
1603914 claude  1433275  6       Rs   php tools/run-tests.php lanes/syncthing/tests
```

Because an exact no-argument root harness was active, I did not start
`php tools/run-tests.php`.

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:20` through
     `progress.md:25`, `progress.md:27` through `progress.md:42`,
     `.tmux-team/*`, `scripts/run-php-dirty-root.sh`, and
     `scripts/run-team-watchdog.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision,
     reviewable committed slices, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the duplicate-root gate found active no-argument root PID
     `1602856` owned by `claude`, plus multiple focused Syncthing PHP
     harnesses. The tree is not quiescent: `git status` reported `5107`
     rows, `262` tracked dirty files, and `262 files changed, 106533
     insertions(+), 11655 deletions(-)`. `tmux list-sessions` reported
     `116` sessions while `progress.md:25` still documents a 2-worker plus
     auditor target and every Active Lanes row in `progress.md:31` through
     `progress.md:42` says `stopped`.

2. **Critical - `porting.html` is stale and materially contradicts the
   current manifests/status files.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current coordination files and
     a visible dashboard with denominator, mapped tests, PHP pass/fail,
     phase, audit, blocker, and commit fields.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 04:57:16 UTC` and snapshot `bda83c6b93d4`, while sampled
     `HEAD` is `395219edeca1`. Current manifest/status rows disagree with
     the dashboard: Difftastic is `355 / 716-prose` while the dashboard is
     `160 / 417`; Dolt is `613 mapped` while the dashboard is `242 / 613`;
     esbuild is `296 / 2567` while the dashboard is `164 / 2567`;
     Gitoxide is `2708 / 2877` while the dashboard is `1432 / 2877`;
     libsqlite is `276 / 1589` while the dashboard is `149 / 1454`;
     LightningCSS is `1719 / 3532` while the dashboard is `773 / 3532`;
     markerPDF is `269 / 319` while the dashboard is `159 / 78`; Pandoc is
     `974 / 2276-prose` while the dashboard is `426 / 2028`; rclone is
     `660 / 1601` while the dashboard is `291 / 327`; Readability is
     `1984 / 1984` while the dashboard is `1031 / 1984`; Syncthing is
     `560 / 658` while the dashboard is `235 / 658`.

3. **High - lane progress remains mostly pending dirty handoff, not accepted
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
   - Evidence: sampled `latestCommit` fields say `pending`, `not
     committed`, `uncommitted`, or dirty-batch prose. The latest 40 commits
     are audit/status/integration-hold records, and `HEAD` is 129 commits
     past the latest sampled implementation commit `b75226d1`.

4. **High - manifest denominator schemas remain non-normalized and unsafe
   to average.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as the source of truth, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` is a number in some lanes and
     prose in Difftastic, Dolt, esbuild, Pandoc, and Quadrable. `mapped`
     mixes executable upstream tests, local PHP behavior tests, static source
     reads, supplied-document excerpts, copied fixture inventories, selected
     oracle probes, and plan-only workflow boundaries. The dashboard average
     is therefore a presentation number, not a comparable measure of native
     port parity.

5. **High - near-complete lane percentages overstate native full-port
   parity.**
   - Paths: `lanes/difftastic/lane-status.json:4` through
     `lanes/difftastic/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:4` through
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native ports, prohibit counting
     oracle/bridge evidence as progress, and require explicit hard-feature
     gaps.
   - Evidence: Difftastic, rclone, Readability, and Syncthing report `99%`
     and markerPDF reports `98%` despite uncommitted batches, pending
     aggregate root verification, and major full-runner/live-provider/model
     gaps. markerPDF's newest slice includes publish/CLA workflow planning
     and secret-reference metadata, which is release-process planning rather
     than PDF conversion parity. Readability marks `1984 / 1984` mapped after
     copying all Mozilla fixtures, but the status still says focused PHP
     coverage is `194` tests and root aggregate verification was not run.

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
     `goal.md:40` require precise blockers, parity beyond local passing
     tests, and explicit hard-feature gaps.
   - Evidence: blocker fields start with `No ... blocker` while the same
     fields admit pending root verification, uncommitted work, unexecuted
     full upstream runners, excluded live providers/servers/model paths, or
     broad parity gaps. Slice-local green status needs a separate field from
     full-port blocker status.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root PID `1602856` owned
by `claude`, plus three focused Syncthing PHP harnesses. Starting another
aggregate root run would duplicate an existing harness and would not produce an
accepted baseline while the worktree remains broad and non-quiescent.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
