# Independent Audit - 2026-05-23T21:19:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current lane status files for cross-checking, recent Git history, worktree
state, process state, and the required duplicate root-harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

Sampled `HEAD` moved during this audit from `457e5dca0db4` to
`4018c0e0d9ad`.

Latest sampled worktree/process state:

```text
4535 total git status rows
262 tracked dirty files
262 files changed, 104269 insertions(+), 11538 deletions(-)
113 tmux sessions
latest 30 commits are audit/status/integration-hold records
```

The required exact root-harness gate was checked before any possible root run:

```text
1181457 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED S COMMAND
1181457 claude  1181268  01:25   R php tools/run-tests.php
```

The parent command was `scripts/run-php-dirty-root.sh` from a capacity feed.
Because a no-argument root harness was active, I did not start
`php tools/run-tests.php`.

A final handoff gate found the root harness had rolled to another active
no-argument process:

```text
1205067 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED S COMMAND
1205067 claude  1205018  00:54   R php tools/run-tests.php
```

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `scripts/run-php-dirty-root.sh`,
     `scripts/run-capacity-executor-queue.sh`, `.tmux-team/*`,
     `progress.md:25`, `progress.md:31` through `progress.md:42`, and
     `progress.md:421`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable committed slices, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the duplicate-root gate found active no-argument root PID
     `1181457` owned by `claude`; `HEAD` moved during sampling; `tmux
     list-sessions` reported 113 sessions while `progress.md` still documents
     a two-worker-plus-auditor target and every Active Lanes row says
     `stopped`; the dirty tree is broad at 4535 status rows and 262 tracked
     dirty files.

2. **Critical - the public dashboard is stale and materially contradicts the
   current manifests.**
   - Paths: `porting.html:30`, `porting.html:32` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`, and
     all `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current coordination files and a
     visible dashboard with denominator, mapped tests, PHP pass/fail, phase,
     audit, blocker, and commit fields.
   - Evidence: `porting.html` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while current `HEAD` is `4018c0e0d9ad`. Current manifest
     rows disagree with the dashboard: Difftastic is `353 / 710` while the
     dashboard shows `160 / 417`; rclone is `650 / 1601` while the dashboard
     shows `291 / 327`; Syncthing is `548 / 658` while the dashboard shows
     `235 / 658`; LightningCSS is `1717 / 3532` while the dashboard shows
     `773 / 3532`; Pandoc is `954 / prose 2276` while the dashboard shows
     `426 / 2028`; markerPDF is `266 / 316` while the dashboard shows
     `159 / 78`; Readability is `1984 / 1984` while the dashboard shows
     `1031 / 1984`.

3. **High - lane claims are still mostly pending dirty handoffs, not accepted
   implementation commits.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, and
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:36`, and `goal.md:48` require small correct slices, meaningful
     tests, and verified committed handoffs before assigning more work.
   - Evidence: lane `latestCommit` fields repeatedly say `pending`,
     `uncommitted`, `not committed`, or dirty-batch prose. The latest 30
     commits are `Refresh independent audit status` or `Record integration
     hold status`, not accepted implementation slices.

4. **High - manifest counts are non-normalized and unsafe to average.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     Pandoc, and Quadrable but numeric in other lanes. `mapped` mixes upstream
     executable tests, static behavior artifacts, copied fixture inventory,
     targeted source references, and PHP behavior tests. Those categories
     cannot support the current single average progress number.

5. **High - markerPDF and Readability still overstate native parity from
   static, supplied, copied, and upstream-oracle evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/readability/lane-status.json`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, `goal.md:37`, and `goal.md:40` require native
     standard-PHP ports, generated/bridge evidence to not count as
     implementation progress, meaningful fixture parity, upstream tests as
     source of truth, and explicit hard-feature gaps.
   - Evidence: markerPDF records 316 static behavior/reference units, 266
     mapped units, and 400 PHP behavior tests while also saying upstream has
     0 committed Python tests and the full runner requires heavy Python/PDF/model
     dependencies. Readability records `1984 / 1984` mapped upstream Mocha
     tests, but native evidence is 192 focused PHP tests plus copied Mozilla
     fixtures and local upstream JS oracle checks.

6. **Medium - blocker language still blurs slice-local green checks with
   full-port blockers.**
   - Paths: `lanes/gitoxide/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`,
     and similar lane-status files.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond local passing tests,
     and explicit hard-feature gaps.
   - Evidence: blocker fields begin with "No ... blocker" while the same field
     admits pending aggregate root verification, unexecuted full upstream
     runners, excluded live-provider/server/model paths, or broad parity gaps.
     Slice-local green status needs a separate field from full-port blockers.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root PID `1181457` owned
by `claude`; a final handoff gate later matched active no-argument root PID
`1205067`, also owned by `claude`. The tree was also unstable: `HEAD` moved,
113 tmux sessions were active, the dirty tree stayed broad, and
dashboard/evaluator/integrator/capacity loops were active. Starting another
aggregate root run would duplicate an existing harness and would not create
accepted baseline evidence.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP harnesses.
Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
