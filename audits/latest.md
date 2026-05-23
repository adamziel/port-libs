# Independent Audit - 2026-05-23T21:06:58Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, worktree state, process state,
and the required root-harness duplicate gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

Sampled `HEAD` moved during this audit from `27491c5adad9` to
`8cb1002a3f76`.

Latest sampled worktree/process state:

```text
4192 total git status rows
258 tracked dirty files
258 files changed, 102746 insertions(+), 11492 deletions(-)
111 tmux sessions
117 commits since implementation commit b75226d1; sampled history since then is audit/status/integration-hold only
```

The required exact root-harness gate was checked before any possible root run.
It matched an active no-argument root harness during this audit window:

```text
945019 php tools/run-tests.php
```

Owner evidence captured for that root harness:

```text
945019 claude 944733 00:23 R+ php tools/run-tests.php
```

The same sample also showed focused PHP harnesses owned by `claude`, including:

```text
934784 claude 754014 00:46 Rs php tools/run-tests.php lanes/syncthing/tests
945190 claude 944953 00:23 R+ php tools/run-tests.php lanes/syncthing/tests/PlatformMetadataApplierTest.php ...
```

A later exact gate briefly returned no rows, but final validation found another
active no-argument root harness:

```text
1030906 claude 1030813 00:13 R+ php tools/run-tests.php
```

No root run was started because duplicate-root prevention was active again and
the tree was still unstable: `HEAD` moved during the audit, the dirty tree
remained large, and active tmux/capacity/dashboard/evaluator/auditor/integrator
and lane sessions persisted.

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:25`,
     `progress.md:31` through `progress.md:42`, `.tmux-team/*`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`, and
     `scripts/run-capacity-executor-queue.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable committed slices, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the required root gate found no-argument root PID `945019`
     owned by `claude`; `HEAD` moved from `27491c5adad9` to `8cb1002a3f76`
     while auditing; `tmux list-sessions` reported 111 sessions even though
     `progress.md` still says the launch target is 2 implementation lanes plus
     1 auditor and every lane is `stopped`; the dirty tree still has 4192
     status rows and 258 tracked dirty files.

2. **Critical - the public dashboard is stale and materially contradicts the
   current manifests and statuses.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`, `porting-summary.json`,
     and all `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current coordination files and a
     visible dashboard with denominator, mapped tests, PHP pass/fail, phase,
     audit, blocker, and commit fields.
   - Evidence: `porting.html` still publishes generated time
     `2026-05-23 04:57:16 UTC` and snapshot `bda83c6b93d4`, while sampled
     `HEAD` is `8cb1002a3f76`. Current manifests/statuses disagree with the
     dashboard: rclone is `645 / 1601` but dashboard row shows `291 / 327`;
     Syncthing is `548 / 658` but dashboard row shows `235 / 658`;
     LightningCSS is `1713 / 3532` but dashboard row shows `773 / 3532`;
     markerPDF is `265 / 316` but dashboard row shows `159 / 78`; Pandoc is
     `944 / prose 2276` while dashboard row shows `426 / 2028`.

3. **High - near-complete lane claims are attached to unaccepted dirty batches,
   not committed verified slices.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
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
     `goal.md:36`, and `goal.md:48` require small correct slices, passing
     tests, and verified committed handoffs before assigning more work.
   - Evidence: every sampled `latestCommit` is `pending`, `uncommitted`,
     `not committed`, or dirty-batch prose. Current branch history contains
     117 commits after implementation commit `b75226d1`; sampled subjects are
     audit/status/integration-hold records, not accepted implementation
     integration commits.

4. **High - manifest/status count schemas are still non-normalized and even
   disagree inside the same lane family.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2220`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:746`, and
     `lanes/readability/lane-status.json:5`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: Difftastic, Dolt, Pandoc, and Quadrable use prose strings in
     `benchmarkDenominator.total`; markerPDF's manifest says `265 / 316` with
     `phpBehaviorTests: 400` while its status still says `264 / 315` and
     `phpPass: 398`; Pandoc's manifest now says `944` mapped while the status
     says `935`; Readability maps `1984 / 1984` upstream tests while recording
     roughly 190 native PHP behavior tests, and its manifest/status PHP counts
     also drift.

5. **High - markerPDF and Readability still overstate native parity from
   static, supplied, copied, or upstream-oracle evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:606`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:740`, and
     `lanes/readability/lane-status.json:5`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native standard-PHP ports,
     generated/bridge evidence to not count as implementation progress,
     meaningful fixture parity, and explicit hard-feature gaps.
   - Evidence: markerPDF has 0 committed upstream Python tests, no full
     upstream runner, and status says it maps supplied/plan-only boundaries
     without executing Python/model/PDF stacks. Readability maps `1984 / 1984`
     upstream Mocha tests, but the native implementation evidence is about 190
     PHP behavior tests plus copied Mozilla fixtures and local upstream JS
     oracle checks.

6. **Medium - blocker fields still blur slice-local green checks with full-port
   blockers.**
   - Paths: `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`, and similar status files.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond local passing tests,
     and explicit hard-feature gaps.
   - Evidence: many blocker fields begin with "No ... blocker" while the same
     field discloses pending aggregate root verification, unexecuted full
     upstream runners, excluded live-provider/server/model paths, or broad
     parity gaps. Slice-local green status needs to be separated from full-port
     blockers.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root PID `945019`, owned
by `claude`. Later exact root samples briefly cleared, but final validation
found active no-argument root PID `1030906` owned by `claude`. The stability
gate also failed because `HEAD` moved, 111 tmux sessions remained, and the
dirty tree still had 258 tracked dirty files. Starting a new aggregate root run
would not produce accepted baseline evidence.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
