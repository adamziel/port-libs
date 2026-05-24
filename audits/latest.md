# Independent Audit - 2026-05-24T04:49Z

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
UTC samples: 2026-05-24T04:41:56Z to 2026-05-24T04:49:47Z
HEAD observed: 6c9a31f31356 -> 659d3adf7971 -> 2197c855b273
recent commits: 2197c855 Record integration hold status; 659d3adf Record integration hold status; 6c9a31f3 Record integration hold status
branch: main
branch divergence: ahead 703, behind 68 relative to origin/main
tracked dirty rows: 306 -> 308
default status rows including untracked: 13489 -> 13502
git diff --shortstat changed: 306 files changed, 163767 insertions(+), 22004 deletions(-) -> 308 files changed, 165024 insertions(+), 22149 deletions(-)
manifest/status JSON validation: jq empty passed for all lane manifests, all lane-status files, porting-summary.json, and dependency-backlog.json
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:41:56Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:43:43Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:48:21Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:49:47Z:
<no rows>
```

I did not start `php tools/run-tests.php`. The duplicate-root gate was clear,
but the checkout was not stable enough for a meaningful no-argument root run:
the default status count and diff shortstat changed during the audit, `HEAD`
advanced during the final check, and every primary lane still has dirty or
pending handoff state.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance checkpoint.**
   - Paths: `progress.md:39`, current Git status, and recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verified finished work, and honest
     repo-wide checks.
   - Evidence: `HEAD` moved from `6c9a31f31356` to `2197c855b273` during the
     final checks. `main...origin/main` is ahead 703 and behind 68. Tracked
     dirty rows moved from 306 to 308, default status rows moved from 13,489
     to 13,502, and the shortstat moved from 163,767 insertions / 22,004
     deletions to 165,024 insertions / 22,149 deletions during this audit.

2. **Critical - no coherent root-harness result exists for this snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/rclone/lane-status.json:10`,
     `lanes/syncthing/lane-status.json:10`,
     `lanes/esbuild/lane-status.json:10`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/dolt/lane-status.json:12`, and
     `lanes/gitoxide/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:48` and `goal.md:49` require
     finished work to be verified and repo-wide tests/static checks to be
     recorded honestly.
   - Evidence: the exact pre-root process gate had no rows at all audit
     samples, but the tree changed while the audit was running. Current lane
     statuses repeatedly say no-argument root verification was not assigned or
     remains pending, while reporting only focused lane-green checks.

3. **Critical - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard contract.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:43` through `porting.html:52`, and
     `porting-summary.json:1`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     generated dashboard with separate benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit columns.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 23:43:54 UTC` and source commit `79768df0c427`, while the
     observed `HEAD` reached `2197c855b273`. Commit cells still contain
     non-commit states like `pending`, `uncommi`, `not com`, and `HEAD 8d`.
     The table also collapses required denominator/source/pass-fail fields
     into broad `Benchmark` and `Mapped` cells.

4. **High - manifest, lane-status, and dashboard counts disagree across active
   lanes.**
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
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:103`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/lane-status.json:6`, and
     `lanes/syncthing/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45` require mapped
     upstream denominators and accurate dashboard pass/fail counts.
   - Evidence: current files say Difftastic is 822 total / 460 mapped while
     the dashboard says 735 / 374; esbuild maps 347 while the dashboard says
     311; Gitoxide maps 2,877 with 6,239 PHP assertions while the dashboard
     says 2,751 and 5,634; libsqlite maps 309 while the dashboard says 286;
     LightningCSS maps 1,954 and reports 2,494 PHP assertions while the
     dashboard says 1,732 and 2,197; markerPDF is 353 / 304 with 440 PHP
     behavior tests while the dashboard says 330 / 280 and 416; Pandoc maps
     1,442 and reports 313 PHP behavior tests while the dashboard says 1,061
     and 278; rclone maps 800 while the dashboard says 698; Readability now
     reports 230 PHP behavior tests while the dashboard says 204; Syncthing
     reports 6,285 PHP assertions while the dashboard says 4,579.

5. **High - manifest/status schemas remain too free-form for reliable
   generation or acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2433`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2439`, and
     `porting.html:56` through `porting.html:67`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric and
     sometimes a long narrative. Dolt puts `mapped` near the top but has the
     current `total` and `phpBehaviorTests` much later, and those late fields
     still describe the older 04:05 TIME slice with 388 PHP behavior tests
     while the top/latest lane status describes the 04:36 GREATEST/LEAST slice
     with 390 focused cases. The generator cannot reliably produce auditable
     dashboard rows from that shape.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` and
     `goal.md:49`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes while the same lane records still say root aggregate
     verification, full Cargo/Go/BATS/Haskell/release-extra suites, live
     provider paths, broad workspace runners, and full upstream parity remain
     unrun or blocked.

7. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php:140` through
     `lanes/markerpdf/src/ChunkConversionPlanner.php:143`, and
     `lanes/markerpdf/src/BenchmarkCiWorkflowPlanner.php:45` through
     `lanes/markerpdf/src/BenchmarkCiWorkflowPlanner.php:76`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:35`.
   - Evidence: the manifest/status surface still includes shell lifecycle
     planning, Streamlit/FastAPI/Uvicorn/server startup plans, OCR installer
     readiness, Poetry/package metadata, model-runtime dependency graphs,
     benchmark workflow plans, Texify/Nougat/Pandoc/XeLaTeX planning, and CI
     download/unzip commands. Those are preflight, blocker, or oracle notes
     unless reduced to bounded native PHP behavior with fixture parity and
     pass/fail evidence.

8. **High - essential optional-library coverage remains backlog-only, not
   lane-grade support-library progress.**
   - Paths: `dependency-backlog.json:1` through `dependency-backlog.json:5`,
     `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:25` through `dependency-backlog.json:43`,
     `dependency-backlog.json:111` through `dependency-backlog.json:124`,
     `dependency-backlog.json:169` through `dependency-backlog.json:213`,
     `dependency-backlog.json:219` through `dependency-backlog.json:258`,
     `dependency-backlog.json:382` through `dependency-backlog.json:399`,
     and `porting.html:72` through `porting.html:78`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library granularity requirement.
   - Evidence: `dependency-backlog.json` has 23 gated items, but the
     dashboard still says 22. The current lane tree still has only the 12
     primary lane manifests, not support-library manifests. Rich-function
     gaps remain for Pandoc package/OpenXML/ODT/doctemplates, markerPDF PDF
     text/OCR/layout/table/Unicode work, rclone archive/XML/provider metadata,
     and shared charset/hash/glob/compression surfaces. None is yet a bounded
     component with its own activation gate, dependency-specific denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt coverage, and
     full-upstream/spec evidence.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:25` through `dependency-backlog.json:43`,
     `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/VfsWebDavProppatchXml.php:7` through
     `lanes/rclone/src/VfsWebDavProppatchXml.php:10`,
     `lanes/rclone/src/VfsVirtualTree.php:481`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9`, and
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:36`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's dependency-expansion requirement.
   - Evidence: rclone now carries lane-local ZIP and WebDAV XML
     parsing/rendering surfaces, and markerPDF inspects benchmark ZIP archives
     inside the lane. These may be useful local slices, but they should not
     count as shared dependency progress until split or explicitly justified
     behind bounded support-library gates with dependency-specific evidence.

10. **Medium - `progress.md` Active Lanes still lag current lane handoffs.**
    - Paths: `progress.md:74` through `progress.md:91`,
      `lanes/gitoxide/lane-status.json:11`,
      `lanes/rclone/lane-status.json:11`,
      `lanes/readability/lane-status.json:10`, and
      `lanes/syncthing/lane-status.json:11`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists older handoffs such as Gitoxide SSH
      config-options, rclone VFS Statfs/usage, Readability negative header
      cleanup, Syncthing system-log route, Difftastic Ada/Apex, and esbuild
      automatic JSX key/spread fallback. Current lane statuses now describe
      gix-index FSMN, WebDAV GET/HEAD/POST, parent-score climb, `SystemEvents`
      producer bridging, PHP/Hack signature paths, and class-expression
      private-field/public-auto-accessor slices.

11. **Medium - process and shell boundaries still need stricter
    non-progress labeling.**
    - Paths: `tools/generate-dashboard.php:197`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php:142`, and
      `lanes/markerpdf/src/BenchmarkCiWorkflowPlanner.php:53` through
      `lanes/markerpdf/src/BenchmarkCiWorkflowPlanner.php:75`.
    - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
    - Evidence: dashboard generation shells out for Git metadata; Gitoxide
      tests use subprocesses as oracles; markerPDF records shell lifecycle and
      CI command plans. Some of this is acceptable coordination or oracle
      tooling, but it must remain excluded from native implementation progress
      and from lane completion percentages.

## Required Intervention

Keep the integration hold. Freeze writers, lane agents, status/dashboard
publishers, and root/focused runners long enough for two stable polls of
`HEAD`, `git status`, `git diff --shortstat`, and
`pgrep -af '^php tools/run-tests\.php( |$)'`. Accept one lane batch at a time
only after manifest/status schema normalization, focused lane verification,
and `git diff --check`. If the root process gate is empty on that frozen
snapshot, run exactly one no-argument `php tools/run-tests.php`, regenerate
`porting.html`/`porting-summary.json` from the accepted commit, then commit or
reject. Do not credit support-library work until a bounded shared component has
its own manifest, activation gate, denominator, fixtures, PHP evidence, and
malformed/corrupt coverage.
