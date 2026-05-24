# Independent Audit - 2026-05-24T05:58Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git
history, and process state for the required root-run gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T05:54:57Z, 2026-05-24T05:55:17Z, and 2026-05-24T05:58:03Z
HEAD observed: 6e60fb9aacb3 -> 8dd385029b02
recent commits: 8dd38502 Record integration hold status; 6e60fb9a Record integration hold status; d5bb434a Refresh independent audit status
branch divergence: main...origin/main [ahead 726, behind 68]
tracked dirty rows: 312 -> 312 -> 314
default status rows including untracked: 13883 -> 13883 -> 13945
git diff --shortstat: 312 files changed, 174596 insertions(+), 22732 deletions(-) -> 312 files changed, 174606 insertions(+), 22732 deletions(-) -> 314 files changed, 175003 insertions(+), 22935 deletions(-)
manifest/status JSON validation: jq empty passed for all 12 root lane manifests, lane-status files, porting-summary.json, and dependency-backlog.json
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:54:57Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:55:17Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:58:03Z:
<no rows>
```

I did not start `php tools/run-tests.php`. The exact process gate was clear in
the two samples, but the tree was not stable enough for an audit-owned
acceptance run: shortstat changed during a 20-second window, the checkout has
312-plus tracked dirty rows plus thousands of untracked artifacts, `HEAD` moved
after the first two samples, and active lane, dashboard, evaluator, integrator,
auditor, watchdog, and capacity loops were visible. Running a root harness on
this moving aggregate would create another diagnostic anecdote, not an accepted
baseline.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37`, `progress.md:39`, current Git status, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires small reviewable slices, passing
     tests, and accepted commits rather than broad unreviewed lane aggregates.
   - Evidence: `HEAD` moved from `6e60fb9aacb3` to `8dd385029b02`; `main` is
     `ahead 726, behind 68`; the worktree moved from `312` to `314` tracked
     dirty rows and from `13883` to `13945` total status rows; shortstat moved
     to `314 files changed, 175003 insertions(+), 22935 deletions(-)`; and
     active writer/status loops were present (`port-syncthing`, `port-dolt`,
     `port-libsqlite`,
     `port-difftastic`, `port-lightningcss`, `port-esbuild`, `port-rclone`,
     `port-markerpdf`, `port-quadrable`, `port-gitoxide`, `port-pandoc`,
     `port-auditor`, `port-integrator`, `port-dolt-runner`,
     `run-dashboard-updater-loop.sh`, `run-team-watchdog.sh`,
     `run-evaluator-loop.sh`, and `run-capacity-controller-loop.sh`).

2. **Critical - there is still no coherent root-harness result for the current
   source snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires periodic repo-wide tests/static
     checks and honest failure recording.
   - Evidence: this audit's exact gate returned no rows, but the source snapshot
     moved at the file-content level during sampling and active writers were
     present. Current lane statuses all describe root aggregate verification as
     pending supervisor/integrator work. A root run now would not prove a single
     frozen snapshot.

3. **Critical - the published local dashboard is stale and violates the
   dashboard contract.**
   - Paths: `porting.html:32`, `porting.html:34`,
     `porting.html:35`, `porting.html:56`, `porting.html:67`,
     `porting-summary.json:2`, `porting-summary.json:3`, and
     `porting-summary.json:8`.
   - Requirement at risk: `goal.md` requires current per-lane benchmark source,
     upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html` and `porting-summary.json` still publish
     generated time `2026-05-23 23:43:54 UTC`, source snapshot
     `79768df0c427`, and average progress `97.7%`, while current `HEAD` is
     `8dd385029b02`. Commit cells still contain fragments such as `pending`,
     `not com`, `uncommi`, and `HEAD 8d` instead of accepted commit IDs.

4. **High - dashboard, manifest, and lane-status counts disagree across almost
   every lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:10` through `porting-summary.json:120`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires reliable upstream denominators,
     mapped upstream tests, PHP pass/fail counts, blockers, and current work.
   - Evidence: current manifests/statuses no longer match the dashboard:
     Difftastic `862 total / 515 mapped / 2803 pass` versus dashboard
     `735 / 374 / 374`; esbuild `357 / 357` versus `311 / 311`; Gitoxide
     `2877 mapped / 6361 pass` versus `2751 / 5634`; libsqlite `315` versus
     `286`; LightningCSS `2268 mapped / 2909 pass` versus `1732 / 2197`;
     markerPDF `358 / 309 / 446` versus `330 / 280 / 416`; Pandoc
     `1503 mapped / 319 pass` versus `1061 / 278`; Quadrable `208 pass`
     versus `190`; rclone `818 pass` versus `698`; Readability `234 pass`
     versus `204`; and Syncthing `6415 pass` versus `4579`.

