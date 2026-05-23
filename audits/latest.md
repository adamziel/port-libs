# Independent Audit - 2026-05-23T20:02:04Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled
`lanes/*/lane-status.json`, recent Git history, worktree state, and process
state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle fixtures,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

`HEAD` was `7957716e40ca` (`Record integration hold status`) at the final
pre-edit sample, after moving from the earlier audit sample `88d530f84d43`.
Recent history remains coordination dominated: the nearest sampled
implementation commit is `b75226d1` (`Port rclone OneDrive Object.Update upload
selection`), 92 sampled audit/status/integration-hold commits behind `HEAD`.

The worktree is not quiescent. Latest samples reported `3094`
`git status --short --untracked-files=all` rows, `237` tracked dirty rows,
`2857` untracked rows, and `237 files changed, 95231 insertions(+), 12472
deletions(-)`. Process sampling still reported `69` repo
worker/status/test-control matches and `90` tmux sessions while
`progress.md:25` documents a target of two implementation lanes plus one
auditor and `progress.md:31` through `progress.md:42` mark every lane as
`stopped`.

The required exact root-harness gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

was clear at the final pre-edit sample, so there was no active no-argument root
PID to record then. I still did not run `php tools/run-tests.php` because the
tree failed the stability gate: active lane agents, dashboard/evaluator,
watchdog, capacity, auditor/integrator loops, and broad upstream runner activity
were still present. Earlier in this audit window the same exact gate matched
focused lane harnesses, including surviving owner evidence:

```text
202489 claude 79636 00:31 Rs php tools/run-tests.php lanes/syncthing/tests
```

Current manifest/status sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Published dashboard mapped / denominator |
| --- | ---: | ---: |
| difftastic | 342 / prose `669` artifacts | 160 / 417 |
| dolt | 608 / prose root/focused-runner status string | 242 / 613 |
| esbuild | 283 / prose `2,567` entry points | 164 / 2,567 |
| gitoxide | 2670 / 2877 | 1432 / 2877 |
| libsqlite | 269 / 1589 | 149 / 1454 |
| LightningCSS | 1645 / 3532 | 773 / 3532 |
| markerPDF | 260 / 311 static behavior/reference units | 159 / 78 |
| pandoc | 891 manifest mapped; 887 lane-status mapped / prose `2276` artifacts | 426 / 2028 |
| quadrable | 55 / prose `55` paths plus scenarios | 55 / 55 |
| rclone | 626 manifest mapped; 628 lane-status PHP pass / 1601 | 291 / 327 |
| readability | 1984 / 1984 upstream Mocha tests | 1031 / 1984 |
| syncthing | 514 / 658 | 235 / 658 |

## Findings

1. **Critical - active writers and broad test loops still block a trustworthy
   aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     and sampled `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require capped supervision, reviewable tested commits,
     integration cleanup, and honest repo-wide verification.
   - Evidence: the final process sample still showed active agents for many
     lanes plus dashboard/evaluator/watchdog/capacity/auditor/integrator loops
     and broad Dolt BATS activity. With 237 tracked dirty files and ongoing
     status/test loops, a no-argument root run would not establish an accepted
     integration baseline even though the exact root gate was clear at the final
     sample.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and fail
   the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`, and
     `porting-summary.json:2` through `porting-summary.json:8`,
     plus lane rows beginning at `porting-summary.json:10`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     tracking of upstream denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit status, current work, blocker, and latest commit.
   - Evidence: the dashboard still publishes generated time `2026-05-23
     04:57:16 UTC` and source commit `bda83c6b93d4`, while sampled `HEAD` is
     `7957716e40ca`. The rows materially disagree with current manifests:
     rclone is `626 / 1601` in the manifest while the dashboard says
     `291 / 327`, markerPDF is `260 / 311` while the dashboard says
     `159 / 78`, and Difftastic is `342 / 669` while the dashboard says
     `160 / 417`.

3. **High - near-complete lane percentages are unsupported by accepted
   implementation commits.**
   - Paths: `lanes/difftastic/lane-status.json:4` and `:13`,
     `lanes/dolt/lane-status.json:4` and `:13`,
     `lanes/gitoxide/lane-status.json:4` and `:13`,
     `lanes/libsqlite/lane-status.json:4` and `:13`,
     `lanes/markerpdf/lane-status.json:4` and `:13`,
     `lanes/rclone/lane-status.json:4` and `:13`,
     `lanes/readability/lane-status.json:4` and `:13`, and
     `lanes/syncthing/lane-status.json:4` and `:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small correct slices, passing tests, commits, and
     integration cleanup before assigning the next highest-value slice.
   - Evidence: sampled lane statuses now report 95-99% progress across many
     lanes, but `latestCommit` fields remain `pending`, `uncommitted`, or
     dirty-batch prose. Recent Git history shows 92 sampled
     audit/status/integration-hold commits since the nearest implementation
     commit `b75226d1`.

4. **High - manifest denominator schemas remain non-normalized and internally
   inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2204`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:30`,
     and `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, meaningful fixture parity, upstream tests as source of
     truth, and comparable dashboard fields.
   - Evidence: denominators mix numeric test counts, prose path inventories,
     fixture artifacts, behavior/reference units, and status paragraphs. Dolt's
     `mapped` field is numeric but its `total` field is a long focused/root
     status paragraph. Pandoc's manifest mapped count is `891` while its
     lane-status text reports `887`, and rclone's manifest mapped count is
     `626` while its lane-status PHP pass count is `628`.

5. **High - markerPDF and readability still overstate parity from static,
   supplied, or copied evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/lane-status.json:5` and `:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:16`, and
     `lanes/readability/lane-status.json:5` and `:13`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native standard-PHP ports,
     generated/bridge/shell-out non-progress handling, meaningful fixture
     parity, and explicit hard-feature gaps.
   - Evidence: markerPDF counts `260 / 311` static behavior/reference units and
     plan/supplied boundaries while the real upstream Python/Streamlit/FastAPI,
     OCR/model, multiprocessing, and benchmark runners remain unexecuted.
     Readability claims `1984 / 1984` upstream Mocha tests mapped, but the
     lane status only records 185 native PHP behavior tests and an
     uncommitted copied-fixture serializer slice.

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
   - Evidence: blockers begin with "No ... blocker" while the same fields
     mention pending root verification, unexecuted full upstream runners, live
     provider/model requirements, broad hydration needs, and excluded service
     coverage. Slice-local status and full-port blockers need separate fields.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact root gate was clear at the final pre-edit sample, but the
tree was not stable enough for an aggregate baseline: active lane agents,
status publishers, broad upstream runners, capacity jobs, integrator/auditor
loops, and a large dirty worktree persisted. Starting a root harness from this
state would create another non-comparable result.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP harnesses.
Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, separate slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
exact duplicate-root gate remains empty across two polls.
