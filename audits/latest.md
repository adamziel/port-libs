# Independent Audit - 2026-05-23T23:19Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history.
I also sampled the required pre-root harness gate, process state, tmux session
count, and worktree status.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
pre-audit-edit HEAD: 09801a51bafa
latest visible commits: audit/status/integration-hold commits dominate the top of history
commits since 2026-05-23 00:00 UTC: 750
sampled audit/status/dependency-status commits since 2026-05-23 00:00 UTC: 230
latest sampled non-audit/status implementation commit: b75226d1
git status rows: 7900
tracked dirty rows: 277
git diff HEAD --shortstat: 277 files changed, 119472 insertions(+), 12459 deletions(-)
tmux sessions: 140
```

The required exact pre-root gate initially matched an active no-argument root
harness, so I did not start `php tools/run-tests.php`.

Initial gate sample:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
4012235 php tools/run-tests.php
4021835 php tools/run-tests.php lanes/readability/tests
4021943 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
4021995 php tools/run-tests.php lanes/syncthing/tests/ConfigFoldersTest.php ...
4022232 php tools/run-tests.php lanes/syncthing/tests/PullTemporaryFileTest.php ...
4023069 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
4023324 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests
4023495 php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
```

Owner sampling raced the root PID after it exited, but the still-active focused
shard evidence showed the same process family running as `claude`:

```text
ps -o pid,user,ppid,stat,etime,cmd -p 4022232
4022232 claude 4021814 R+ 01:02 php tools/run-tests.php lanes/syncthing/tests/PullTemporaryFileTest.php ...
```

A later exact duplicate-root gate returned no rows, but the tree still failed
the stability check, so no root aggregate was started.

## Findings

1. **Critical - the integration baseline is still not trustworthy.**
   - Paths: `progress.md:32`, `progress.md:38` through `progress.md:49`,
     `.tmux-team/`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `scripts/run-evaluator-loop.sh`,
     `scripts/run-team-watchdog.sh`, and the active root/focused harness
     evidence above.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits, cleanup before reassignment, and honest repo-wide
     verification.
   - Evidence: `progress.md` still says the launch target is 2 implementation
     lanes plus 1 auditor and lists every lane as `stopped`, while the audit
     sampled 140 tmux sessions, active lane/watchdog/dashboard/evaluator/
     capacity/integrator/auditor loops, root/focused PHP harnesses, rclone Go
     selector jobs, and a 277-file tracked diff.

2. **Critical - dashboard/status artifacts are snapshots of a moving dirty
   aggregate, not an accepted source commit.**
   - Paths: `porting.html:34`, `porting.html:35`, `porting.html:38`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current visible denominator, mapped-test, PHP
     pass/fail, audit, blocker, and commit fields.
   - Evidence: `porting.html` and `porting-summary.json` claim snapshot
     `main 1a112c6ebaef`, while current sampled `HEAD` is
     `09801a51bafa` and the dashboard files themselves are dirty relative to
     `HEAD`. Dashboard rows also lag current manifests: Difftastic shows
     `367 / 735` while the manifest has `369`, Gitoxide shows `2720 / 2877`
     while the manifest has `2730`, rclone shows `686 / 1601` while the
     manifest has `688`, and markerPDF dashboard `328/278` disagrees with
     current manifest `329/279`.

3. **High - every lane handoff remains pending or uncommitted dirty-batch
   prose.**
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
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small,
     reviewable slices with passing tests, progress updates, cleanup, and
     reassignment only after verification.
   - Evidence: latest-commit fields are `pending`, `uncommitted`, `not
     committed`, or dirty-batch explanations across all 12 lanes. Recent Git
     history remains dominated by audit/status/integration-hold commits, with
     the latest sampled implementation commit still `b75226d1`.