5. **High - manifest/status schemas still prevent mechanical acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires a real upstream benchmark
     denominator, mapped tests, PHP pass/fail counts, blockers, and latest
     commit per lane.
   - Evidence: `benchmarkDenominator.total` is numeric in some lanes and a
     narrative string in others; `mapped` mixes files, behavior units,
     assertions, and supplied-boundary semantics; `nativeImplementation` uses
     lane-specific shapes; and `latestCommit` fields contain prose such as
     `pending in shared dirty worktree`, `not committed`, and `uncommitted lane
     batch`.

6. **High - near-complete progress percentages overstate accepted upstream
   parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, and `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` says passing tests are not enough and each
     lane needs meaningful upstream denominator, fixture parity, edge-case
     coverage, error behavior, docs/examples, and honest blockers.
   - Evidence: most lanes report `98-99%` while full Cargo, Go, BATS, Haskell,
     release-extra, live-provider, model/runtime, and root aggregate parity are
     still pending or explicitly unexecuted. Focused lane-green checks are
     useful but are not full-port parity or accepted root proof.

7. **High - markerPDF still over-credits external/runtime orchestration as
   native mapped progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:462`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:705` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:720`, and
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:971` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:985`.
   - Requirement at risk: `goal.md` forbids counting wrappers around
     JS/Rust/Go/C binaries as progress and allows bridge/shell code only as
     temporary fixture-generation or oracle tooling.
   - Evidence: markerPDF has real native PDF stream-filter work, but its
     manifest also counts Poetry/package metadata, model runtime graphs,
     benchmark archive planning, Nougat/Pandoc/XeLaTeX command planning,
     `chunk_convert.py`/`chunk_convert.sh` shell lifecycle planning,
     Streamlit/FastAPI/Uvicorn/server/app boundaries, and OCR install plans.
     Those should be preflight/oracle evidence unless split from native port
     progress.

8. **High - essential optional-library coverage remains backlog-only, not
   manifest-backed support ports.**
   - Paths: `dependency-backlog.json:1` through
     `dependency-backlog.json:23`, `dependency-backlog.json:111` through
     `dependency-backlog.json:124`, `porting.html:71` through
     `porting.html:78`, and the absence of any support-library
     `UPSTREAM_TEST_MANIFEST.json` outside `lanes/*`.
   - Requirement at risk: this audit prompt requires support libraries to have
     a bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: `dependency-backlog.json` now has 23 items and includes
     `pandoc-doctemplates-core`; `porting.html` and `porting-summary.json`
     still publish 22 items. Rich-function gaps remain for ZIP/OpenXML/ODT/DOC,
     doctemplates, PDF text/OCR/layout/table, XML/HTML5/WebDAV, Unicode,
     charset, source maps, protobuf, hashes, SQL/storage codecs, archive
     streams, glob/pathspec, and provider metadata. None has a dependency-level
     manifest or PHP pass/fail evidence.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/rclone/src/VfsZipArchive.php:8`,
     `lanes/rclone/src/VfsWebDavProppatchXml.php:8`,
     `lanes/rclone/src/MetadataMapper.php`,
     `lanes/rclone/src/OneDrivePermissionPlanner.php`, and
     `dependency-backlog.json`.
   - Requirement at risk: this audit prompt requires dependency expansion to be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone now contains lane-local ZIP writing, WebDAV XML parsing,
     WebDAV compression/property/middleware surfaces, provider metadata, and
     OneDrive permission planning. Those may be valid rclone slices, but they
     should not count as shared support-library progress until they have
     dependency-specific denominators, mapped fixtures, malformed/corrupt cases,
     PHP pass/fail evidence, and activation gates shared with other lanes.

10. **Medium - `progress.md` Active Lanes are stale relative to current lane
    handoffs.**
    - Paths: `progress.md:83` through `progress.md:102` and
      `lanes/*/lane-status.json`.
    - Requirement at risk: `goal.md` requires current owner/session, next task
      per lane, current work, blocker, and latest commit.
    - Evidence: the Active Lanes table still lists older handoffs such as
      Gitoxide SSH config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Readability negative header cleanup, Syncthing
      system-log route, Difftastic Ada/Apex, rclone VFS Statfs, Dolt query-diff
      expression, and esbuild automatic JSX fallback. Current lane statuses
      describe later PHP/Hack namespace work, CONCAT_WS query-diff, JSON-number
      tokenization, gix-index skip-hash, table-btree delete planning,
      non-SRGB relative colors, indirect PDF `/Length`, JATS figures, WebDAV
      property-patch/postprocess, ARS compact headers, and receive-only
      deleted-row cleanup.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. The next acceptable
move is still: stop active writers/status publishers and focused/root runners;
take two stable polls of `HEAD`, tracked status count, total status count,
shortstat, exact PHP runner state, capacity/disk state, and relevant log mtimes;
accept or reject one lane-scoped batch; normalize schema/count fields for that
batch; run focused verification plus `git diff --check`; run exactly one
serialized no-argument `php tools/run-tests.php` from that same frozen snapshot
if the exact process gate remains empty; regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from the accepted
commit; then commit or reject.
