# Independent Audit - 2026-05-24T14:35Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 root `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
12 `lanes/*/lane-status.json`, `dependency-backlog.json`, targeted support
library surfaces, and recent Git history through `275cf4fb Record integration
hold status`. I did not edit lane implementation files, launch agents or tmux
sessions, push, read secrets, inspect process environments, credential stores,
provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 14:23-14:35
HEAD moved during this audit: 3ce6a9a30ba2 -> 078670a0e122 -> 275cf4fb03f7
recent history: 275cf4fb Record integration hold status; 078670a0 Record integration hold status; 3ce6a9a3 Record integration hold status; 1e785852 Refresh independent audit status; bda91e40 Record integration hold status
default status rows including untracked: 18596 -> 18596 -> 18601 -> 18641 -> 18674
git diff --shortstat: 329 files changed, 247181 insertions(+), 30401 deletions(-) -> 329 files changed, 247209 insertions(+), 30403 deletions(-) -> 329 files changed, 247213 insertions(+), 30403 deletions(-) -> 331 files changed, 247930 insertions(+), 30544 deletions(-) -> 329 files changed, 248502 insertions(+), 30508 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC; latest sampled HEAD is 275cf4fb03f7
dependency backlog: dependency-backlog.json has 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
json validation: jq empty passed for all 12 lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
```

Required pre-root process gate:

```text
14:25-14:27Z pgrep -af '^php tools/run-tests\.php( |$)': no rows in initial sampled gates
14:28Z pgrep -af '^php tools/run-tests\.php( |$)': 48482 php tools/run-tests.php lanes/syncthing/tests; 52962 php tools/run-tests.php lanes/markerpdf/tests
14:28Z owner evidence: PID 48482 user claude elapsed 00:52 cmd php tools/run-tests.php lanes/syncthing/tests
14:28Z owner evidence: PID 52962 exited before owner sampling
14:28Z second pgrep -af '^php tools/run-tests\.php( |$)': 48482 php tools/run-tests.php lanes/syncthing/tests
14:30Z pgrep -af '^php tools/run-tests\.php( |$)': 59140 php tools/run-tests.php; focused Gitoxide/libsqlite/LightningCSS/markerPDF shards also present, many exited before owner sampling
14:30Z owner evidence: PID 59140 user claude elapsed 00:12 cmd php tools/run-tests.php
14:31Z pgrep -af '^php tools/run-tests\.php( |$)': 59140 php tools/run-tests.php; 61361 php tools/run-tests.php lanes/quadrable/tests; 61777 php tools/run-tests.php lanes/readability/tests; 61842 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...; 61929 php tools/run-tests.php lanes/syncthing/tests/ConfigDevicesTest.php ...
14:31Z owner evidence: PID 59140 user claude elapsed 00:21 cmd php tools/run-tests.php
14:31Z owner evidence: PID 61361 user claude elapsed 00:14 cmd php tools/run-tests.php lanes/quadrable/tests
14:31Z owner evidence: PID 61842 user claude elapsed 00:13 cmd php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
14:33Z pgrep -af '^php tools/run-tests\.php( |$)': 80903 php tools/run-tests.php
14:33Z owner evidence: PID 80903 exited before owner sampling
14:34Z pgrep -af '^php tools/run-tests\.php( |$)': 85990 php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php; 86014 php tools/run-tests.php lanes/dolt/tests
14:34Z owner evidence: PIDs 85990 and 86014 exited before owner sampling
14:35Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
```

I did not start `php tools/run-tests.php`. The exact process gate was initially
clear but later matched focused harnesses and then an active no-argument root
PID `59140`; a later sample cleared, but the checkout also failed the stability gate because `HEAD`,
default status rows, shortstat, and lane manifest/status counts changed during
the audit. A later transient no-argument root PID `80903` appeared and exited
before owner sampling. markerPDF status/manifest evidence moved while it was being
inspected from the prior 407/358/495-style record to 408 total, 359 mapped, and
496 PHP behavior tests.

Current dashboard drift sample:

```text
lane          current status/manifest              dashboard
difftastic    status 3379 pass, manifest 934/1148  3245 pass, 851/1077
dolt          status 428 pass/0 fail               425 pass/0 fail
esbuild       status 445 pass, manifest 445 mapped 429 pass/mapped
gitoxide      status 7313 pass                     7152 pass
libsqlite     status/manifest 360 pass/mapped      348 pass, 349 mapped
LightningCSS  status 4146 pass, manifest 2829      4065 pass, 2765 mapped
markerPDF     status 496 pass, manifest 359/408    484 pass, 347/396
pandoc        status 373 pass, manifest 2005/2276  362 pass, 1891/2276
quadrable     status 239 pass                      232 pass
rclone        status 938 pass, manifest 939 mapped 906 pass/mapped
readability   status 3658 pass                     3545 pass
syncthing     status 8233 pass                     7902 pass
```

## Findings

1. **Critical - the repository is still not an acceptance checkpoint.**
   - Paths: `goal.md:29`, `goal.md:48`, `progress.md:48`,
     `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/esbuild/lane-status.json`, `lanes/gitoxide/lane-status.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, committed,
     cleaned up, and assigned onward.
   - Evidence: every sampled lane status still says `pending`,
     `uncommitted`, `not committed`, or equivalent dirty-worktree handoff
     prose. `HEAD` moved twice to integration-hold commits, not a lane
     acceptance commit, while the dirty aggregate remained hundreds of tracked
     files changed.

