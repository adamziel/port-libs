# Independent Audit - 2026-05-24T15:30Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through `c959e048 Refresh independent audit status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 15:28-15:30
observed HEAD: c959e048ce0b
recent history: c959e048 Refresh independent audit status; 508e35d0 Refresh independent audit status; 47b35a65 Record integration hold status; 712f22ba Record integration hold status
tracked dirty files: 330
git diff --shortstat: 330 files changed, 257463 insertions(+), 33513 deletions(-) -> 330 files changed, 257559 insertions(+), 33519 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
15:28-15:29Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact no-argument root gate was
empty, but the checkout changed during the audit: shortstat moved by 96
insertions and 6 deletions while `HEAD` remained `c959e048ce0b`. A
no-argument root result from this source would not be attributable to a frozen
manifest/status/dashboard snapshot.

Current manifest/status sample versus the published dashboard:

```text
lane          current manifest/status                 dashboard
difftastic    manifest 980/1193, status 3468 pass     3245 pass, 851/1077
dolt          status 432 pass, manifest php 430       425 pass, 613/613
esbuild       451 pass, 451/2567 mapped               429 pass, 429/2567
gitoxide      7349 pass, 2877/2877 mapped             7152 pass, 2877/2877
libsqlite     status moved to 365 pass                348 pass, 349/1589
LightningCSS  4178 pass, 2860/3548 mapped             4065 pass, 2765/3548
markerPDF     manifest/status 502 pass, 365/414       484 pass, 347/396
pandoc        379 pass, 2065/2276 mapped              362 pass, 1891/2276
quadrable     242 pass, 55/55 mapped                  232 pass, 55/55
rclone        951 pass, 951/1601 mapped               906 pass, 906/1601
readability   3703 assertions, 1984/1984 mapped       3545 pass, 1984/1984
syncthing     8352 pass, 658/658 mapped               7902 pass, 658/658
```

## Findings

1. **Critical - the repo is still a moving dirty aggregate, not an acceptance checkpoint.**
   - Paths: `progress.md`, `lanes/*/lane-status.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: "Commit small, reviewable slices with passing
     tests" and keep current work, blockers, and latest commits precise.
   - Evidence: `git diff --shortstat` moved from
     `330 files changed, 257463 insertions(+), 33513 deletions(-)` to
     `330 files changed, 257559 insertions(+), 33519 deletions(-)` during the
     audit. Every sampled lane status still records `pending`, `uncommitted`,
     or owner-deferral prose rather than accepted implementation commit
     boundaries, including `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.

2. **Critical - root acceptance remains non-attributable from this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: "Periodically run repo-wide tests and static
     checks. Record failures honestly."
   - Evidence: the exact root gate returned no rows, but the tree changed
     during the same audit. Lane blockers explicitly leave root verification
     pending for the supervisor/integrator across the portfolio, for example
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.

