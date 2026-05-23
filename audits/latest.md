# Independent Audit - 2026-05-23T23:29Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history.
I also sampled the required root-harness gate, live worker/test processes, tmux
session count, and worktree status.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, `porting-summary.json`, and
`dependency-backlog.json`.

## Current Snapshot

```text
pre-audit-edit HEAD: 6ae54c6361f7
latest visible commits: 6ae54c63 Refresh independent audit status; ae7518bc Record integration hold status; e081b07b Record integration hold status
commits since 2026-05-23 00:00 UTC: 754 total, 431 audit/status-like by subject sampling
git status rows: 7918
tracked dirty rows: 278
git diff HEAD --shortstat: 278 files changed, 120448 insertions(+), 12482 deletions(-)
tmux sessions: 136
```

The required exact pre-root gate returned no rows at the sampling instant:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
<no rows>
```

I still did not start `php tools/run-tests.php` because the tree was not stable
enough for a trustworthy aggregate run. Live process sampling showed active
capacity, dashboard, evaluator, watchdog, integrator, auditor, and lane-agent
loops, including:

```text
716 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-rclone ...
878 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-esbuild ...
13064 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-lightningcss ...
25074 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-markerpdf ...
25894 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-integrator ...
42157 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt ...
42536 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-auditor ...
58691 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-readability ...
58959 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-syncthing ...
59382 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt-runner ...
2222131 bash /home/claude/port-libs/scripts/run-dashboard-updater-loop.sh
2347911 bash scripts/run-team-watchdog.sh
2424048 bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
2938141 bash scripts/run-capacity-controller-loop.sh
2983417 bash scripts/run-capacity-executor-queue.sh --loop
4136137 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-libsqlite ...
4136404 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-pandoc ...
4154684 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-quadrable ...
4170911 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-difftastic ...
```

## Findings

1. **Critical - the repository is still not in an auditable integration state.**
   - Paths: `progress.md:32`, `progress.md:38` through `progress.md:49`,
     `.tmux-team/`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-capacity-executor-queue.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and `scripts/run-team-watchdog.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits with passing tests, cleanup before reassignment, and
     honest repo-wide verification.
   - Evidence: `progress.md` still says the launch target is two
     implementation lanes plus one auditor and lists every lane as `stopped`,
     while this audit sampled 136 tmux sessions, active lane/watchdog/
     dashboard/evaluator/capacity/integrator/auditor loops, 7918 status rows,
     and a 278-file tracked diff. A root run from this state would be another
     moving-tree sample, not an accepted baseline.

2. **Critical - `porting.html` and `porting-summary.json` are not current
   coordination artifacts for the dirty tree.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, and the current lane manifests.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current visible denominator, mapped-test, PHP
     pass/fail, audit, blocker, and commit fields.
   - Evidence: the dashboard publishes snapshot `main 1a112c6ebaef`, but
     sampled `HEAD` is `6ae54c6361f7` and current manifests/statuses have
     advanced again. Dashboard counts disagree with current manifests:
     Difftastic `367/735` versus manifest `373`; esbuild `306` versus `308`;
     Gitoxide `2720/2877` versus `2743/2877` at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14` and `:15`;
     libsqlite `283` versus `285`; LightningCSS `1728` versus `1731`;
     markerPDF `328/278` versus `329/279` at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` and `:15`;
     Pandoc `1039` versus `1047`; rclone `686` versus `692`; Readability PHP
     `200` versus `203`; and Syncthing `654/658` versus `657/658`.

3. **High - all lane handoffs remain pending, uncommitted, or dirty-batch
   prose rather than accepted implementation commits.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, and
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small,
     reviewable slices with passing tests, progress updates, cleanup, and
     reassignment only after verification.
   - Evidence: latest-commit fields across all 12 lanes are `pending`,
     `uncommitted`, `not committed`, or explanatory dirty-worktree text. Top
     history is still audit/status/integration-hold dominated, so the dirty
     implementation aggregate has not been accepted lane-by-lane.

4. **High - near-complete percentages overstate accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13` through `:16`, and
     the lane-status blocker fields.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40` require meaningful fixture parity,
     upstream tests as source of truth, explicit slices, and blockers for hard
     features.
   - Evidence: the dashboard reports 97.4% average progress and 98-99% for
     most lanes while the root harness was not run, full Cargo/Go/Haskell/
     Python/PDF/model/live-provider suites remain unexecuted or excluded, and
     many lane notes explicitly say root verification is supervisor/integrator
     owned. These are useful focused slices, not proof of near-complete
     accepted ports.