2. **Critical - root verification is duplicate-blocked and non-attributable.**
   - Paths: `tools/run-tests.php`, `goal.md:49`, `progress.md:48`.
   - Goal requirement at risk: repo-wide tests and static checks must be run
     periodically and recorded honestly from a stable tree.
   - Evidence: `pgrep -af '^php tools/run-tests\.php( |$)'` initially returned
     no rows, then matched active no-argument root PID `59140` owned by
     `claude` plus focused lane shards. During the same audit, `HEAD` moved
     from `3ce6a9a30ba2` through `078670a0e122` to `275cf4fb03f7`,
     status rows moved `18596 -> 18674`, and shortstat moved from
     `329 files changed, 247181 insertions(+), 30401 deletions(-)` to
     `329 files changed, 248502 insertions(+), 30508 deletions(-)`.

3. **High - `porting.html` and `porting-summary.json` are stale against all lanes.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:38`, `porting-summary.json:3`, `porting-summary.json:4`,
     `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: dashboard artifacts still claim source `89260857cc71` generated
     at `2026-05-24 12:29:46 UTC`, while current `HEAD` is `275cf4fb03f7` and
     every lane has newer manifest/status counts than the dashboard table.

4. **High - support-library coverage remains backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:4`, `dependency-backlog.json:7`,
     `dependency-backlog.json:25`, `dependency-backlog.json:81`,
     `dependency-backlog.json:129`, `dependency-backlog.json:145`,
     `dependency-backlog.json:163`, `dependency-backlog.json:179`,
     `dependency-backlog.json:214`, `dependency-backlog.json:233`,
     `dependency-backlog.json:256`, `dependency-backlog.json:272`,
     `dependency-backlog.json:322`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:629`,
     `goal.md:35`, `goal.md:40`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much upstream/spec-suite evidence as can run.
   - Evidence: `dependency-backlog.json` has 37 rows and 0 active rows; only
     the 12 base-lane root manifests exist under `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
     Pandoc's DOC, DOCX/OpenXML, PDF output/input handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML metadata, and archive/compression
     needs are accounted for as gated rows, but none has a separate
     dependency-specific manifest, PHP ledger, malformed/corrupt evidence, or
     bounded `sudo -n` install-attempt/ruled-out notes.

5. **High - dependency-adjacent code is spreading lane-locally before shared support gates are active.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:629`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:20`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:191`,
     `lanes/rclone/src/VfsZipArchive.php:7`,
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/GzipReader.php:7`,
     `lanes/rclone/src/VfsWebDavCompression.php:7`.
   - Goal requirement at risk: optional support libraries must be bounded,
     activation-gated, tested against dependency-specific denominators, and
     shared across lanes where they are common needs.
   - Evidence: markerPDF already has lane-local ZIP archive inspection via
     `ZipArchive`, and rclone carries lane-local ZIP/gzip/WebDAV compression
     helpers. These may be valid lane-local slices, but they cannot count as
     `shared-zip-package-core` or `archive-compression-streams` progress until
     the support rows are activated with their own manifests, malformed archive
     cases, PHP pass/fail ledgers, and cross-lane reuse contracts.

6. **High - markerPDF still mixes native PDF progress with external runtime and shell-boundary plans.**
   - Paths: `goal.md:1`, `goal.md:30`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:805`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:807`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1113`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1129`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1134`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as native deliverables.
   - Evidence: markerPDF has real native PDF extraction movement, now 359/408
     mapped with 496 local behavior tests. The same denominator/status surface
     still includes Streamlit/FastAPI/Uvicorn plans, OCR/Tesseract/Ghostscript
     install planning, Pandoc/XeLaTeX helper planning, Poetry/package planning,
     and model stack boundaries. These must stay explicit blockers,
     supplied-runner contracts, or non-goals, not accepted native progress.