3. **High - `porting.html` and `porting-summary.json` are stale against every active lane.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:38`,
     `porting.html:56`, `porting.html:62`, `porting.html:63`,
     `porting-summary.json:2`, `porting-summary.json:3`,
     `porting-summary.json:8`.
   - Goal requirement at risk: the dashboard must show current upstream
     denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase,
     audit, current work, blocker, and commit.
   - Evidence: both dashboard artifacts still publish source
     `89260857cc71` generated `2026-05-24 12:29:46 UTC`, while observed `HEAD`
     is `c959e048ce0b`. Examples: Difftastic is now `980/1193` and `3468`
     pass while the dashboard shows `851/1077` and `3245`; markerPDF is now
     `365/414` and `502` pass while the dashboard shows `347/396` and `484`;
     Pandoc is now `2065/2276` and `379` pass while the dashboard shows
     `1891/2276` and `362`.

4. **High - manifest/status ledgers are internally inconsistent and too prose-heavy for acceptance.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2574`,
     `lanes/dolt/lane-status.json:6`, `lanes/libsqlite/lane-status.json:6`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:890`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:339`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13`.
   - Goal requirement at risk: every lane needs machine-checkable upstream
     denominator, mapped upstream tests, PHP pass/fail, phase, audit status,
     blocker, and latest commit.
   - Evidence: Dolt's manifest still records `phpBehaviorTests: 430` while
     status reports `phpPass: 432`. Libsqlite changed from the manifest/status
     sample at `364` to status `365` during this audit. Several
     `benchmarkDenominator.status` fields are long concatenated histories
     instead of bounded status plus evidence records, which lets dashboard,
     manifest, and lane status drift independently.

5. **High - support-library coverage remains backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:81`,
     `dependency-backlog.json:129`, `dependency-backlog.json:145`,
     `dependency-backlog.json:163`, `dependency-backlog.json:179`,
     `dependency-backlog.json:214`, `dependency-backlog.json:233`,
     `dependency-backlog.json:256`, `dependency-backlog.json:272`,
     `dependency-backlog.json:322`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:391`,
     `dependency-backlog.json:413`, `dependency-backlog.json:629`,
     `porting.html:72`, `porting.html:75`, `porting.html:77`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec
     denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt
     cases where relevant, and as much upstream/full-suite evidence as can
     honestly run.
   - Evidence: the tracker has 37 rows and 0 active support ports. Pandoc's
     DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     templates, citations, math, tables, package containers, XML/HTML,
     Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression categories are visible as gated rows, but none has a
     support-library manifest, PHP ledger, malformed/corrupt evidence,
     accepted activation record, or bounded install-attempt/ruled-out note.

6. **High - dependency-adjacent rich work is still lane-local and cannot count as support-library progress.**
   - Paths: `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:11`,
     `dependency-backlog.json:272`, `dependency-backlog.json:322`,
     `dependency-backlog.json:629`.
   - Goal requirement at risk: dependency expansion must be bounded, gated,
     tested, and shared across lanes when it implements an essential rich
     function.
   - Evidence: markerPDF is growing PDF text/page/resource behavior while
     `pdf-text-dictionary-core`, `layout-ocr-result-core`, and
     `table-geometry-core` remain inactive. Rclone is growing WebDAV behavior
     while `webdav-protocol-core` remains candidate-only. Pandoc's current
     RawInline slice explicitly avoids shared ZIP/OpenXML/OpenDocument, PDF,
     CSL, PlainMath/MathML, Unicode/charset, and syntax-highlighting rows.
     Those lane-local slices may be useful, but they are not reusable support
     library progress until their own manifests, ledgers, malformed cases, and
     activation gates exist.

7. **High - Pandoc has a broad map, but the original conversion-kernel goal is not proven.**
   - Paths: `goal.md`, `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:10`, `lanes/pandoc/lane-status.json:12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:339`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:341`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1295`.
   - Goal requirement at risk: Pandoc must become a document conversion kernel
     with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: Pandoc now maps `2065/2276` focused checks and `379` behavior
     tests, but full Haskell runner parity remains unexecuted. The current
     slice is RawInline HTML writer behavior, while TeX RawInline math/ref,
     PlainMath/MathML, support-library package formats, PDF handoff, and WXR
     remain unproven as accepted native components. A repo search found `WXR`
     only inside generic Markdown test text, not as a visible Pandoc lane
     reader/writer capability.

8. **Medium - near-complete percentages overstate accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: passing focused tests are not enough; hard
     upstream gaps, fixture parity, error behavior, edge cases, docs/examples,
     blockers, and acceptance state must remain visible.
   - Evidence: the dashboard still reports `98.3%` average progress and most
     lanes at `98-99%`, while every lane remains unaccepted in a dirty moving
     worktree, root verification is pending, several full upstream runners are
     static/bounded/unexecuted, and zero support-library rows are active.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. Require
two stable polls of `HEAD`, tracked/default status counts, shortstat, exact
root gate `pgrep -af '^php tools/run-tests\.php$'`, dashboard/dependency
counts, lane status timestamps, and relevant log mtimes. Accept exactly one
owner-free lane batch at a time, first normalizing manifest/status schema,
counts, and commit fields. Promote support libraries only behind an accepted
base-lane gate or true component blocker, each with its own manifest,
malformed-case evidence, PHP ledger, and bounded install-attempt note.
Regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the
accepted commit, then run one serialized no-argument root harness only if the
exact process gate remains empty on that frozen snapshot.
