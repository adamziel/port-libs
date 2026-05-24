# Independent Audit - 2026-05-24T00:23Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle
fixtures, and shell-outs are treated as non-progress unless explicitly
temporary oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
observed HEAD movement during audit: 168462c8 -> a88642ee -> 3eb94b47 -> 9d7cc81a
latest visible commits: 9d7cc81a Record integration hold status; 3eb94b47 Track pandoc doctemplates support dependency; a88642ee Record integration hold status
commits since 2026-05-23 00:00 UTC: 779
tracked dirty rows: 286
total status rows including untracked: 9482
git diff HEAD --shortstat: 286 files changed, 125959 insertions(+), 12694 deletions(-)
tmux sessions: 151
active repo worker/test-control processes sampled: 86
exact pre-root gate: PID 945646 matched no-argument php tools/run-tests.php earlier; final sample matched active no-argument PID 992298 plus focused Syncthing shards
owner evidence: 945646 claude 945478 00:16 R+ php tools/run-tests.php; 992298 claude 992246 01:41 R+ php tools/run-tests.php; 1025969 claude 1025540 00:16 R+ php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
```

No root run was started by this audit. The required exact duplicate-root probe
matched active no-argument root harnesses during the run, plus focused PHP
shards later. The stability gate also failed independently: `HEAD` moved three times,
the worktree remains a broad dirty aggregate, and dashboard/status publishers
plus lane/test-control activity are still active.

## Findings

1. **Critical - the repo is not stable enough for an aggregate root result.**
   - Paths: `progress.md:34`, `progress.md:40` through `progress.md:51`,
     `.tmux-team/`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits with passing tests, integration cleanup, and honest
     repo-wide verification.
   - Evidence: `progress.md` still says the launch target is two
     implementation lanes plus one auditor and lists all lanes as `stopped`,
     while sampling found 151 tmux sessions, 86 active repo worker/test-control
     processes, 286 tracked dirty rows, and 9482 total status rows. During the
     run the required root gate matched no-argument root PID `945646` owned by
     `claude`; the final sample matched no-argument root PID `992298` plus
     focused Syncthing shards owned by `claude`. A duplicate root run would
     not be trustworthy.

2. **Critical - `porting.html` is still a stale publication artifact and now
   also misses the committed optional-dependency expansion.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:78`,
     `porting-summary.json`, `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:1` through `dependency-backlog.json:123`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current denominator, mapped-test, PHP pass/fail,
     WordPress scenario, phase, audit, current work, blocker, and commit
     fields in `progress.md` and `porting.html`.
   - Evidence: `porting.html` still publishes `main 79768df0c427` generated
     at `2026-05-23 23:43:54 UTC`, while current `HEAD` is `9d7cc81a`.
     Dashboard rows lag current dirty manifests/statuses: Difftastic shows
     `374 / 735` mapped but current manifest is `379 / 739`; esbuild `311` vs
     current `316`; Gitoxide `2751` vs `2768`; libsqlite `286` vs `289`;
     LightningCSS `1732` vs `1736`; markerPDF `280 / 330` vs `283 / 333`;
     Pandoc `1061` vs `1083`; rclone `698` vs `713`; Syncthing PHP pass count
     `4579` vs current `4810`. The dashboard auxiliary table still says 22
     dependency items, but `dependency-backlog.json` now has 23 after
     `pandoc-doctemplates-core`.

