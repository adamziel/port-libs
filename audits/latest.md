# Independent Audit - 2026-05-24T05:02Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 current `lanes/*/UPSTREAM_TEST_MANIFEST.json`
files, current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent
Git history, and root-runner process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T05:02:46Z
HEAD observed: b37894279fa7
recent commits: b3789427 Refresh independent audit status; 4a3ee732 Record integration hold status; 05135080 Record integration hold status
branch divergence: main...origin/main [ahead 707, behind 68]
tracked dirty rows: 306
default status rows including untracked: 13514
git diff --shortstat: 306 files changed, 168841 insertions(+), 24281 deletions(-)
manifest/status JSON validation: jq empty passed for all lane manifests, all lane-status files, porting-summary.json, and dependency-backlog.json
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' during audit sampling:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' during final validation:
953299 php tools/run-tests.php lanes/esbuild/tests/TypeScriptModuleLowererTest.php
954716 php tools/run-tests.php lanes/esbuild/tests

ps -o pid,user,etime,args -p 953299 / -p 954716:
PID USER ELAPSED COMMAND
<no process row; each PID exited before owner sampling>

stability poll:
05:00Z: b37894279fa7; 306 tracked dirty rows; 13512 total status rows; 306 files changed, 168750 insertions(+), 24281 deletions(-)
05:01Z: b37894279fa7; 306 tracked dirty rows; 13514 total status rows; 306 files changed, 168818 insertions(+), 24281 deletions(-)
05:02Z: b37894279fa7; 306 tracked dirty rows; 13514 total status rows; 306 files changed, 168841 insertions(+), 24281 deletions(-)
```

I did not start `php tools/run-tests.php`. The process gate was initially empty,
then focused esbuild lane runners appeared during final validation and exited
before owner sampling. The checkout also moved during audit sampling and remains
a broad, unaccepted dirty aggregate with all lane handoffs pending
root/integrator acceptance. A root run from this state would be diagnostic at
best, not an acceptance checkpoint.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:39`, `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and current Git status.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small reviewable slices, finished-agent
     verification, commits, and honest repo-wide checks.
   - Evidence: `main` is still ahead 707 and behind 68; `git status` reports
     306 tracked dirty rows and 13,514 rows including untracked files; the
     diff grew during this audit to 168,841 insertions and 24,281 deletions.
     Current lane statuses repeatedly say `pending`, `uncommitted`, or
     `not committed` because root verification and integrator acceptance were
     not assigned.

2. **Critical - no coherent root-harness result exists for this snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording.
   - Evidence: the required `pgrep -af '^php tools/run-tests\.php( |$)'`
     gate returned no rows during initial audit sampling, but final validation
     then matched focused esbuild PIDs `953299` and `954716`, each exited
     before owner sampling. The tree also moved between the 05:00Z and 05:02Z
     samples. Lane statuses cite focused lane-green checks while root aggregate
     verification remains explicitly pending.

3. **Critical - `porting.html` and `porting-summary.json` are stale and do
   not satisfy the dashboard contract.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:45` through `porting.html:46`,
     `porting.html:56` through `porting.html:67`, and
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard rows with separate benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, phase, audit, current work, blocker, and
     commit.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 23:43:54 UTC` and source commit `79768df0c427`, while the
     reviewed HEAD is `b37894279fa7`. The HTML still has only `Benchmark` and
     `Mapped` columns rather than distinct benchmark-source and
     upstream-denominator columns. Commit cells still contain truncated prose
     states such as `pending`, `uncommi`, `not com`, and `HEAD 8d`.

4. **High - manifest, lane-status, and dashboard counts disagree across
   active lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/lane-status.json:5`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2438`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/lane-status.json:6`,
     `lanes/readability/lane-status.json:6`, and
     `lanes/syncthing/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45`.
   - Evidence: current data no longer matches the published dashboard. For
     example, Difftastic's manifest says total `829...` and mapped `467`, its
     lane status says `834` artifacts and 2,744 PHP assertions, while the
     dashboard says `735` total and `374` pass/mapped. Rclone now maps 806
     tests, but the dashboard says 698. LightningCSS maps 2,019, but the
     dashboard says 1,732. MarkerPDF is 354/305 with 442 behavior tests, but
     the dashboard says 330/280/416. Pandoc maps 1,449, but the dashboard says
     1,061. Syncthing reports 6,332 PHP assertions, but the dashboard says
     4,579.

