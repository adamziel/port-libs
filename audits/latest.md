# Independent Audit - 2026-05-24T14:53Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, support-library tracker
coverage, and recent Git history through `6b6bbffe Refresh independent audit
status`. I did not edit lane implementation files, launch agents or tmux
sessions, push, read secrets, inspect process environments, credential stores,
provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 14:52-14:53
HEAD: 6b6bbffe5d42
recent history: 6b6bbffe Refresh independent audit status; 9affd3c7 Record integration hold status; 24b6fc0b Refresh independent audit status; 7ab00951 Record integration hold status; ede518e4 Record integration hold status
tracked dirty rows: 330
default status rows including untracked moved: 18914 -> 18972
git diff --shortstat moved: 330 files changed, 251586 insertions(+), 31615 deletions(-) -> 330 files changed, 251737 insertions(+), 31615 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC; current HEAD is 6b6bbffe5d42
dependency backlog: dependency-backlog.json has 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
14:52Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
14:53Z pgrep -af '^php tools/run-tests\.php( |$)' matched:
  PID 351078 php tools/run-tests.php
  PID 352820 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php ... lanes/syncthing/tests/RequestServerTest.php
  PID 357250 php tools/run-tests.php lanes/readability/tests/ArticleExtractorTest.php
14:53Z owner evidence:
  PID 351078 user claude elapsed 00:45 cmd php tools/run-tests.php
  PID 352820 user claude elapsed 00:38 cmd php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php ... lanes/syncthing/tests/RequestServerTest.php
  PID 357250 exited before owner sampling
```

I did not start `php tools/run-tests.php`. The exact gate became occupied by an
active no-argument root harness plus focused harnesses, and the checkout also
failed the stability gate because default status rows and shortstat changed
during sampling.

Current dashboard drift sample:

```text
lane          current status/manifest                 dashboard
difftastic    3395 pass, 943/1157 mapped              3245 pass, 851/1077
dolt          429 pass, 613/613 mapped                425 pass, 613/613
esbuild       447 pass, 447/2567 mapped               429 pass, 429/2567
gitoxide      7320 status pass, 2877/2877 mapped      7152 pass, 2877/2877
libsqlite     361 pass, 361/1589 mapped               348 pass, 349/1589
LightningCSS  4158 pass, 2841/3548 mapped             4065 pass, 2765/3548
markerPDF     498 pass, 361/410 mapped                484 pass, 347/396
pandoc        375 pass, 2027/2276 mapped              362 pass, 1891/2276
quadrable     240 pass, 55/55 mapped                  232 pass, 55/55
rclone        943 pass, 943/1601 mapped               906 pass, 906/1601
readability   3676 assertions, 1984/1984 mapped       3545 pass, 1984/1984
syncthing     8265 pass, 658/658 mapped               7902 pass, 658/658
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `goal.md:29`, `goal.md:48`, `progress.md:48`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, committed,
     cleaned up, and assigned onward.
   - Evidence: all 12 lane status files still report `pending`,
     `uncommitted`, `not committed`, or stale commit ownership prose. The dirty
     aggregate is broad (`330` tracked dirty rows, `18,972` default rows), and
     default status rows plus shortstat changed while this audit was sampling.

2. **Critical - root verification is duplicate-blocked and not attributable to a frozen tree.**
   - Paths: `tools/run-tests.php`, `goal.md:49`, `progress.md:48`.
   - Goal requirement at risk: repo-wide tests and static checks must run
     periodically and failures must be recorded honestly from a stable source
     snapshot.
   - Evidence: the required exact `pgrep -af '^php tools/run-tests\.php( |$)'`
     gate matched PID `351078` owned by `claude` running the no-argument root
     harness, plus focused Syncthing PID `352820`; a focused Readability PID
     exited before owner sampling. Starting another root run would have been a
     duplicate, and the working tree moved during the same sampling window.

3. **High - `porting.html` and `porting-summary.json` are stale against every lane.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:38`, `porting.html:56`, `porting.html:67`,
     `porting-summary.json:2`, `porting-summary.json:3`,
     `porting-summary.json:8`, `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: dashboard artifacts still claim source `89260857cc71` generated
     at `2026-05-24 12:29:46 UTC`, while current `HEAD` is `6b6bbffe5d42`.
     All 12 lane rows have newer manifest/status counts than the published
     dashboard table.

4. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:4`, `dependency-backlog.json:7`,
     `dependency-backlog.json:25`, `dependency-backlog.json:81`,
     `dependency-backlog.json:129`, `dependency-backlog.json:145`,
     `dependency-backlog.json:163`, `dependency-backlog.json:179`,
     `dependency-backlog.json:195`, `dependency-backlog.json:214`,
     `dependency-backlog.json:233`, `dependency-backlog.json:256`,
     `dependency-backlog.json:272`, `dependency-backlog.json:289`,
     `dependency-backlog.json:322`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:391`,
     `dependency-backlog.json:413`, `dependency-backlog.json:629`,
     `progress.md:17`, `progress.md:32`, `goal.md:25`, `goal.md:35`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has 37 rows and 0 active rows, and the only
     `UPSTREAM_TEST_MANIFEST.json` files are the 12 base lane manifests.
     Pandoc's DOC, DOCX/OpenXML, PDF handoff/text extraction, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression needs are covered as gated rows, but none has a
     dependency-specific manifest, PHP pass/fail ledger, malformed/corrupt
     evidence, or bounded `sudo -n` install-attempt/ruled-out note for broader
     suites.

5. **High - markerPDF still mixes native PDF work with plan-only external/runtime boundaries.**
   - Paths: `goal.md:1`, `goal.md:30`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php:17`,
     `lanes/markerpdf/src/MarkerRuntimePlanner.php:35`,
     `lanes/markerpdf/src/OcrRecognition.php:193`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as native deliverables.
   - Evidence: markerPDF has real native PDF extraction movement (`361/410`
     mapped, `498` local pass). The same denominator/status surface still
     names marker-server, Streamlit/FastAPI/Uvicorn, Tesseract/OCRMyPDF,
     Ghostscript, Pandoc/XeLaTeX, model/runtime, and chunk-convert shell
     lifecycle planning. Those entries must stay explicit blockers, supplied
     runner contracts, or non-goals until replaced by bounded native PHP
     support components.

6. **High - lane-local dependency helpers are spreading before shared support gates are active.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:629`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:36`,
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/GzipReader.php:7`,
     `lanes/rclone/src/VfsWebDavCompression.php:10`,
     `lanes/rclone/src/VfsVirtualTree.php:653`.
   - Goal requirement at risk: optional libraries must be bounded,
     activation-gated, dependency-specific, tested, and shared across lanes
     where the same rich function is needed.
   - Evidence: markerPDF carries lane-local `ZipArchive` benchmark archive
     inspection, while rclone has lane-local ZIP, gzip, and WebDAV compression
     helpers. These may be useful lane scaffolding, but they cannot count as
     `shared-zip-package-core` or `archive-compression-streams` progress until
     those rows are activated with their own manifest, malformed archive cases,
     PHP ledger, and cross-lane reuse contract.

7. **Medium - Pandoc rich-function coverage is routed, but not proven.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:325`,
     `lanes/pandoc/lane-status.json:10`,
     `lanes/pandoc/lane-status.json:12`,
     `dependency-backlog.json:7`, `dependency-backlog.json:25`,
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
   - Evidence: Pandoc reports `2027/2276` mapped and `375` focused PHP
     behavior tests, but full upstream Haskell runner parity remains
     unexecuted. The latest required rich-function libraries are present as
     gated backlog rows, so the tracker is not missing the categories; the gap
     is that they remain inactive and have no dependency-specific manifests or
     pass/fail evidence.

8. **Medium - near-complete percentages overstate accepted parity.**
   - Paths: `porting.html:32`, `porting.html:56`, `porting.html:67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json`, `goal.md:35`,
     `goal.md:37`, `goal.md:40`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be blockers or
     future slices.
   - Evidence: the dashboard still reports `98.3%` average progress and most
     lanes show 98-99%, but every lane handoff is pending or uncommitted, no
     frozen root result is attached to the current aggregate, multiple upstream
     runners are static/bounded/unexecuted, and no support-library row is
     active.

9. **Medium - manifest/status schema remains too free-form for durable coordination.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/*/lane-status.json:13`, `porting-summary.json:25`,
     `porting-summary.json:42`, `porting-summary.json:59`, `goal.md:3`,
     `goal.md:44`.
   - Goal requirement at risk: progress must track upstream denominator,
     mapped tests, PHP pass/fail counts, phase, audit status, blocker, and
     latest commit in a durable coordination system.
   - Evidence: several `benchmarkDenominator.status` values are long
     concatenated slice slugs, and dashboard commit fields contain truncated
     prose such as `pending`, `not com`, and `uncommi` instead of accepted
     implementation commits. This makes the dashboard look precise while the
     underlying ownership/acceptance state is still ambiguous.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. Require
two stable polls of `HEAD`, tracked/default status counts, shortstat,
`pgrep -af '^php tools/run-tests\.php( |$)'`, dashboard/dependency counts,
lane status timestamps, and relevant log mtimes. Recover the already-running
root PID `351078` result only if it can be tied to a fixed source snapshot;
otherwise mark it as moving-tree evidence only. Accept exactly one owner-free
lane batch at a time, normalizing manifest/status schema and commit fields
before claiming progress. Promote support libraries only behind an accepted
base-lane gate or true component blocker, each with its own manifest and
malformed-case evidence. Regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the accepted commit, then run one serialized
no-argument root harness only if the exact process gate remains empty on that
frozen snapshot.
