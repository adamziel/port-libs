# Independent Audit - 2026-05-23T19:50:00Z

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

`HEAD` at the audit sample was `c60e2a79487b` (`Record integration hold
status`). The branch was `main...origin/main [ahead 502, behind 68]`. Recent
history is still status/audit dominated: the nearest sampled implementation
commit is `b75226d1` (`Port rclone OneDrive Object.Update upload selection`),
well behind the current status-only head.

The worktree is not quiescent. Latest samples reported `3022`
`git status --short --untracked-files=all` rows, `233` tracked dirty rows, and
`233 files changed, 94178 insertions(+), 12494 deletions(-)`. Process sampling
showed `28` repo worker/status/test-control matches and `90` tmux sessions,
while `progress.md:25` still documents a launch target of two implementation
lanes plus one auditor and `progress.md:31` through `progress.md:42` still mark
every lane as `stopped`.

Manifest values changed during this audit. Examples: difftastic moved from
`338 / 657` to `340 / 660`, LightningCSS moved from `1617` to `1638` mapped,
markerPDF moved from `259 / 310` after earlier `258 / 309`, Pandoc moved from
`881` to `887` mapped, and Syncthing moved from `500` to `510` mapped.

The required exact pre-root gate returned active root PID `68250`, so I did not
run `php tools/run-tests.php`. A later validation gate briefly returned active
root PID `79277`; it exited before owner sampling. A post-commit handoff sample
then returned active root PID `100383`.

```text
68250 php tools/run-tests.php
100383 php tools/run-tests.php
```

Owner evidence:

```text
68250 claude 68164 00:31 R+ php tools/run-tests.php
100383 claude 100284 00:05 R+ php tools/run-tests.php
```

Current manifest/status sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Published dashboard mapped / denominator |
| --- | ---: | ---: |
| difftastic | 340 / prose `660` artifacts | 160 / 417 |
| dolt | 607 / prose `total` status string | 242 / 613 |
| esbuild | 282 / 2,567 | 164 / 2,567 |
| gitoxide | 2670 / 2877 | 1432 / 2877 |
| libsqlite | 268 / 1589 | 149 / 1454 |
| LightningCSS | 1638 / 3532 | 773 / 3532 |
| markerPDF | 259 / 310 static behavior/reference units | 159 / 78 |
| pandoc | 887 / prose `2276` artifacts | 426 / 2028 |
| quadrable | 55 / prose `55` paths plus 34 scenarios | 55 / 55 |
| rclone | 620 / 1601 | 291 / 327 |
| readability | 1984 / 1984 upstream Mocha tests | 1031 / 1984 |
| syncthing | 510 / 658 | 235 / 658 |

## Findings

1. **Critical - active writers and duplicate test activity still block any
   trustworthy aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     and sampled `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require capped supervision, small reviewable commits,
     integration cleanup, and honest repo-wide verification.
   - Evidence: active process/tmux counts contradict the stopped-lane table and
     documented cap; manifests changed while being read; the exact pre-root gate
     found no-argument root PID `68250` owned by `claude`, so starting another
     root harness would duplicate live aggregate test activity.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:50`, and
     `porting-summary.json:3` through `porting-summary.json:18`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard fields for benchmark source, upstream denominator, mapped tests,
     PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker,
     and commit.
   - Evidence: the dashboard still publishes generated time `2026-05-23
     04:57:16 UTC` and source commit `bda83c6b93d4` while sampled `HEAD` is
     `c60e2a79487b`. It also collapses denominator, mapped tests, and PHP
     pass/fail into one `Mapped` column. Current manifests disagree materially:
     rclone is `620 / 1601` while the dashboard says `291 / 327`, markerPDF is
     `259 / 310` while the dashboard says `159 / 78`, and Syncthing is
     `510 / 658` while the dashboard says `235 / 658`.

3. **High - manifest denominator schemas are not normalized enough to audit
   percentages.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` and
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2200`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:32`, and
     sampled `lanes/*/lane-status.json:4` through `lanes/*/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, meaningful fixture parity, upstream tests as source of
     truth, and comparable dashboard fields.
   - Evidence: denominators mix runnable tests, executable files, fixture pairs,
     source/config boundaries, static behavior units, assertion counts, and
     prose strings. Dolt currently has `mapped: 607` near the top but stores
     `total` as a long status narrative near the end, not a stable numeric
     denominator. Difftastic, Pandoc, and Quadrable store prose denominators.
     Gitoxide, LightningCSS, and Syncthing lane-status PHP pass counts are
     assertion counts, while other lanes use behavior-test counts.

4. **High - near-complete lane estimates are attached to unaccepted dirty
   batches, not committed verified slices.**
   - Paths: `lanes/difftastic/lane-status.json:4` and
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:4` and `lanes/dolt/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:4` and
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/rclone/lane-status.json:4` and
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:4` and
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:4` and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small reviewable commits with passing verification
     before assigning the next slice.
   - Evidence: many lane statuses claim `98` to `99` percent progress while
     `latestCommit` is `pending`, `uncommitted`, or dirty-batch prose. Focused
     lane tests are useful handoff evidence, but not accepted repo progress
     until reviewed, root-gated where appropriate, and committed.

5. **High - markerPDF still overweights plan-only and supplied-boundary
   evidence as native port progress.**
   - Paths: `lanes/markerpdf/lane-status.json:4` through
     `lanes/markerpdf/lane-status.json:13`, and
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native standard-PHP ports, no
     generated/bridge progress credit, meaningful parity, and explicit hard
     feature gaps.
   - Evidence: markerPDF is still marked `98%` even though the manifest records
     `0` committed Python test files and the status says full upstream
     benchmark, Streamlit, FastAPI/Uvicorn, multiprocessing, OCR/model, and
     GitHub Actions execution remain unrun. Static and supplied-document
     boundaries are useful inventory; they should not carry the same progress
     weight as executable native converter parity.

6. **Medium - blocker fields still blur slice-local green checks with full-port
   parity gaps.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond passing local tests,
     and explicit hard-feature gaps.
   - Evidence: fields begin with "No ... blocker" while also recording pending
     aggregate root verification, unexecuted full upstream runners, live
     provider/model requirements, broad hydration needs, or excluded service
     coverage. Slice-local health and full-port blockers need separate fields.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact pre-root gate found active no-argument root PID `68250`,
owned by `claude`, running `php tools/run-tests.php`. The stability gate also
failed because active workers/status publishers and dirty lane batches persisted
through the audit. A later validation gate briefly matched active root PID
`79277`, which exited before owner evidence could be sampled; a post-commit
handoff sample matched active root PID `100383` owned by `claude`.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP harnesses
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, separate slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
exact duplicate-root gate remains empty across two polls.
