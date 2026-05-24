# Independent Audit - 2026-05-24T04:54Z

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
UTC sample: 2026-05-24T04:54:09Z
HEAD observed: de6bde4b856e
recent commits: de6bde4b Refresh independent audit status; 2197c855 Record integration hold status; 659d3adf Record integration hold status
branch divergence: main...origin/main [ahead 704, behind 68]
tracked dirty rows: 306
default status rows including untracked: 13504
git diff --shortstat: 306 files changed, 165091 insertions(+), 22100 deletions(-)
manifest/status JSON validation: jq empty passed for all lane manifests, all lane-status files, porting-summary.json, and dependency-backlog.json
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at initial sample:
763233 php tools/run-tests.php lanes/readability/tests

ps -o pid,user,etime,args -p 763233:
PID USER ELAPSED COMMAND
<no process row; PID exited before owner sampling>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:54:09Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' during pre-commit validation:
839655 php tools/run-tests.php lanes/readability/tests

ps -o pid,user,etime,args -p 839655:
PID USER ELAPSED COMMAND
<no process row; PID exited before owner sampling>

pgrep -af '^php tools/run-tests\.php( |$)' during final gate check:
851574 php tools/run-tests.php lanes/syncthing/tests
856959 php tools/run-tests.php lanes/quadrable/tests

ps -o pid,user,etime,args -p 851574,856959:
PID USER ELAPSED COMMAND
851574 claude 00:24 php tools/run-tests.php lanes/syncthing/tests
856959 claude 00:07 php tools/run-tests.php lanes/quadrable/tests
```

I did not start `php tools/run-tests.php`. The process gate was intermittently
non-empty, and the checkout was still a broad dirty aggregate with every lane
represented by pending/uncommitted handoff state.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:39`, `lanes/difftastic/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and current Git status.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small reviewable slices, verified finished work,
     honest repo-wide checks, and committed accepted work.
   - Evidence: the branch is still `ahead 704, behind 68`; `git status`
     reports 306 tracked dirty rows and 13,504 total rows including
     untracked files; `git diff --shortstat` reports 306 changed files with
     165,091 insertions and 22,100 deletions. Current lane statuses repeatedly
     say their work is pending or uncommitted because root verification and
     supervisor/integrator acceptance were not assigned.

2. **Critical - no coherent root-harness result exists for this snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:48` and `goal.md:49` require
     verification and honest recording of repo-wide test status.
   - Evidence: the initial required gate matched active PID `763233`
     (`php tools/run-tests.php lanes/readability/tests`), which exited before
     owner sampling. Pre-commit validation later matched focused readability
     PID `839655`, which also exited before owner sampling. The final gate
     check then matched focused Syncthing PID `851574` and focused Quadrable
     PID `856959`, both owned by `claude`. The tree remained unstable and
     broad. Lane statuses cite focused lane-green checks while explicitly
     leaving the no-argument root harness pending.

3. **Critical - `porting.html` and `porting-summary.json` are stale and do
   not satisfy the dashboard contract.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:42` through `porting.html:52`,
     `porting-summary.json:2` through `porting-summary.json:8`, and
     `porting-summary.json:25`, `porting-summary.json:42`,
     `porting-summary.json:59`, `porting-summary.json:76`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard rows with separate benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, phase, audit, current work, blocker, and
     commit.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 23:43:54 UTC` and source commit `79768df0c427`, while the
     reviewed HEAD is `de6bde4b856e`. The HTML table has `Benchmark` and
     `Mapped` columns but no distinct benchmark-source and upstream-denominator
     columns. Commit cells still contain non-commit states such as `pending`,
     `uncommi`, `not com`, and `HEAD 8d`.

4. **High - manifest, lane-status, and dashboard counts disagree across
   active lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:734`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:105`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:905`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45`.
   - Evidence: current manifests/statuses say Difftastic is 829 total / 467
     mapped while the dashboard says 735 / 374; esbuild maps 348 while the
     dashboard says 311; Gitoxide maps 2,877 with 6,266 lane assertions while
     the dashboard says 2,751 and 5,634; libsqlite maps 310 while the
     dashboard says 286; LightningCSS maps 1,988 and reports 2,528 assertions
     while the dashboard says 1,732 and 2,197; markerPDF is 353 / 304 with
     441 behavior tests while the dashboard says 330 / 280 / 416; Pandoc maps
     1,449 and reports 314 behavior tests while the dashboard says 1,061 and
     278; rclone maps 803 while the dashboard says 698; Readability reports
     230 behavior tests while the dashboard says 204; Syncthing reports 6,307
     lane assertions while the dashboard says 4,579.