5. **High - essential optional-library coverage remains a candidate backlog,
   not manifest-backed dependency-port work.**
   - Paths: `progress.md:17` through `progress.md:22`,
     `porting.html:71` through `porting.html:75`,
     `dependency-backlog.json:7` through `dependency-backlog.json:22`,
     `dependency-backlog.json:25` through `dependency-backlog.json:41`,
     and `dependency-backlog.json:44` through `dependency-backlog.json:73`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:15`, `goal.md:18`, `goal.md:25`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require rich conversion/runtime behavior,
     real denominators, no bridge/shell-out progress credit, and explicit hard
     blockers.
   - Evidence: the backlog correctly identifies bounded gaps such as
     ZIP/package, XML/HTML5 DOM, DOCX/OpenXML, legacy DOC/CFB, EPUB/ODF, PDF
     text/layout/OCR, table geometry, Unicode/encoding, source maps,
     tree-sitter subsets, protobuf wire format, checksums, archives, and
     glob/pathspec matching. But all 18 items are still `candidate` or
     `deferred`; none has its own manifest, upstream/spec denominator, PHP
     pass/fail evidence, accepted owner, commit, or dashboard row. Lanes that
     depend on these should not present full-port blockers as `none`.

6. **Medium - markerPDF and Readability still overstate rich-content parity
   relative to their dependency and fixture evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `:19`, `lanes/markerpdf/lane-status.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13` through `:16`,
     `lanes/readability/lane-status.json`, and `porting.html:62` and `:66`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:15`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require
     native implementation progress, meaningful fixture parity, rich document
     behavior, and explicit blockers for unported binary/model/runtime
     formats.
   - Evidence: markerPDF reports `329` static behavior/reference units and
     `279` mapped semantics while its own source field lists unexecuted heavy
     dependencies and runtime paths: torch, Surya, pdftext, pypdfium2, tabled,
     Texify, OCRMyPDF/Tesseract/Ghostscript, Streamlit, FastAPI/Uvicorn,
     multiprocessing, and model downloads. Readability reports `1984/1984`
     mapped upstream tests, but status still describes copied fixture/oracle
     checks and focused PHP evidence rather than a clean accepted root
     baseline. Both lanes need dependency-backed manifests before rich
     conversion parity is credited as near-complete.

7. **Medium - manifest/status schemas remain non-normalized and hard to audit
   across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through `:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through `:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, and `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` alternates between integers and
     long prose; Dolt keeps the actual denominator as prose while `mapped` is
     numeric; Pandoc and Quadrable use prose totals; runner status alternates
     between strings and objects; PHP counts mix behavior tests, assertions,
     PASS cases, and file counts. The portfolio percentages remain
     non-comparable until units are normalized.

8. **Medium - blocker fields still mix slice-local green status with full-port
   blockers.**
   - Paths: `lanes/dolt/lane-status.json`,
     `lanes/esbuild/lane-status.json`, `lanes/gitoxide/lane-status.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     and `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: several lanes begin with "No current implementation blocker" or
     "No focused blocker" and then list unexecuted full runners, live/provider
     suites, large dependency graphs, external model stacks, credential-bearing
     integrations, or root aggregate verification. Split blockers into
     `slice`, `root`, `upstream-runner`, `dependency`, and `full-port` fields.

## Test Gate

I did not run `php tools/run-tests.php`. The exact duplicate-root gate was clear
at the sample, but the tree was not stable enough for a trustworthy aggregate
run: active writer/status/test-control loops were present, all lanes had
pending/uncommitted handoffs, and the dirty tree had 278 tracked files changed.

Verification performed by this audit:

```text
read goal.md, progress.md, porting.html, porting-summary.json
read every lanes/*/UPSTREAM_TEST_MANIFEST.json and every lanes/*/lane-status.json
read dependency-backlog.json and recent git history
sampled live process/worktree/tmux state
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json porting-summary.json dependency-backlog.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator/watchdog
loops, capacity jobs, focused PHP shards, broad upstream runners, and duplicate
root harnesses. Then validate manifests from the frozen tree, accept or reject
dirty lane batches one lane at a time, normalize denominator/mapped/PHP/
runner/blocker/commit schemas, add manifest-backed optional dependency lanes
only behind concrete base-lane blockers, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from one accepted
commit, and only then run a quiesced root `php tools/run-tests.php` if the exact
duplicate-root gate remains clear.
