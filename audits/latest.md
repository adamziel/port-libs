# Independent Audit - 2026-05-24T05:15Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
current `lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, recent Git history, and root-runner process
state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T05:15:23Z and 2026-05-24T05:15:40Z
HEAD observed: a76f0a1f364d
recent commits: a76f0a1f Record integration hold status; 5021b031 Refresh independent audit status; a843795f Record integration hold status
branch divergence: main...origin/main [ahead 712, behind 68]
tracked dirty rows: 307
default status rows including untracked: 13535
git diff --shortstat moved: 307 files changed, 167903 insertions(+), 21925 deletions(-) -> 307 files changed, 167952 insertions(+), 21925 deletions(-)
recent port logs touched in last five minutes: 22
manifest/status JSON validation: jq empty passed for all lane manifests, lane-status files, porting-summary.json, and dependency-backlog.json
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:15:23Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:15:40Z:
<no rows>
```

I did not start `php tools/run-tests.php`. The exact process gate was clear,
but the tree was not stable enough: `git diff --shortstat` changed across a
17-second sample window, the checkout remains a broad 307-file dirty aggregate,
and recent history is still audit/status-only rather than accepted lane
implementation commits.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md`, `audits/integration-status.md`, current Git status,
     and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:52`
     require small reviewable slices, verification before acceptance, commits,
     and a visible stable baseline.
   - Evidence: latest history is status-only (`a76f0a1f`, `5021b031`,
     `a843795f`), branch state is `ahead 712, behind 68`, the worktree has 307
     tracked dirty rows and 13,535 total status rows, and shortstat still
     changed during this audit from 167,903 to 167,952 insertions. That is an
     active aggregate, not a lane-scoped handoff.

2. **Critical - there is no coherent root-harness result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`, and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires repo-wide tests/static
     checks and honest failure recording.
   - Evidence: the exact duplicate-root gate was clear twice, but no
     audit-owned root run was started because the tree moved during the
     stability sample. Lane statuses cite focused green checks; those do not
     substitute for one serialized no-argument root run from the frozen source
     snapshot.

3. **Critical - `porting.html` and `porting-summary.json` are stale and still
   miss the dashboard contract.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:43` through `porting.html:52`,
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     per-lane benchmark source, upstream denominator, mapped tests, PHP
     pass/fail, WordPress scenarios, phase, audit, current work, blocker, and
     commit.
   - Evidence: local dashboard artifacts still publish generated time
     `2026-05-23 23:43:54 UTC` and source/dashboard commit `79768df0c427`,
     while reviewed `HEAD` is `a76f0a1f364d`. The HTML still collapses
     benchmark source, denominator, mapped tests, and PHP pass/fail into broad
     `Benchmark`/`Mapped` columns, and commit cells still contain non-commit
     prose fragments such as `pending`, `uncommi`, `not com`, and `HEAD 8d`.

4. **High - manifest, lane-status, and dashboard counts disagree across
   active lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45`
     require real denominators and reliable status/dashboard tracking.
   - Evidence: current manifests/statuses disagree with the published
     dashboard. Difftastic is now 834 total / 480 mapped / 2,744 PHP pass, but
     dashboard says 735 / 374 / 374. esbuild is 350 manifest mapped and 349
     status pass, but dashboard says 311. markerPDF is 355 total / 306 mapped
     / 443 PHP pass, but dashboard says 330 / 280 / 416. Pandoc maps 1,471,
     but dashboard says 1,061. rclone maps 810, but dashboard says 698.
     Syncthing status says 6,356 PHP assertions/pass units, while dashboard
     says 4,579.

5. **High - manifest/status schemas remain too free-form for reliable
   acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is numeric in some manifests and
     a long narrative string in others; `phpPass` mixes assertions, behavior
     checks, and test counts; status fields store large prose blobs where the
     dashboard needs typed denominator, mapped, runner, PHP pass/fail, blocker,
     and accepted commit fields. rclone's manifest has a long series of
     `sourceAddendum*` keys that further confirms the schema is evolving
     lane-locally rather than through a stable acceptance model.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` and
     `goal.md:49`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes even while many lane blockers still say full Cargo/Go/BATS,
     Haskell, release-extra, live provider/mount, or root aggregate parity
     remains unrun or out of scope. Focused green lane checks are useful
     evidence, but they are not accepted full-port parity.