3. **High - every lane still reports an unaccepted or pending handoff instead
   of a clean implementation commit.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json`, current dirty lane source/test paths under
     `lanes/*/{src,tests,examples,fixtures,notes}`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small
     reviewable slices with passing tests, then verification, commit, progress
     update, cleanup, and reassignment.
   - Evidence: lane `latestCommit` fields are still `pending`, `uncommitted`,
     `not committed`, or dirty-batch prose. Recent history is dominated by
     audit/status/integration-hold/dependency bookkeeping while implementation
     files remain mixed across 286 tracked dirty rows.

4. **High - near-complete percentages still overstate accepted native upstream
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/lane-status.json:5` through
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/pandoc/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real upstream
     denominators, meaningful fixture parity, upstream tests as source of
     truth, explicit slices, and blockers for hard features.
   - Evidence: dashboard progress is 92-99% per lane and 97.7% average, but
     several lanes remain bounded-slice ports without full native upstream
     parity: esbuild maps 316/2567, libsqlite maps 289/1589, Pandoc maps
     1083/2276 without Cabal runner parity, rclone maps 713/1601 while live
     provider/mount suites are excluded, Gitoxide maps 2768/2877 without full
     Cargo workspace pass, Difftastic still lacks full upstream runner parity,
     and markerPDF uses a static behavior denominator because upstream has no
     committed Python tests and the heavy PDF/model/server workflows remain
     unexecuted.

5. **High - support-library coverage is still backlog-only; no optional
   dependency has lane-grade evidence.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:123`, `dependency-backlog.json:168` through
     `dependency-backlog.json:257`, `porting.html:71` through
     `porting.html:110`, `progress.md:17` through `progress.md:24`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require rich
     document/runtime behavior, native implementation, real denominators, no
     shell-out progress credit, and explicit hard blockers.
   - Evidence: the backlog now has 23 rows, but none has a dependency-specific
     manifest, accepted upstream/spec denominator, mapped fixture matrix, PHP
     pass/fail record, owner, latest commit, malformed/corrupt evidence, or
     dashboard lane. Rich gaps remain for Pandoc ZIP/DOCX/DOC/EPUB/ODT/
     doctemplates/citation/math, markerPDF PDF text/render/OCR/table behavior,
     esbuild source maps, Syncthing protobuf/BEP wire compatibility,
     Difftastic tree-sitter/encoding behavior, and rclone provider metadata/
     checksum/archive behavior. Broad shared rows such as XML/HTML5 DOM,
     Unicode repair, checksum suite, archive/compression, tree-sitter, SQL
     storage codecs, and glob/pathspec need bounded manifests by spec,
     algorithm, provider, or fixture family before progress credit.

6. **Medium - blocker fields still lead with local-green language while
   burying full-port blockers.**
   - Paths: `lanes/*/lane-status.json`, especially blocker fields beginning
     with "No current", "No focused", or "No lane-local"; examples include
     `lanes/gitoxide/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: blocker fields often start by saying there is no local PHP
     blocker, then list unexecuted full upstream runners, live provider suites,
     external model/runtime stacks, broad dependency graphs, or pending root
     verification. That framing makes full-port blockers read like secondary
     notes instead of acceptance blockers.

7. **Medium - manifest/status schemas remain too non-normalized for reliable
   dashboard comparison.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:31`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` alternates between numbers and
     prose; Dolt stores latest-evidence narrative where a comparable total
     should be; PHP counts mix behavior tests, assertions, PASS cases, selected
     files, and lane-local checks; and lane-status fields are mostly prose.

## Test Gate

I did not run `php tools/run-tests.php`. The required exact duplicate-root
probe matched an active no-argument root harness during this audit:

```text
945646 claude 945478 00:16 R+ php tools/run-tests.php
992298 claude 992246 01:41 R+ php tools/run-tests.php
```

A later sample still matched focused PHP shards, including:

```text
1025969 claude 1025540 00:16 R+ php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
```

Verification performed by this audit:

```text
read goal.md, progress.md, porting.html, porting-summary.json
read every lanes/*/UPSTREAM_TEST_MANIFEST.json and every lanes/*/lane-status.json
read dependency-backlog.json and recent git history
sampled live process/worktree/tmux state without reading process environments
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator/watchdog
loops, capacity jobs, focused PHP shards, broad upstream runners, and duplicate
root harnesses. Then accept or reject dirty lane batches one lane at a time,
normalize manifest/status denominator, mapped, runner, PHP pass/fail, blocker,
and commit schemas, split optional dependency candidates into manifest-backed
bounded ports only behind concrete base-lane blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
one accepted commit, and only then run a quiesced root `php tools/run-tests.php`
if the exact duplicate-root gate remains clear.
