# Independent Audit - 2026-05-24T04:36Z

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
UTC samples: 2026-05-24T04:35:51Z to 2026-05-24T04:36:39Z
HEAD observed: 097bbe6aaee5
recent commits: 097bbe6a Record integration hold status; d28a43df Refresh independent audit status; 41984ec7 Record integration hold status
branch: main
branch divergence: ahead 699, behind 68 relative to origin/main
tracked dirty rows: 306
default status rows including untracked: 13481 -> 13483
git diff --shortstat changed: 306 files changed, 161614 insertions(+), 20446 deletions(-) -> 306 files changed, 161663 insertions(+), 20446 deletions(-)
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:35:51Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:36:39Z:
<no rows>

post-commit pre-finish pgrep at 2026-05-24T04:39Z:
554464 php tools/run-tests.php lanes/readability/tests
owner sampling with `ps -o pid=,user=,etime=,args= -p 554464` returned no row
because the focused runner exited before owner details could be captured; a
follow-up `pgrep -af '^php tools/run-tests\.php( |$)'` returned no rows.
```

I did not start `php tools/run-tests.php`. The duplicate-root gate was clear,
but the checkout was not stable enough for a meaningful no-argument root run:
the default status and diff shortstat changed during the audit, every primary
lane still has dirty or pending handoff state, and a focused readability runner
briefly appeared under the required process pattern before final owner sampling
could complete.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance checkpoint.**
   - Paths: `progress.md:39`, current Git status, and recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verified finished work, and honest
     repo-wide checks.
   - Evidence: `HEAD` is `097bbe6aaee5`, `main...origin/main` is ahead 699
     and behind 68, tracked dirty rows remain 306, default status rows moved
     from 13,481 to 13,483, and the shortstat moved from 161,614 to 161,663
     insertions during this audit. Recent history also shows alternating
     audit and integration-hold commits rather than accepted lane batches.

2. **Critical - no coherent root-harness result exists for this snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/dolt/lane-status.json:10`,
     `lanes/esbuild/lane-status.json:10`,
     `lanes/rclone/lane-status.json:10`, and
     `lanes/syncthing/lane-status.json:10`.
   - Goal requirement at risk: `goal.md:49` requires repo-wide tests/static
     checks with failures recorded honestly.
   - Evidence: the exact pre-root process gate had no rows at both audit
     samples, but the checkout changed during review. Lane statuses continue
     to report focused lane-green evidence while explicitly leaving
     no-argument root verification to the supervisor/integrator. A root run
     now would not prove an accepted, frozen integration state.

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
     observed `HEAD` is `097bbe6aaee5`. The table still collapses required
     denominator/source/pass-fail fields into broad `Benchmark` and `Mapped`
     cells, and commit cells contain states like `pending`, `uncommi`,
     `not com`, and `HEAD 8d`.

4. **High - manifest, lane-status, and dashboard counts disagree across active
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/rclone/lane-status.json:5`, and
     `lanes/syncthing/lane-status.json:5`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45` require mapped
     upstream denominators and accurate dashboard pass/fail counts.
   - Evidence: current manifests/statuses say Difftastic is 822 total / 460
     mapped while the dashboard says 735 / 374; esbuild is 346 mapped while
     the dashboard says 311; Gitoxide manifest now maps 2,877 while the
     dashboard says 2,751; libsqlite is 309 mapped while the dashboard says
     286; LightningCSS is 1,954 mapped while the dashboard says 1,732;
     markerPDF is 352 / 303 while the dashboard says 330 / 280; Pandoc is
     1,422 mapped while the dashboard says 1,061; rclone lane status says 797
     native behavior tests while the dashboard says 698; Syncthing lane status
     says 6,285 PHP assertions while the dashboard says 4,579 pass.

5. **High - manifest/status schemas remain too free-form for reliable
   generation or acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2430`,
     `lanes/esbuild/lane-status.json:5`,
     `lanes/rclone/lane-status.json:5`, and `porting.html:56` through
     `porting.html:67`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric and
     sometimes a long narrative. Dolt puts `mapped` near the top and the
     current `total` as a late prose field. Lane statuses often store the only
     useful counts in `suiteProgress` or `audit` prose instead of normalized
     denominator/mapped/PHP pass/fail fields. The generator cannot reliably
     produce auditable dashboard rows from that shape.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` and
     `goal.md:49`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes while the same lane records still say full Cargo/Haskell/Go,
     provider, full upstream, and root aggregate verification remain unrun or
     blocked. Focused PHP and bounded upstream selectors are useful evidence,
     but they are not accepted full-port parity.

7. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php:142`, and
     `lanes/markerpdf/src/BenchmarkCiWorkflowPlanner.php:52` through
     `lanes/markerpdf/src/BenchmarkCiWorkflowPlanner.php:75`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:35`.
   - Evidence: the manifest/status surface includes shell lifecycle planning,
     Streamlit/FastAPI/Uvicorn/server startup plans, OCR installer readiness,
     Poetry/package metadata, model-runtime dependency graphs, benchmark
     workflow plans, Texify/Nougat/Pandoc/XeLaTeX planning, and CI download
     commands. Those are preflight, blocker, or oracle notes unless reduced to
     bounded native PHP behavior with fixture parity and pass/fail evidence.

8. **High - essential optional-library coverage remains backlog-only, not
   lane-grade support-library progress.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:25` through `dependency-backlog.json:43`,
     `dependency-backlog.json:111` through `dependency-backlog.json:124`,
     `dependency-backlog.json:169` through `dependency-backlog.json:213`,
     `porting.html:72` through `porting.html:78`, and the current 12 primary
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library granularity requirement.
   - Evidence: the current lane tree still has only 12 upstream manifest
     files, one for each primary lane. The 23 support-library candidates
     define useful gates for ZIP/package, XML/HTML5, OpenXML, legacy DOC/CFB,
     doctemplates, PDF text, OCR/layout, table geometry, Unicode, source maps,
     protobuf, compression, glob/pathspec, and provider metadata, but none has
     a bounded component directory with its own manifest, activation gate
     enforcement, dependency-specific denominator, mapped fixtures, PHP
     pass/fail evidence, or malformed/corrupt coverage. The dashboard still
     reports 22 backlog items.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `dependency-backlog.json:15` through
     `dependency-backlog.json:20`, `dependency-backlog.json:35` through
     `dependency-backlog.json:40`, `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/VfsVirtualTree.php:481`,
     `lanes/rclone/src/VfsWebDavProppatchXml.php:10`,
     `lanes/rclone/src/VfsWebDavPropfindResponse.php:58`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9`, and
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:36`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's dependency-expansion requirement.
   - Evidence: rclone now carries lane-local ZIP and WebDAV XML parsing/render
     surfaces, and markerPDF inspects benchmark ZIP archives inside the lane.
     The backlog says ZIP/package and XML/HTML5 should be shared,
     dependency-specific support cores with their own gates and evidence. This
     lane-local growth risks duplicate infrastructure and inflated lane
     progress.

10. **Medium - `progress.md` Active Lanes still lag current lane handoffs.**
    - Paths: `progress.md:79` through `progress.md:90`,
      `lanes/difftastic/lane-status.json:9`,
      `lanes/rclone/lane-status.json:9`, and
      `lanes/syncthing/lane-status.json:9`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists older handoffs such as Difftastic
      Ada/Apex tokenizer, rclone VFS Statfs/usage, Syncthing system-log route,
      and esbuild automatic JSX key/spread fallback. Current lane statuses now
      describe PHP/Hack function parameter paths, WebDAV OPTIONS, event
      producer bridging, and class-expression decorator slices.

11. **Medium - process/shell boundaries need stricter non-progress labeling.**
    - Paths: `tools/generate-dashboard.php:197`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php:142`, and
      `lanes/markerpdf/src/BenchmarkCiWorkflowPlanner.php:52` through
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
only after schema/count normalization, focused lane verification, and
`git diff --check`. If the root process gate is empty on that frozen snapshot,
run exactly one no-argument `php tools/run-tests.php`, regenerate
`porting.html`/`porting-summary.json` from the accepted commit, then commit or
reject. Do not credit support-library work until a bounded shared component has
its own manifest, activation gate, denominator, fixtures, PHP evidence, and
malformed/corrupt coverage.