7. **High - markerPDF still over-credits external/runtime orchestration as
   mapped native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`, and
     `lanes/markerpdf/src/ChunkConversionPlanner.php:136`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and this audit's support-library granularity requirement.
   - Evidence: markerPDF now has valuable native PDF stream work, but the
     manifest/status denominator still includes benchmark/archive planning,
     marker server/app/runtime planning, Poetry/package metadata, model runtime
     graphs, OCR install plans, Texify/Nougat boundaries, and
     `shell_execution => eval` chunk-convert lifecycle metadata. Those are
     preflight/oracle boundaries unless explicitly isolated from native port
     progress.

8. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json`, `progress.md:17` through
     `progress.md:24`, and `porting.html:75` through `porting.html:78`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this audit's support-library requirement for a bounded
     native component, activation gate, dependency-specific denominator,
     mapped fixtures, PHP pass/fail evidence, and malformed/corrupt cases
     where relevant.
   - Evidence: `dependency-backlog.json` has 23 rows (`candidate: 13`,
     `deferred: 10`), while local `porting.html` still says 22 rows
     (`candidate: 12`). There are no support-library manifests/status files
     with PHP pass/fail evidence for the rich-function gaps now blocking
     real parity: Pandoc package/OpenXML/ODT/doctemplates/citation/math,
     markerPDF PDF text/OCR/layout/table/Unicode, rclone archive/XML/provider
     metadata, and shared charset/hash/glob/compression surfaces.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `lanes/rclone/src/VfsZipArchive.php`,
     `lanes/rclone/src/VfsWebDavProppatchXml.php`,
     `lanes/rclone/src/VfsWebDavReadResponse.php`,
     `lanes/rclone/src/VfsWebDavServeMiddleware.php`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php`, and
     `dependency-backlog.json`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31` and this
     audit's dependency-expansion requirement.
   - Evidence: rclone now carries lane-local ZIP, WebDAV XML, WebDAV response,
     and middleware components, while markerPDF carries benchmark archive
     inspection. These may be justified implementation slices, but they should
     not count as support-library progress until split or gated with their own
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, and malformed/corrupt cases.

10. **Medium - `progress.md` Active Lanes still lags current handoffs.**
    - Paths: `progress.md:82` through `progress.md:95` and
      `lanes/*/lane-status.json`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: the Active Lanes table still lists older handoffs such as
      Gitoxide SSH config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Readability negative header cleanup,
      Syncthing system-log route, Difftastic Ada/Apex, rclone VFS Statfs, and
      esbuild automatic JSX fallback. Current lane statuses describe later
      UNTR, relative color, RunLengthDecode, nonmatching sibling, disk-change
      events, PHP/Hack class-property, WebDAV middleware, and decorator work.

11. **Medium - shell/process adapters must remain quarantined from native
    progress credit.**
    - Paths: `lanes/gitoxide/src/GitFilterDriver.php:250`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php:136`, and
      `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
    - Evidence: some process-facing code is correctly framed as supplied
      clients, preflight planning, or oracle/fixture tooling. The audit risk is
      status drift: any shell/process boundary must stay labeled as fixture
      generation, runner evidence, or adapter planning, never as native
      implementation progress.

## Next Intervention

Keep the hard writer/runner/status freeze. The next acceptable move is still:
two stable polls of `HEAD`, tracked status count, untracked-inclusive status
count, shortstat, exact PHP runner state, Dolt runner state, capacity queue
state, and relevant log mtimes; accept one lane-scoped batch only; normalize
schema/count fields for that batch; run focused verification plus
`git diff --check`; run exactly one serialized no-argument
`php tools/run-tests.php` from that same frozen snapshot if
`pgrep -af '^php tools/run-tests\.php( |$)'` is empty; regenerate dashboard
artifacts from the accepted commit; then commit or reject.
