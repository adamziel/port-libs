# Independent Audit - 2026-05-24T14:48Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, targeted support-library
surfaces, and recent Git history through `9affd3c7 Record integration hold
status`. I did not edit lane implementation files, launch agents or tmux
sessions, push, read secrets, inspect process environments, credential stores,
provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 14:44-14:48
HEAD moved during this audit run: 24b6fc0b -> 9affd3c7
recent history: 9affd3c7 Record integration hold status; 24b6fc0b Refresh independent audit status; 7ab00951 Record integration hold status; ede518e4 Record integration hold status; fc4ddabf Refresh independent audit status
tracked dirty rows: 329
default status rows including untracked moved: 18803 -> 18811
git diff --shortstat moved: 329 files changed, 251252 insertions(+), 32057 deletions(-) -> 329 files changed, 251280 insertions(+), 32077 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC; latest sampled HEAD is 9affd3c7
dependency backlog: dependency-backlog.json has 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred)
root run by this audit: not started
```

Required pre-root process gate:

```text
14:47Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
14:48Z pgrep -af '^php tools/run-tests\.php( |$)' matched PID 274387
14:48Z owner evidence: PID 274387 user claude elapsed 00:26 cmd php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php`. The exact gate became occupied by a
focused Syncthing harness, and the checkout also failed the stability gate
because `HEAD`, default status rows, and shortstat changed while being audited.

Current dashboard drift sample:

```text
lane          current status/manifest              dashboard
difftastic    status 3395 pass, manifest 943/1157  3245 pass, 851/1077
dolt          status 429 pass/0 fail               425 pass/0 fail
esbuild       status/manifest 447 pass/mapped      429 pass/mapped
gitoxide      status 7320 pass                     7152 pass
libsqlite     status/manifest 361 pass/mapped      348 pass, 349 mapped
LightningCSS  status 4154 pass, manifest 2841      4065 pass, 2765 mapped
markerPDF     status 497 pass, manifest 360/409    484 pass, 347/396
pandoc        status 375 pass, manifest 2027/2276  362 pass, 1891/2276
quadrable     status 240 pass                      232 pass
rclone        status/manifest 943 pass/mapped      906 pass/mapped
readability   status 3667 assertions / 271 loaded behavior entries vs 3545 dashboard pass
syncthing     status 8233 pass                     7902 pass
```

## Findings

1. **Critical - the repository is still not an acceptance checkpoint.**
   - Paths: `goal.md:29`, `goal.md:48`, `progress.md:48`,
     `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/esbuild/lane-status.json`, `lanes/gitoxide/lane-status.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests; finished agent work must be verified, committed, cleaned
     up, and assigned onward.
   - Evidence: every sampled lane status still records `pending`,
     `uncommitted`, `not committed`, or equivalent handoff wording. `HEAD`
     advanced from `24b6fc0b` to `9affd3c7` during the audit while the dirty
     aggregate changed from 18,803 to 18,811 status rows and shortstat changed.

2. **Critical - root verification is duplicate-blocked and would not be attributable.**
   - Paths: `tools/run-tests.php`, `goal.md:49`, `progress.md:48`.
   - Goal requirement at risk: repo-wide tests and static checks must run
     periodically and failures must be recorded honestly from a stable tree.
   - Evidence: the required `pgrep -af '^php tools/run-tests\.php( |$)'`
     gate first returned no rows, then matched PID `274387` owned by `claude`
     running `php tools/run-tests.php lanes/syncthing/tests`. The tree also
     moved during sampling, so no audit-owned no-argument root run was started.

3. **High - `porting.html` and `porting-summary.json` are stale against every lane.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:38`, `porting.html:56`, `porting.html:67`,
     `porting-summary.json`, `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: dashboard artifacts still claim source `89260857cc71` generated
     at `2026-05-24 12:29:46 UTC`, while current `HEAD` is `9affd3c7` and all
     12 lane status/manifest counts exceed or contradict the dashboard rows.

4. **High - support-library coverage remains backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:81`, `dependency-backlog.json:129`,
     `dependency-backlog.json:145`, `dependency-backlog.json:163`,
     `dependency-backlog.json:179`, `dependency-backlog.json:195`,
     `dependency-backlog.json:214`, `dependency-backlog.json:233`,
     `dependency-backlog.json:256`, `dependency-backlog.json:272`,
     `dependency-backlog.json:289`, `dependency-backlog.json:306`,
     `dependency-backlog.json:322`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:391`,
     `dependency-backlog.json:413`, `dependency-backlog.json:629`,
     `goal.md:25`, `goal.md:35`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: the tracker has 37 rows and 0 active rows; only the 12 base lane
     manifests exist. Pandoc's required DOC, DOCX/OpenXML, PDF handoff/text
     extraction, EPUB, ODT/OpenDocument, templates, citations, math, tables,
     package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, and
     archive/compression areas are covered by gated rows, but none has its own
     support manifest, PHP pass/fail ledger, malformed/corrupt evidence, or
     bounded `sudo -n` install-attempt/ruled-out note for broader suites.

