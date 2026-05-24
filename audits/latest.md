# Independent Audit - 2026-05-24T15:08Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through observed `05a6bd1a Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 15:07-15:08
observed HEAD movement during audit: 6d3f2dd7 -> 05a6bd1a
recent history: 05a6bd1a Record integration hold status; 6d3f2dd7 Refresh independent audit status; 57ce7c78 Clarify integration hold process sample; fafdce01 Record root retry follow-up; 3f491c42 Record integration hold status
tracked dirty rows: 329
default status rows including untracked: 18951 -> 19011
git diff --shortstat: 329 files changed, 253759 insertions(+), 32369 deletions(-) -> 329 files changed, 253674 insertions(+), 32160 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC; current observed HEAD is 05a6bd1a892f
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
15:07Z pgrep -af '^php tools/run-tests\.php$' matched active no-argument root PID 457576:
  ps owner evidence: claude 457576 00:18 php tools/run-tests.php
15:08Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. A root harness was already active
during the audit window, and after it cleared the checkout was still moving:
`HEAD`, default status rows, shortstat, and lane metadata changed during
sampling.

Current manifest/status sample versus the published dashboard:

```text
lane          current status/manifest                 dashboard
difftastic    3422 pass, 969/1182 mapped              3245 pass, 851/1077
dolt          430 pass, 613/613 mapped                425 pass, 613/613
esbuild       449 pass, 449/2567 mapped               429 pass, 429/2567
gitoxide      7339 pass, 2877/2877 mapped             7152 pass, 2877/2877
libsqlite     363 pass, 363/1589 mapped               348 pass, 349/1589
LightningCSS  4164 pass, 2846/3548 mapped             4065 pass, 2765/3548
markerPDF     499 pass, 363/412 mapped                484 pass, 347/396
pandoc        376 pass, manifest 2049/2276 mapped     362 pass, 1891/2276
quadrable     241 pass, 55/55 mapped                  232 pass, 55/55
rclone        946 pass, 946/1601 mapped               906 pass, 906/1601
readability   3676 assertions, 1984/1984 mapped       3545 pass, 1984/1984
syncthing     8305 pass, 658/658 mapped               7902 pass, 658/658
```

## Findings

1. **Critical - the repository is still a moving integration aggregate, not an acceptance checkpoint.**
   - Paths: `goal.md:3`, `goal.md:29`, `goal.md:44`,
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
   - Goal requirement at risk: each slice should be small, reviewable,
     committed, tracked by current blocker/latest-commit state, and counted
     only after passing verification.
   - Evidence: all 12 lane status files still record `pending`,
     `uncommitted`, `not committed`, or stale commit prose. During this audit
     `HEAD` moved from `6d3f2dd7` to `05a6bd1a`, default status rows moved
     `18951 -> 19011`, and the shortstat changed while lane counts advanced.

2. **Critical - root verification remains unattributable to one frozen tree.**
   - Paths: `tools/run-tests.php`, `goal.md:49`,
     `lanes/dolt/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `progress.md:49`.
   - Goal requirement at risk: repo-wide tests and static checks must run
     periodically and failures must be recorded honestly against a stable
     source snapshot.
   - Evidence: the exact required gate `pgrep -af
     '^php tools/run-tests\.php$'` matched active root PID `457576` owned by
     `claude` (`php tools/run-tests.php`, elapsed `00:18`) at 15:07 UTC. I did
     not start a duplicate. The gate cleared later, but the checkout continued
     moving, so a new audit-owned root run would still be non-attributable.

3. **High - `porting.html` and `porting-summary.json` are stale against every lane.**
   - Paths: `porting.html:34`, `porting.html:35`, `porting.html:38`,
     `porting.html:56`, `porting.html:67`, `porting-summary.json`,
     `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current benchmark
     source, upstream denominator, mapped tests, PHP pass/fail, scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: dashboard artifacts still publish `main 89260857cc71`
     generated at `2026-05-24 12:29:46 UTC`, while current observed `HEAD` is
     `05a6bd1a892f`. Every dashboard row is behind current manifest/status
     counts.

4. **High - support-library coverage is still backlog-only, not lane-granular execution.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:82`, `dependency-backlog.json:132`,
     `dependency-backlog.json:146`, `dependency-backlog.json:164`,
     `dependency-backlog.json:179`, `dependency-backlog.json:214`,
     `dependency-backlog.json:233`, `dependency-backlog.json:256`,
     `dependency-backlog.json:272`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:629`,
     `progress.md:17`, `progress.md:33`, `goal.md:35`.
   - Goal requirement at risk: optional support libraries must have the same
     granularity as lanes: bounded native PHP scope, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has 37 rows and 0 active bounded support ports.
     Pandoc's required DOC, DOCX/OpenXML, PDF handoff/text extraction and
     output, EPUB, ODT/OpenDocument, templates, citations, math, tables,
     package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression areas are routed to gated rows, but
     none has a support-library manifest, PHP ledger, malformed/corrupt
     evidence, or bounded install-attempt/ruled-out note.

