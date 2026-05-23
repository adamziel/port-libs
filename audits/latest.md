# Independent Audit - 2026-05-23T21:13:01Z

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

Sampled `HEAD` moved during this audit from `21c75c2a52bc` to
`9fb51c0d1e7f`.

Latest sampled worktree/process state:

```text
4323 total git status rows
260 tracked dirty files
262 files changed, 103557 insertions(+), 11536 deletions(-)
111 tmux sessions
latest sampled history remains audit/status/integration-hold dominated
```

The required exact root-harness gate was checked before any possible root run.
It matched active PHP harnesses, including two no-argument root harnesses:

```text
1096306 php tools/run-tests.php lanes/syncthing/tests/PlatformMetadataApplierTest.php ...
1108109 php tools/run-tests.php
1108181 php tools/run-tests.php
```

Owner evidence captured for the active harnesses:

```text
1096306 claude 00:47 php tools/run-tests.php lanes/syncthing/tests/PlatformMetadataApplierTest.php ...
1108109 claude 00:25 php tools/run-tests.php
1108181 claude 00:25 php tools/run-tests.php
```

A later sample still showed root PID `1108181` owned by `claude`:

```text
1108181 claude 1107984 01:22 R php tools/run-tests.php
```

The final exact gate later returned no rows, but no root run was started because
the tree was still not stable enough: `HEAD` moved, the dirty tree grew, and
active tmux/capacity/dashboard/evaluator/integrator/auditor/lane sessions
persisted.

A post-edit handoff gate then found another active no-argument root harness:

```text
1160715 claude 1160530 00:53 R php tools/run-tests.php
```

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:25`,
     `progress.md:31` through `progress.md:42`, `progress.md:421`,
     `.tmux-team/*`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`, and
     `scripts/run-capacity-executor-queue.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable committed slices, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the pre-root gate matched no-argument root PIDs `1108109` and
     `1108181`, both owned by `claude`; `HEAD` moved from `21c75c2a52bc` to
     `9fb51c0d1e7f`; `tmux list-sessions` reported 111 sessions even though
     `progress.md` still documents a two-worker-plus-auditor target and every
     lane in the Active Lanes table is `stopped`; the dirty tree is still broad
     at 4323 status rows, 260 tracked dirty files, and 262 changed tracked
     files.

2. **Critical - the dashboard is stale and materially contradicts current
   manifests and statuses.**
   - Paths: `porting.html:32` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:11` through `porting-summary.json:25`, and
     all `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current coordination files and a
     visible dashboard with denominator, mapped tests, PHP pass/fail, phase,
     audit, blocker, and commit fields.
   - Evidence: `porting.html` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `9fb51c0d1e7f`. Current manifest
     rows disagree with the dashboard: Difftastic is `353 / prose 710` while
     the dashboard shows `160 / 417`; rclone is `650 / 1601` while the
     dashboard shows `291 / 327`; Syncthing is `548 / 658` while the dashboard
     shows `235 / 658`; LightningCSS is `1716 / 3532` while the dashboard
     shows `773 / 3532`; Pandoc is `944 / prose 2276` while the dashboard
     shows `426 / 2028`; Readability is `1984 / 1984` while the dashboard
     shows `1031 / 1984`.

3. **High - near-complete lane claims are tied to unaccepted dirty batches, not
   verified commits.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/readability/lane-status.json:4`,
     `lanes/readability/lane-status.json:13`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:4`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:36`, and `goal.md:48` require small correct slices, passing
     tests, and verified committed handoffs before assigning more work.
   - Evidence: sampled lane estimates are now as high as 98-99%, but
     `latestCommit` fields say `pending`, `uncommitted`, `not committed`, or
     dirty-batch prose. The latest sampled Git history is still
     `Refresh independent audit status` and `Record integration hold status`
     commits rather than accepted implementation commits.

4. **High - manifest count schemas are still non-normalized and
   non-comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2220`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:613`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:751`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` is a prose string in Difftastic,
     Dolt, Pandoc, and Quadrable, while other lanes use integers. `mapped`
     alternates between upstream mapped units, static behavior units, and PHP
     behavior counts. MarkerPDF records `265 / 316` static mapped units plus
     `400` PHP behavior tests; Readability records `1984 / 1984` mapped
     upstream Mocha tests plus only `191` PHP behavior tests. These values are
     not safe to average or publish as one progress metric.

5. **High - markerPDF and Readability still overstate native parity from
   static, copied, supplied, or upstream-oracle evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:28`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:36` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:42`,
     `lanes/readability/lane-status.json:4`, and
     `lanes/readability/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, `goal.md:37`, and `goal.md:40` require native
     standard-PHP ports, generated/bridge evidence to not count as
     implementation progress, meaningful fixture parity, upstream tests as
     source of truth, and explicit hard-feature gaps.
   - Evidence: markerPDF reports 98% progress while the manifest says upstream
     has 0 committed Python tests and the full runner remains blocked by heavy
     Python/PDF/model dependencies. Readability reports 99% progress and
     `1984 / 1984` mapped upstream tests, but the native evidence is 191 PHP
     behavior tests plus copied Mozilla fixtures and local upstream JS oracle
     checks.

6. **Medium - blocker fields still blur slice-local green checks with full-port
   blockers.**
   - Paths: `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`, and similar lane-status files.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond local passing tests,
     and explicit hard-feature gaps.
   - Evidence: blocker fields start with "No ... blocker" while the same field
     admits pending aggregate root verification, unexecuted full upstream
     runners, excluded live-provider/server/model paths, or broad parity gaps.
     Slice-local green status needs to be separated from full-port blockers.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root PIDs `1108109` and
`1108181`, owned by `claude`, plus a focused Syncthing PHP harness. A later
exact gate cleared, but the stability gate still failed because `HEAD` moved,
111 tmux sessions remained, the dirty tree stayed broad, and status/dashboard
publisher activity continued. A post-edit handoff gate then found active
no-argument root PID `1160715` owned by `claude`. Starting another aggregate
root run would not produce accepted baseline evidence.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