7. **Medium - Pandoc rich-function support is routed but not proven.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:318`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:319`,
     `dependency-backlog.json:81`, `dependency-backlog.json:129`,
     `dependency-backlog.json:145`, `dependency-backlog.json:163`,
     `dependency-backlog.json:179`, `dependency-backlog.json:214`,
     `dependency-backlog.json:233`, `dependency-backlog.json:256`.
   - Goal requirement at risk: Pandoc must account for essential rich document
     and PDF conversion libraries with real upstream/spec-suite evidence rather
     than fixture-only credit unless broader suites were attempted and bounded.
   - Evidence: Pandoc reports 99% progress, 2,005/2,276 mapped, and 373 PHP
     behavior tests, but full Haskell runner parity remains unexecuted. The
     dependency rows cover the requested DOC, DOCX/OpenXML, PDF, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package-container,
     XML/HTML, Unicode/charset, and archive/compression areas, but they remain
     inactive backlog entries without support manifests.

8. **Medium - near-complete percentages overstate accepted parity.**
   - Paths: `porting.html:32`, `lanes/gitoxide/lane-status.json`,
     `lanes/markerpdf/lane-status.json:4`, `lanes/pandoc/lane-status.json:4`,
     `goal.md:35`, `goal.md:37`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be blockers or
     future slices.
   - Evidence: the public dashboard still reports `98.3%` average progress and
     most lane statuses report 98 or 99, while every lane handoff is pending or
     uncommitted, root verification is not attributable to a frozen snapshot,
     several full upstream runners remain static/bounded/unexecuted, and
     support-library work has no active bounded port.

9. **Medium - recent history remains audit/status-hold dominated.**
   - Paths: `audits/latest.md`, `audits/integration-status.md`,
     `progress.md:48`, `goal.md:20`, `goal.md:48`.
   - Goal requirement at risk: the supervisor must integrate useful work,
     enforce standards, keep the roadmap honest, and assign the next
     highest-value slice after verification.
   - Evidence: the latest commits are `275cf4fb Record integration hold
     status`, `078670a0 Record integration hold status`, `3ce6a9a3 Record
     integration hold status`, `1e785852 Refresh independent audit status`, and
     `bda91e40 Record integration hold status`. That is coordination churn, not
     accepted lane implementation.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. Require
two stable polls of `HEAD`, default/tracked status counts, shortstat,
`pgrep -af '^php tools/run-tests\.php( |$)'`, dashboard/dependency counts,
lane status timestamps, and relevant log mtimes. Accept exactly one
owner-free lane batch at a time, normalizing manifest/status schema and commit
fields before claiming progress. Promote support libraries only behind an
accepted base-lane gate or true component blocker, each with its own manifest
and malformed-case evidence. Regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the accepted commit, then run one serialized
no-argument root harness only if the exact process gate remains empty on that
frozen snapshot.