5. **High - Pandoc's manifest/status/dashboard disagree, and rich conversion coverage is routed but not proven.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1285`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1288`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:10`,
     `lanes/pandoc/lane-status.json:12`,
     `porting.html:63`.
   - Goal requirement at risk: Pandoc must preserve a shared AST and
     document/PDF-oriented conversion semantics using upstream tests as source
     of truth, with hard gaps recorded as blockers.
   - Evidence: the manifest now records `2049/2276` mapped and a latest
     `HTML writer span-like class lowering` slice, while lane status still
     says `2039` checks and latest `HTML writer styled-inline` work; the
     dashboard is older again at `1891/2276` mapped. Full Haskell
     `test-pandoc`/`test-pandoc-lua-engine` runner parity remains unexecuted,
     and the dependency rows for DOC/DOCX/PDF/EPUB/ODT/citations/math/tables
     remain inactive.

6. **High - markerPDF continues to mix native PDF progress with plan-only external/runtime boundaries.**
   - Paths: `goal.md:1`, `goal.md:9`, `goal.md:30`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:569`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: wrappers, bridge calls, shell-outs, whole apps,
     and external converter/runtime plans cannot count as native deliverables.
   - Evidence: markerPDF has useful native PDF extraction movement (`363/412`
     mapped, `499` local pass), but the same denominator/status surface still
     includes marker server/app, Streamlit/FastAPI/Uvicorn, chunk shell
     lifecycle, Poetry/model runtime, OCR install, OCRMyPDF/Tesseract/
     Ghostscript, Pandoc/XeLaTeX, and pdftext/Python runtime planning. These
     must remain blockers, supplied-runner contracts, or non-goals until
     replaced by bounded native support components.

7. **High - lane-local dependency helpers are expanding ahead of shared support gates.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:629`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:36`,
     `lanes/rclone/src/VfsZipArchive.php`,
     `lanes/rclone/src/GzipReader.php`,
     `lanes/rclone/src/VfsWebDavCompression.php:8`,
     `lanes/rclone/src/VfsVirtualTree.php:653`.
   - Goal requirement at risk: dependency expansion must be bounded,
     activation-gated, dependency-specific, tested, and shared where the same
     rich function is needed across lanes.
   - Evidence: markerPDF uses lane-local `ZipArchive` benchmark inspection,
     while rclone carries lane-local ZIP, gzip, and WebDAV compression helpers.
     They may be valid lane scaffolding, but they cannot count as
     `shared-zip-package-core`, `webdav-protocol-core`, or
     `archive-compression-streams` until those rows have their own manifests,
     malformed archive/protocol cases, PHP ledgers, and reuse contracts.

8. **Medium - near-complete percentages overstate accepted parity.**
   - Paths: `porting.html:32`, `porting.html:56`, `porting.html:67`,
     `lanes/*/lane-status.json`, `goal.md:35`, `goal.md:37`,
     `goal.md:40`.
   - Goal requirement at risk: passing focused tests are not enough; upstream
     denominators, fixture parity, edge cases, error behavior, and hard gaps
     must remain visible.
   - Evidence: the dashboard still reports `98.3%` average progress and most
     lanes report `98-99%`, while every lane handoff is pending/uncommitted,
     root verification is not tied to a frozen snapshot, several full upstream
     runners are static/bounded/unexecuted, and no support-library row is
     active.

9. **Medium - manifest/status schema remains too free-form for durable coordination.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `porting.html:57`, `porting.html:61`, `porting.html:62`,
     `goal.md:3`.
   - Goal requirement at risk: coordination data must be durable and
     machine-checkable across denominator, mapped tests, PHP pass/fail, phase,
     audit status, blocker, and latest commit.
   - Evidence: several `benchmarkDenominator.status` fields are concatenated
     slice histories instead of bounded status enums, and dashboard commit
     fields still truncate prose (`not com`, `uncommi`, `HEAD c9`). The result
     looks precise while ownership, latest slice, and acceptance remain
     ambiguous.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. Require
two stable polls of `HEAD`, tracked/default status counts, shortstat, the exact
root gate `pgrep -af '^php tools/run-tests\.php$'`, dashboard/dependency
counts, lane status timestamps, and relevant log mtimes. Accept exactly one
owner-free lane batch at a time, normalizing manifest/status schema and commit
fields before claiming progress. Promote support libraries only behind an
accepted base-lane gate or true component blocker, each with its own manifest
and malformed-case evidence. Regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the accepted commit, then run one serialized
no-argument root harness only if the exact process gate remains empty on that
frozen snapshot.