5. **High - manifest/status schemas are still too free-form for reliable
   acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2438`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`, and
     `lanes/*/lane-status.json:5`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is numeric in some manifests and
     a long narrative string in others. Dolt has a top-level mapped count but
     keeps the current denominator narrative thousands of lines later. Native
     implementation fields differ by lane (`currentSlice`, `latestSlice`,
     `phpBehaviorTests`, `mappedUpstreamSemantics`, etc.). The dashboard
     generator cannot safely distinguish denominator units, mapped upstream
     units, behavior tests, assertions, upstream runner evidence, blocker
     state, and accepted commit state from these strings.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` and
     `goal.md:49`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes while the same lane records still say root verification, full
     Cargo/Go/BATS/Haskell/release-extra suites, live provider paths, and
     upstream parity remain unrun or explicitly blocked.

7. **High - markerPDF still over-credits external/runtime orchestration as
   mapped native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php:129` through
     `lanes/markerpdf/src/ChunkConversionPlanner.php:164`, and
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:14` through
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:20`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and this run's support-library granularity requirement.
   - Evidence: the markerPDF manifest/status still include benchmark CLI
     argument/default plans, marker server startup/lifespan/remote polling
     plans, chunk-convert shell lifecycle plans, OCR install readiness,
     Poetry/package metadata, model runtime graphs, Texify/Nougat planning,
     and CI workflow/publish plans. `ChunkConversionPlanner` models `eval`,
     background jobs, signal traps, and `pkill -P $$`; those are plan/preflight
     evidence, not native PDF conversion progress.

8. **High - essential optional-library coverage remains backlog-only rather
   than lane-grade support-library progress.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:23`,
     `dependency-backlog.json:25` through `dependency-backlog.json:43`,
     `dependency-backlog.json:111` through `dependency-backlog.json:121`,
     `dependency-backlog.json:171` through `dependency-backlog.json:182`,
     `porting.html:75` through `porting.html:78`, and
     `progress.md:17` through `progress.md:24`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library requirement.
   - Evidence: `dependency-backlog.json` has 23 items with status counts
     `candidate: 13` and `deferred: 10`, but `porting.html` still says 22
     items with `candidate: 12`. The backlog has no corresponding
     support-library manifests, lane-status files, PHP pass/fail evidence, or
     dependency-specific upstream/spec denominators. Rich gaps remain for
     Pandoc package/OpenXML/ODT/doctemplates/citation/math, markerPDF PDF
     text/OCR/layout/table/Unicode work, rclone archive/XML/provider
     metadata, and shared charset/hash/glob/compression surfaces.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/VfsWebDavProppatchXml.php:7` through
     `lanes/rclone/src/VfsWebDavProppatchXml.php:15`,
     `lanes/rclone/src/VfsWebDavReadResponse.php:17` through
     `lanes/rclone/src/VfsWebDavReadResponse.php:51`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9` through
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:20`, and
     `dependency-backlog.json:384` through `dependency-backlog.json:399`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's dependency-expansion requirement.
   - Evidence: rclone now carries lane-local ZIP writing, WebDAV XML parsing,
     and gzip/WebDAV response modeling, while markerPDF inspects benchmark ZIP
     archives inside the markerPDF lane. Those may be useful local slices, but
     they should not count as support-library progress until split or
     explicitly justified behind bounded shared gates with dependency-specific
     upstream/spec denominators, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases.

10. **Medium - `progress.md` Active Lanes lag current lane handoffs.**
    - Paths: `progress.md:76` through `progress.md:93`,
      `lanes/gitoxide/lane-status.json:9`,
      `lanes/lightningcss/lane-status.json:9`,
      `lanes/markerpdf/lane-status.json:9`,
      `lanes/pandoc/lane-status.json:9`,
      `lanes/rclone/lane-status.json:9`,
      `lanes/readability/lane-status.json:9`, and
      `lanes/syncthing/lane-status.json:9`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists older handoffs such as Gitoxide SSH
      config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Readability negative header cleanup, Syncthing
      system-log route, Difftastic Ada/Apex, rclone VFS Statfs/usage, and
      esbuild automatic JSX fallback. Current lane statuses describe later
      UNTR, relative color, ASCII85Decode, row-header table, WebDAV gzip,
      scored heading/pre sibling, and events-bridge work.

11. **Medium - shell-backed oracle tests must stay quarantined from progress
    credit.**
    - Paths: `lanes/gitoxide/tests/GitUrlTest.php:68` through
      `lanes/gitoxide/tests/GitUrlTest.php:115`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`, and
      `lanes/gitoxide/src/GitFilterDriver.php:250` through
      `lanes/gitoxide/src/GitFilterDriver.php:258`.
    - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
    - Evidence: Gitoxide production filter process handling correctly requires
      a caller-supplied approved client and does not spawn a process, but some
      tests still use `proc_open()` with local `git` as diagnostic/oracle
      tooling. Keep those clearly classified as oracle fixture evidence; do
      not let them inflate native implementation progress.

## Next Intervention

Keep the integration hold. The next useful supervisor move is still:

1. Freeze writers, dashboard generation, and root runners.
2. Take two stable dirty-tree polls that do not change status counts or
   shortstat.
3. Accept or reject one lane batch at a time, normalizing manifest/status
   schema and count units while doing so.
4. Run focused lane verification plus `git diff --check` on the accepted
   batch.
5. If the process gate is empty, run one serialized no-argument
   `php tools/run-tests.php` from that exact frozen snapshot.
6. Regenerate `porting.html` and `porting-summary.json` from the accepted
   commit only, then commit or reject the batch.