5. **High - manifest/status schemas remain too free-form for reliable
   generation or acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2436`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2442`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, and
     `porting-summary.json:14` through `porting-summary.json:18`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric and
     sometimes a long narrative. Dolt puts `mapped` near the top but keeps the
     current `total` and `phpBehaviorTests` thousands of lines later, where
     they still describe an older TIME slice while the lane-status handoff has
     advanced to broader GREATEST/LEAST expression work. MarkerPDF and
     Gitoxide encode long slice histories into status strings instead of
     stable fields. The generator cannot reliably distinguish source,
     denominator, mapped units, behavior tests, assertions, and blocker state.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` and
     `goal.md:49`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes while the same current lane records still say root aggregate
     verification, full Cargo/Go/BATS/Haskell/release-extra suites, live
     provider paths, broad workspace runners, and full upstream parity remain
     unrun or blocked.

7. **High - markerPDF still over-credits external/runtime orchestration as
   mapped native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php:129` through
     `lanes/markerpdf/src/ChunkConversionPlanner.php:164`, and
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:14` through
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:20`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and this run's support-library granularity requirement.
   - Evidence: the markerPDF manifest/status still include benchmark CLI
     argument plans, marker server startup/lifespan/remote polling/upload
     plans, chunk-convert shell lifecycle plans, OCR install readiness, Poetry
     package metadata, model runtime graphs, Texify/Nougat planning, and CI
     workflow/publish/CLA plans. The source also models shell/background
     command orchestration. These are blocker/preflight/oracle notes unless
     reduced to bounded native PHP behavior with fixture parity and pass/fail
     evidence.

8. **High - essential optional-library coverage is backlog-only, not
   lane-grade support-library progress.**
   - Paths: `dependency-backlog.json:1` through `dependency-backlog.json:5`,
     `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:25` through `dependency-backlog.json:43`,
     `dependency-backlog.json:111` through `dependency-backlog.json:124`,
     `porting.html:72` through `porting.html:78`, and
     `progress.md:17` through `progress.md:24`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library granularity requirement.
   - Evidence: `dependency-backlog.json` has 23 gated items
     (`candidate: 13`, `deferred: 10`), while the dashboard still says 22
     items (`candidate: 12`, `deferred: 10`). The tree still has only the 12
     primary lane manifests, not support-library manifests. Rich-function gaps
     remain for Pandoc package/OpenXML/ODT/doctemplates, markerPDF PDF
     text/OCR/layout/table/Unicode work, rclone archive/XML/provider metadata,
     and shared charset/hash/glob/compression surfaces. None is yet a bounded
     native PHP component with its own activation gate, dependency-specific
     denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt
     coverage, and full-upstream/spec evidence.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:25` through `dependency-backlog.json:43`,
     `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/VfsWebDavProppatchXml.php:7` through
     `lanes/rclone/src/VfsWebDavProppatchXml.php:15`,
     `lanes/rclone/src/VfsVirtualTree.php:461` through
     `lanes/rclone/src/VfsVirtualTree.php:483`, and
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9` through
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:20`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's dependency-expansion requirement.
   - Evidence: rclone now carries lane-local ZIP writing and WebDAV XML
     parsing, and markerPDF inspects benchmark ZIP archives inside the lane
     through `ZipArchive`. These may be useful local slices, but they should
     not count as support-library progress until split or explicitly justified
     behind bounded shared gates with dependency-specific upstream/spec
     denominators and malformed/corrupt cases.

10. **Medium - `progress.md` Active Lanes still lag current lane handoffs.**
    - Paths: `progress.md:75` through `progress.md:92`,
      `lanes/gitoxide/lane-status.json:11`,
      `lanes/lightningcss/lane-status.json:11`,
      `lanes/markerpdf/lane-status.json:11`,
      `lanes/pandoc/lane-status.json:11`,
      `lanes/rclone/lane-status.json:11`,
      `lanes/readability/lane-status.json:11`, and
      `lanes/syncthing/lane-status.json:11`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists older handoffs such as Gitoxide SSH
      config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Readability negative header cleanup, Syncthing
      system-log route, Difftastic Ada/Apex, rclone VFS Statfs/usage, and
      esbuild automatic JSX key/spread fallback. Current lane statuses now
      describe Git index UNTR, direct color minifier work, PDF ASCIIHexDecode,
      Pandoc row-header Native output, rclone WebDAV PUT, scored same-class
      sibling joining, and Syncthing route-producer events.

11. **Medium - process and shell boundaries still need strict non-progress
    labeling.**
    - Paths: `tools/generate-dashboard.php:196` through
      `tools/generate-dashboard.php:204`,
      `lanes/gitoxide/tests/GitUrlTest.php:68` through
      `lanes/gitoxide/tests/GitUrlTest.php:71`,
      `lanes/gitoxide/tests/ReceivePackTransportTest.php:1167` through
      `lanes/gitoxide/tests/ReceivePackTransportTest.php:1184`, and
      `lanes/markerpdf/src/ChunkConversionPlanner.php:140` through
      `lanes/markerpdf/src/ChunkConversionPlanner.php:143`.
    - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
    - Evidence: dashboard generation shells out for Git metadata; Gitoxide
      tests use real `git` subprocess oracle calls and injected SSH execution
      boundaries; markerPDF records shell lifecycle plans. Some of this is
      acceptable coordination or oracle tooling, but it must remain excluded
      from native implementation progress and from completion percentages.

## Required Intervention

Keep the integration hold. Freeze writers, lane agents, status/dashboard
publishers, and root/focused runners long enough for two stable polls of
`HEAD`, `git status`, `git diff --shortstat`, and
`pgrep -af '^php tools/run-tests\.php( |$)'`. Accept one lane batch at a time
only after manifest/status schema normalization, focused lane verification,
and `git diff --check`. If the root process gate is empty on that frozen
snapshot, run exactly one no-argument `php tools/run-tests.php`, regenerate
`porting.html`/`porting-summary.json` from the accepted commit, then commit or
reject that single batch. Start support-library progress only as bounded
native PHP components with explicit activation gates, dependency-specific
upstream/spec denominators, mapped fixtures, PHP pass/fail evidence, and
malformed/corrupt cases.