5. **High - dependency-adjacent code is spreading lane-locally before shared gates are active.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:629`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:20`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:191`,
     `lanes/rclone/src/VfsZipArchive.php:11`,
     `lanes/rclone/src/GzipReader.php:7`,
     `lanes/rclone/src/VfsWebDavCompression.php:8`.
   - Goal requirement at risk: optional libraries must be bounded,
     activation-gated, tested against dependency-specific denominators, and
     shared across lanes when common.
   - Evidence: markerPDF uses lane-local `ZipArchive` benchmark archive
     inspection, and rclone carries lane-local ZIP, gzip, and WebDAV
     compression helpers. These may be useful lane slices, but they cannot
     count as `shared-zip-package-core` or `archive-compression-streams`
     progress until those rows are activated with their own manifests,
     malformed archive cases, PHP ledgers, and cross-lane reuse contracts.

6. **High - markerPDF still mixes native PDF evidence with external runtime and shell-boundary plans.**
   - Paths: `goal.md:1`, `goal.md:30`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:802`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:809`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:811`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:831`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:837`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1121`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1131`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1137`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1142`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as native deliverables.
   - Evidence: markerPDF has real native PDF extraction movement, now 360/409
     mapped with 497 local behavior tests. The same denominator/status surface
     still includes Streamlit/FastAPI/Uvicorn plans, OCRMyPDF/Tesseract/
     Ghostscript setup planning, Pandoc/XeLaTeX helper planning, Poetry/model
     stack boundaries, and `chunk_convert` shell lifecycle planning. Those must
     remain explicit blockers, supplied-runner contracts, or non-goals.

7. **Medium - Pandoc rich-function progress is routed but not proven.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `dependency-backlog.json:81`, `dependency-backlog.json:129`,
     `dependency-backlog.json:145`, `dependency-backlog.json:163`,
     `dependency-backlog.json:179`, `dependency-backlog.json:214`,
     `dependency-backlog.json:233`, `dependency-backlog.json:256`,
     `dependency-backlog.json:272`, `dependency-backlog.json:322`,
     `dependency-backlog.json:340`, `dependency-backlog.json:365`,
     `dependency-backlog.json:413`, `dependency-backlog.json:629`.
   - Goal requirement at risk: Pandoc must account for essential rich document
     and PDF conversion libraries with real upstream/spec-suite evidence rather
     than fixture-only credit unless broader suites were attempted and honestly
     bounded.
   - Evidence: Pandoc reports 2,027/2,276 mapped and 375 local PHP checks, but
     the full Haskell runner remains unexecuted. The latest required
     rich-function libraries are represented in backlog rows, yet remain
     inactive and lack dependency-specific manifests or pass/fail evidence.

8. **Medium - near-complete percentages overstate accepted parity.**
   - Paths: `porting.html:32`, `porting.html:56`,
     `porting.html:67`, `lanes/*/lane-status.json`, `goal.md:35`,
     `goal.md:37`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be blockers or
     future slices.
   - Evidence: the dashboard reports `98.3%` average progress and most lanes
     show 98-99%, while every lane handoff remains pending/uncommitted, root
     verification is absent for a frozen snapshot, several upstream runners are
     static/bounded/unexecuted, and no support-library row is active.

9. **Medium - manifest/status fields remain too free-form for durable coordination.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `goal.md:3`, `goal.md:44`.
   - Goal requirement at risk: progress must track upstream denominator,
     mapped tests, PHP pass/fail counts, phase, audit status, blocker, and
     latest commit in a durable coordination system.
   - Evidence: some `benchmarkDenominator.status` values are extremely long
     concatenated slice slugs, and `latestCommit`/commit fields in lane status
     files still contain prose such as `pending`, `not committed`,
     `uncommitted`, or stale HEAD references instead of accepted
     implementation commits.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. Require
two stable polls of `HEAD`, tracked/default status counts, shortstat,
`pgrep -af '^php tools/run-tests\.php( |$)'`, dashboard/dependency counts,
lane status timestamps, and relevant log mtimes. Accept exactly one owner-free
lane batch at a time, normalizing manifest/status schema and commit fields
before claiming progress. Promote support libraries only behind an accepted
base-lane gate or true component blocker, each with its own manifest and
malformed-case evidence. Regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the accepted commit, then run one serialized
no-argument root harness only if the exact process gate remains empty on that
frozen snapshot.
