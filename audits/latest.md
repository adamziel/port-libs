# Independent Audit - 2026-05-23T21:01:44Z

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

Sampled `HEAD`: `c2a83c53dcd4`.

Latest sampled worktree/process state:

```text
4069 total git status rows
259 tracked dirty rows
259 files changed, 102096 insertions(+), 11464 deletions(-)
99 tmux sessions
24 sampled repo worker/status/test-control process matches
115 commits since implementation commit b75226d1; all 115 are audit/status records
```

Recent Git history remains coordination-only since the latest sampled
implementation commit:

```text
b75226d1 Port rclone OneDrive Object.Update upload selection
115 later commits: 81 Refresh independent audit status, 34 Record integration hold status
```

The required exact root-harness gate was checked before any possible root run.
It matched an active no-argument root harness during this audit window:

```text
864207 php tools/run-tests.php
```

Owner evidence captured for the active harness:

```text
864207 claude 864080 00:13 R+ php tools/run-tests.php
```

A later exact sample no longer had that root PID, but still showed focused lane
harness activity:

```text
891798 claude 754014 00:37 Rs php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php` because another no-argument root
harness was active at the required pre-root gate, and the later focused-test
activity plus moving dirty tree still could not produce accepted aggregate
baseline evidence.

Current manifest counts versus the published dashboard:

| Lane | Current manifest mapped / denominator | Dashboard mapped / denominator |
| --- | ---: | ---: |
| difftastic | 352 / prose 706 behavior artifacts | 160 / 417 |
| dolt | 612 / prose latest-runner total | 242 / 613 |
| esbuild | 291 / 2,567 | 164 / 2,567 |
| gitoxide | 2696 / 2877 | 1432 / 2877 |
| libsqlite | 273 / 1589 | 149 / 1454 |
| LightningCSS | 1712 / 3532 | 773 / 3532 |
| markerPDF | 264 / 315 | 159 / 78 |
| pandoc | 935 / prose 2276 artifacts | 426 / 2028 |
| quadrable | 55 / prose 55 paths plus scenarios | 55 / 55 |
| rclone | 645 / 1601 | 291 / 327 |
| readability | 1984 / 1984 | 1031 / 1984 |
| syncthing | 548 / 658 | 235 / 658 |

`porting-summary.json` and `porting.html` still publish generated time
`2026-05-23 04:57:16 UTC`, source commit `bda83c6b93d4`, dashboard commit
`8ba77df82902`, and average progress `68.8`.

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:25`,
     `progress.md:31` through `progress.md:42`, `.tmux-team/logs/*`,
     `.tmux-team/tmp/port-*.md`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`, and
     `scripts/run-capacity-executor-queue.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable committed slices, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the required root gate matched no-argument root PID `864207`
     owned by `claude`; later samples still showed focused PHP harnesses under
     `claude`, 99 tmux sessions, 4069 status rows, and 259 tracked dirty rows
     while `progress.md` reports all lanes as `stopped` and the current launch
     target as only two implementation lanes plus one auditor.

2. **Critical - the public dashboard is stale and materially contradicts the
   current manifests and statuses.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current coordination files and a
     visible dashboard with denominator, mapped tests, PHP pass/fail, phase,
     audit, blocker, and commit fields.
   - Evidence: the dashboard still points at `bda83c6b93d4` from
     `2026-05-23 04:57:16 UTC`, while sampled `HEAD` is `c2a83c53dcd4`.
     Current rclone is `645 / 1601` but the dashboard shows `291 / 327`;
     Syncthing is `548 / 658` but the dashboard shows `235 / 658`;
     Pandoc is `935 / prose 2276` but the dashboard shows `426 / 2028`;
     markerPDF is `264 / 315` but the dashboard shows `159 / 78`.

3. **High - near-complete lane claims are attached to unaccepted dirty batches,
   not committed verified slices.**
   - Paths: every `lanes/*/lane-status.json`, especially
     `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, and
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:36`, and `goal.md:48` require small correct slices, passing
     tests, and verified committed handoffs before assigning more work.
   - Evidence: sampled `latestCommit` fields say `pending`, `uncommitted`,
     `not committed`, or dirty-batch prose across the portfolio. The latest
     sampled implementation commit is `b75226d1`; all 115 later commits are
     audit/status records, not accepted implementation integration commits.

4. **High - manifest denominator and PHP-count schemas are still
   non-normalized, so cross-lane percentages are not comparable.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json` and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` is a prose string for
     Difftastic, Dolt, Pandoc, and Quadrable, but numeric for most other lanes.
     PHP evidence mixes assertions, behavior tests, BATS PASS cases, upstream
     Mocha counts, and focused-file checks. For example, Readability maps
     `1984 / 1984` upstream tests while recording only 190 native PHP behavior
     tests, and markerPDF maps 264 static behavior/reference units despite
     upstream having 0 committed Python test files.

5. **High - markerPDF and Readability still overstate native parity from
   static, supplied, copied, or upstream-oracle evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/readability/lane-status.json`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native standard-PHP ports,
     generated/bridge evidence to not count as implementation progress,
     meaningful fixture parity, and explicit hard-feature gaps.
   - Evidence: markerPDF reports `264 / 315` mapped and 398 PHP behavior
     tests, but its status still excludes full benchmark execution, Streamlit,
     FastAPI, Python multiprocessing, OCR/model execution, Poetry, and heavy
     dependency paths. Readability reports `1984 / 1984` mapped upstream tests,
     but its native PHP count is 190 behavior tests and its status still relies
     on copied Mozilla fixture inventory plus local upstream JS oracle evidence.

6. **Medium - blocker fields still blur slice-local green checks with full-port
   blockers.**
   - Paths: `lanes/libsqlite/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`, and similar status files.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond local passing tests,
     and explicit hard-feature gaps.
   - Evidence: many blocker fields begin with "No ... blocker" while the same
     field discloses pending root verification, unexecuted full upstream
     runners, excluded live-provider/server/model paths, or broad parity gaps.
     Slice-local green status needs to be separate from full-port blockers.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root PID `864207`, owned
by `claude`. Later no-argument root samples cleared, but focused PHP harnesses
and active writer/status loops were still present and the dirty tree was still
moving. Starting a new aggregate root run would not produce accepted baseline
evidence.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