4. **High - near-complete progress percentages overstate full native parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/gitoxide/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/readability/lane-status.json:4`, and
     `lanes/syncthing/lane-status.json:4`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40` require meaningful native parity,
     upstream tests as the source of truth, explicit slices, and blockers for
     hard features.
   - Evidence: the dashboard reports 97.4% average progress and 98-99% for
     most lanes, but root verification was active elsewhere and not accepted;
     full Cargo/Go/Haskell/Python/PDF/model/live-provider suites remain
     unexecuted or explicitly excluded. These are useful focused slices, not
     proof of near-complete native ports.

5. **High - essential optional-library coverage is identified but not yet
   auditable implementation work.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:22`,
     `dependency-backlog.json:25` through `dependency-backlog.json:41`,
     `dependency-backlog.json:110` through `dependency-backlog.json:124`,
     `dependency-backlog.json:144` through `dependency-backlog.json:157`,
     `dependency-backlog.json:218` through `dependency-backlog.json:233`,
     `dependency-backlog.json:254` through `dependency-backlog.json:267`,
     and `progress.md:17` through `progress.md:22`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:15`, `goal.md:18`, `goal.md:25`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require rich conversion/runtime behavior,
     real denominators, no bridge/shell-out progress credit, and explicit hard
     blockers.
   - Evidence: the backlog names the right rich-function gaps: ZIP/package,
     XML/HTML5 DOM, DOCX/OpenXML, legacy DOC/CFB, EPUB/ODF, PDF text/layout/OCR
     handoffs, table geometry, Unicode/encoding, source maps, tree-sitter
     subsets, protobuf wire format, checksums, archives, and glob/pathspec
     matching. But all 18 items are only `candidate` or `deferred`; none has a
     dependency-specific manifest, upstream/spec denominator, mapped PHP
     pass/fail evidence, accepted owner, commit, or dashboard lane. `blocker:
     "none"` is misleading until those gates exist.

6. **Medium - markerPDF still gives high progress credit to plan-only and
   supplied-boundary slices.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19` and
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:14`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native implementation progress,
     meaningful fixture parity, and explicit blockers for hard features.
   - Evidence: markerPDF reports 98% progress, 413 PHP passes, and no PHP
     blocker, while the latest work explicitly avoids executing shell scripts,
     Python, model workers, PDF renderers, OCR tools, Streamlit/FastAPI, live
     HTTP, and multiprocessing. Those are valid safe boundaries, but they
     should not carry near-complete port credit until bounded dependency ports
     or real supplied-fixture parity make the PDF/model/runtime behavior
     auditable.

7. **Medium - manifest/status schemas remain non-normalized and hard to
   compare across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     and `lanes/*/lane-status.json:4` through `lanes/*/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` alternates between numbers and
     prose; Pandoc uses both prose and `totalCount`; Dolt carries prose where
     the dashboard wants a denominator; runner status alternates between
     missing, strings, objects, and nulls; PHP counts mix behavior tests,
     assertions, PASS cases, and lane file counts. Portfolio percentages are
     not comparable until those units are normalized.

8. **Medium - blocker language still mixes slice-local green checks with
   full-port blockers.**
   - Paths: `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: several lanes say there is no local blocker while the same
     field lists unresolved full-port work: full workspace runners,
     release-extra target suites, live providers/FUSE/services, SQLite all/
     release permutations, PDF/model/OCR runtime stacks, credential-bearing
     integrations, and root aggregate verification. Use separate `slice`,
     `root`, `upstream-runner`, `dependency`, and `full-port` blocker fields.

## Test Gate

I did not run `php tools/run-tests.php`. The required duplicate-root gate
initially matched an active no-argument root harness, and when the later exact
gate became clear the tree still was not stable enough for a trustworthy
aggregate run.

Verification performed by this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator/watchdog
loops, capacity jobs, broad upstream runners, focused PHP shards, and duplicate
root harnesses. Then validate manifests from the frozen tree, enforce atomic
manifest/status/dashboard writes, accept or reject dirty lane batches one lane
at a time, normalize denominator/mapped/PHP/runner/blocker/commit schemas, give
activated optional dependencies their own manifest-backed gated rows, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
one accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
