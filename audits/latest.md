# Independent Audit - 2026-05-24T15:25Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through `508e35d0 Refresh independent audit status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 15:22-15:25
observed HEAD: 508e35d03003
recent history: 508e35d0 Refresh independent audit status; 47b35a65 Record integration hold status; 712f22ba Record integration hold status; b03fca97 Refresh independent audit status
default status rows including untracked: 19145 -> 19148 -> 19149
tracked dirty files: 329 -> 329 -> 330
git diff --shortstat: 329 files changed, 256658 insertions(+), 33499 deletions(-) -> 330 files changed, 256799 insertions(+), 33499 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
15:23:08Z pgrep -af '^php tools/run-tests\.php$': no rows
15:23:28Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact no-argument root gate was
empty during the stability sample, but the checkout was still changing within
20 seconds: status rows, tracked dirty file count, and shortstat all moved.
That would make a no-argument root result non-attributable to a frozen source
snapshot.

Current manifest/status sample versus the published dashboard:

```text
lane          current manifest/status                 dashboard
difftastic    manifest 980/1193, status 3449 pass     3245 pass, 851/1077
dolt          status 431 pass, manifest php 430       425 pass, 613/613
esbuild       451 pass, 451/2567 mapped               429 pass, 429/2567
gitoxide      7349 pass, 2877/2877 mapped             7152 pass, 2877/2877
libsqlite     364 pass, 364/1589 mapped               348 pass, 349/1589
LightningCSS  4178 pass, 2860/3548 mapped             4065 pass, 2765/3548
markerPDF     status 501 pass, manifest php 498       484 pass, 347/396
pandoc        378 pass, 2057/2276 mapped              362 pass, 1891/2276
quadrable     242 pass, 55/55 mapped                  232 pass, 55/55
rclone        949 pass, 949/1601 mapped               906 pass, 906/1601
readability   3693 assertions, 1984/1984 mapped       3545 pass, 1984/1984
syncthing     8336 pass, 658/658 mapped               7902 pass, 658/658
```

## Findings

1. **Critical - the repository is still a moving dirty aggregate, not an acceptance checkpoint.**
   - Paths: `progress.md`, `lanes/*/lane-status.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: "Commit small, reviewable slices with passing
     tests" and keep each lane's current work, blocker, and latest commit
     precise.
   - Evidence: the stability sample moved from 19148 to 19149 default status
     rows, 329 to 330 tracked dirty files, and
     `329 files changed, 256677 insertions(+), 33499 deletions(-)` to
     `330 files changed, 256799 insertions(+), 33499 deletions(-)` in 20
     seconds. All lane status files still record `pending`, `uncommitted`, or
     lane-local latest-commit prose rather than accepted implementation commit
     boundaries, for example `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`, `lanes/markerpdf/lane-status.json:13`,
     and `lanes/syncthing/lane-status.json:13`.

2. **Critical - no trustworthy root acceptance result can be produced from this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: "Periodically run repo-wide tests and static
     checks. Record failures honestly."
   - Evidence: the required exact root gate was empty at 15:23:08Z and
     15:23:28Z, but the tree changed during the same sample. Starting
     `php tools/run-tests.php` would create another result that cannot be tied
     to one `HEAD`, manifest set, or lane-status set. The lane statuses also
     repeatedly say root verification was not assigned to lane workers, leaving
     root acceptance pending across the portfolio.

3. **High - `porting.html` and `porting-summary.json` are stale against every lane.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:38`, `porting.html:56-67`, `porting-summary.json:2-8`.
   - Goal requirement at risk: the dashboard must show current upstream
     denominator, mapped tests, PHP pass/fail, scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: both dashboard artifacts still publish source
     `89260857cc71` from `2026-05-24 12:29:46 UTC`, while the observed `HEAD`
     is `508e35d03003` and current lane counts have advanced. Examples:
     Difftastic is now manifest `980/1193` with `3449` PHP assertions while
     the dashboard shows `851/1077` and `3245`; markerPDF is now manifest
     `364/413` and status `501` PHP behavior tests while the dashboard shows
     `347/396` and `484`; Pandoc is now `2057/2276` and `378` PHP tests while
     the dashboard shows `1891/2276` and `362`.

4. **High - manifest/status ledgers are internally contradictory, not just stale externally.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14-15`,
     `lanes/difftastic/lane-status.json:5`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2571`,
     `lanes/dolt/lane-status.json:6`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:888`,
     `lanes/markerpdf/lane-status.json:6`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:879-881`.
   - Goal requirement at risk: each lane needs a real upstream denominator,
     mapped upstream tests, PHP passing/failing counts, phase, audit status,
     blocker, and latest commit that are machine-checkable.
   - Evidence: Difftastic's manifest now says `1193` total and `980` mapped,
     while its lane status prose still says `1182` artifacts and `969`
     mappings. Dolt's manifest still records `phpBehaviorTests: 430` while the
     status reports `phpPass: 431`. markerPDF's status reports `501` PHP
     behavior tests, while the manifest native implementation still records
     `phpBehaviorTests: 498` and its latest addendum text still describes the
     older `410/361` slice. These are coordination data defects, not
     implementation defects, but they make acceptance claims unreliable.

5. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:3-4`, `dependency-backlog.json:7-22`,
     `dependency-backlog.json:81-95`, `dependency-backlog.json:129-142`,
     `dependency-backlog.json:145-160`, `dependency-backlog.json:163-176`,
     `dependency-backlog.json:179-192`, `dependency-backlog.json:195-211`,
     `dependency-backlog.json:214-230`, `dependency-backlog.json:233-253`,
     `dependency-backlog.json:256-269`, `dependency-backlog.json:272-286`,
     `dependency-backlog.json:322-337`, `dependency-backlog.json:340-388`,
     `dependency-backlog.json:391-427`, `dependency-backlog.json:629-646`,
     `porting.html:72-78`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much upstream/full-suite evidence as can honestly run.
   - Evidence: the tracker has 37 rows and 0 active support ports. Pandoc's
     required DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression categories are visible as gated rows, but none has a
     support-library manifest, PHP ledger, malformed/corrupt evidence, accepted
     base-lane activation record, or bounded install-attempt/ruled-out note.

6. **High - rich-function work is advancing inside base lanes before the reusable support gates are accepted.**
   - Paths: `lanes/markerpdf/lane-status.json:5`, `lanes/markerpdf/lane-status.json:10-12`,
     `lanes/markerpdf/src/PdfTextExtractor.php:681`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:36`,
     `lanes/rclone/lane-status.json:11-12`,
     `lanes/rclone/src/GzipReader.php:7`,
     `lanes/rclone/src/VfsWebDavCompression.php:8`,
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `dependency-backlog.json:45-58`,
     `dependency-backlog.json:272-286`,
     `dependency-backlog.json:629-646`.
   - Goal requirement at risk: dependency expansion must be bounded, gated,
     tested, and shared across lanes when it implements an essential rich
     function.
   - Evidence: markerPDF continues to grow native `PdfTextExtractor` PDF
     object/resource/filter behavior and uses `ZipArchive` for benchmark
     archive inspection while `pdf-text-dictionary-core`,
     `shared-zip-package-core`, and `archive-compression-streams` remain
     inactive. rclone has lane-local WebDAV, gzip, and ZIP helpers while
     `webdav-protocol-core` and `archive-compression-streams` remain inactive.
     These may remain lane-local scaffolds until accepted, but they cannot be
     credited as support-library progress without dependency-specific
     denominators, malformed cases, PHP ledgers, and reuse contracts.

7. **High - Pandoc still has the right rich dependency map but not a proven native conversion kernel.**
   - Paths: `goal.md`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:335`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1289`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:10-12`,
     `dependency-backlog.json:81-95`,
     `dependency-backlog.json:129-192`,
     `dependency-backlog.json:214-269`,
     `dependency-backlog.json:322-337`,
     `dependency-backlog.json:391-427`.
   - Goal requirement at risk: Pandoc must become a document conversion kernel
     with a shared AST and readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented forms, and WordPress block output.
   - Evidence: Pandoc now maps `2057/2276` and reports `378` focused local
     checks, plus smoke parsing of 145 upstream Native expectations, but full
     Haskell runner parity remains unexecuted. The DOC, DOCX, PDF, EPUB, ODT,
     template, citation, math, table, XML/HTML, Unicode/charset, JSON/YAML,
     package, and archive dependencies remain inactive backlog rows instead of
     manifest-backed native components.

8. **Medium - near-complete percentages overstate accepted parity.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: passing focused tests are not enough; upstream
     denominators, fixture parity, edge cases, error behavior, docs/examples,
     blockers, and hard gaps must remain visible.
   - Evidence: the dashboard still reports `98.3%` average progress and most
     lanes at `98-99%`, while every lane is unaccepted in a dirty moving
     worktree, root verification is pending, several full upstream runners are
     static/bounded/unexecuted, and no support-library row is active.

9. **Medium - manifest schema allows long status strings where review needs structured facts.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`.
   - Goal requirement at risk: durable coordination data should be
     machine-checkable across denominator, mapped tests, PHP pass/fail, phase,
     audit status, blocker, and latest commit.
   - Evidence: several manifest `benchmarkDenominator.status` fields are long
     concatenated histories rather than a bounded enum plus evidence records.
     Commit fields mix `pending`, `uncommitted`, stale `HEAD 47b35a65a3e9`,
     and prose. That style makes it easy for dashboard, manifest, and status
     claims to drift apart.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. Require
two stable polls of `HEAD`, tracked/default status counts, shortstat, the exact
root gate `pgrep -af '^php tools/run-tests\.php$'`, dashboard/dependency
counts, lane status timestamps, and relevant log mtimes. Accept exactly one
owner-free lane batch at a time, first normalizing manifest/status schema and
commit fields. Promote support libraries only behind an accepted base-lane gate
or true component blocker, each with its own manifest, malformed-case evidence,
PHP ledger, and bounded install-attempt note. Regenerate `progress.md`,
`porting.html`, and `porting-summary.json` from the accepted commit, then run
one serialized no-argument root harness only if the exact process gate remains
empty on that frozen snapshot.
