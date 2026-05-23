# Independent Audit - 2026-05-23T19:56:26Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled
`lanes/*/lane-status.json`, recent Git history, worktree state, and process
state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

`HEAD` at the main sample was `10da7cb900d6` (`Record integration hold
status`), then advanced during validation to `262083d5c060` (`Record
integration hold status`). Recent history is still coordination dominated: the
latest 40 sampled commits are audit/status/integration-hold commits, not
accepted lane implementation commits.

The worktree is not quiescent. Latest samples reported `3075`
`git status --short --untracked-files=all` rows, `238` tracked dirty rows, and
`238 files changed, 94933 insertions(+), 12606 deletions(-)`. Process sampling
reported `69` repo worker/status/test-control matches and `90` tmux sessions,
while `progress.md:25` still documents a launch target of two implementation
lanes plus one auditor and `progress.md:31` through `progress.md:42` still mark
every lane as `stopped`.

The required exact root-harness gate returned active no-argument root PID
`122782`, so I did not run `php tools/run-tests.php`.

```text
122782 php tools/run-tests.php
```

Owner evidence:

```text
122782 claude 122702 00:27 R+ php tools/run-tests.php
```

Current manifest/status sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Published dashboard mapped / denominator |
| --- | ---: | ---: |
| difftastic | 340 / prose `660` artifacts | 160 / 417 |
| dolt | 608 / prose `total` status string | 242 / 613 |
| esbuild | 282 / 2,567 | 164 / 2,567 |
| gitoxide | 2670 / 2877 | 1432 / 2877 |
| libsqlite | 269 / 1589 | 149 / 1454 |
| LightningCSS | 1638 / 3532 | 773 / 3532 |
| markerPDF | 259 / 310 static behavior/reference units | 159 / 78 |
| pandoc | 887 / prose `2276` artifacts | 426 / 2028 |
| quadrable | 55 / prose `55` paths plus 34 scenarios | 55 / 55 |
| rclone | 626 / 1601 | 291 / 327 |
| readability | 1984 / 1984 upstream Mocha tests | 1031 / 1984 |
| syncthing | 510 / 658 | 235 / 658 |

## Findings

1. **Critical - active writers and duplicate root activity still block a
   trustworthy aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-php-clean-head-root.sh`, and sampled `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require capped supervision, reviewable tested commits,
     integration cleanup, and honest repo-wide verification.
   - Evidence: a no-argument root harness was active as PID `122782` owned by
     `claude`; broad SQLite TCL testfixture work and many lane/status agents
     were also active. Starting another root run would duplicate live aggregate
     test activity, and any root result taken from this moving dirty tree would
     not be an accepted baseline.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:50`,
     `porting.html:54` through `porting.html:65`, and
     `porting-summary.json:2` through `porting-summary.json:18`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     tracking of upstream denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit status, current work, blocker, and latest commit.
   - Evidence: the dashboard publishes generated time `2026-05-23 04:57:16
     UTC` and source commit `bda83c6b93d4` while sampled `HEAD` advanced from
     `10da7cb900d6` to `262083d5c060`. The rows materially disagree with current manifests:
     rclone is `626 / 1601` while the dashboard says `291 / 327`, markerPDF is
     `259 / 310` while the dashboard says `159 / 78`, and Syncthing is
     `510 / 658` while the dashboard says `235 / 658`.

3. **High - manifest denominator schemas remain non-normalized, so portfolio
   percentages are not auditable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2204`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, meaningful fixture parity, upstream tests as the source of
     truth, and comparable dashboard fields.
   - Evidence: denominators mix runnable tests, source files, fixture pairs,
     parser corpus artifacts, prose status strings, static behavior units, and
     assertion counts. Dolt's `mapped` field is `608`, but its `total` field is
     a long focused-runner status paragraph rather than a stable numeric
     denominator. Difftastic, Pandoc, and Quadrable store prose denominators.

4. **High - lane status now reports broad progress as pending dirty batches,
   not accepted reviewable slices.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small correct slices, passing tests, commits, and
     integration cleanup before assigning the next highest-value slice.
   - Evidence: latest-commit fields are `pending`, `uncommitted`, or dirty-batch
     prose across sampled lanes, while focused lane tests are presented as green.
     That is useful handoff evidence, but it is not accepted repo progress until
     the batch is reviewed, integrated, root-gated where appropriate, and
     committed.

5. **High - markerPDF and readability are overstating parity from static or
   copied evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:353`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:600`,
     `lanes/markerpdf/lane-status.json:9` through
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/readability/lane-status.json:9` through
     `lanes/readability/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native standard-PHP ports,
     generated/bridge/shell-out non-progress handling, meaningful fixture
     parity, and explicit hard-feature gaps.
   - Evidence: markerPDF is `259 / 310` static behavior/reference units with
     runner status `not-executed`, yet the lane status frames the PHP blocker as
     inactive while the full Python benchmark, Streamlit, FastAPI/Uvicorn,
     multiprocessing, OCR/model, and CI paths remain unrun. Readability now
     claims `1984 / 1984` mapped upstream Mocha tests, but the sampled lane
     status says the newest serializer slice is uncommitted and only `184`
     native PHP behavior tests are recorded in the manifest.

6. **Medium - blocker fields still blur slice-local green checks with full-port
   parity gaps.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond passing local tests,
     and explicit hard-feature gaps.
   - Evidence: fields begin with "No blocker" or equivalent while also listing
     pending aggregate root verification, unexecuted full upstream runners,
     live provider/model requirements, broad hydration needs, and excluded
     service coverage. Slice-local health and full-port blockers need separate
     fields.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact gate found active no-argument root PID `122782`, owned by
`claude`, running `php tools/run-tests.php`. A later exact gate was clear, but I
still did not start the root harness because the stability gate failed: active
workers/status publishers, broad upstream runners, HEAD movement, and dirty lane
batches persisted through the audit.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP harnesses
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, separate slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
exact duplicate-root gate remains empty across two polls.
