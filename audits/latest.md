# Independent Audit - 2026-05-24T19:13Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `7370ac38 Record libsqlite handoff rejection`.
I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 19:00-19:13
HEAD moved during audit: 22e041c9620e -> fb2cbe491951 -> 836e60b2fb57 -> 3ebca3ab3ad9 -> 7370ac38f396
recent history: 7370ac38 Record libsqlite handoff rejection; 3ebca3ab Record rclone handoff rejection; 836e60b2 Record Gitoxide handoff rejection; fb2cbe49 Record markerPDF handoff rejection; 22e041c9 Refresh independent audit status
branch sample: main...origin/main [ahead 991, behind 68]
tracked dirty rows moved: 330 -> 243 -> 246 -> 249
default status rows including untracked moved: 21648 -> 21661 -> 21460 -> 21567 -> 21637 -> 21703
dirty shortstat moved: 330 files changed, 274932 insertions(+), 33779 deletions(-) -> 249 files changed, 259410 insertions(+), 33380 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started; exact no-argument root gate was clear early, briefly occupied by non-audit root PIDs, cleared, and was active again in the final sample; the tree was not a frozen accepted snapshot
```

Required exact pre-root process gate:

```text
2026-05-24T19:00:34Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:01:52Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:02:24Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:05:05Z pgrep -af '^php tools/run-tests\.php$': 1015626 php tools/run-tests.php
owner evidence attempt: ps -o pid,user,ppid,stat,etime,args -p 1015626 returned only the header because the process exited between pgrep and ps
2026-05-24T19:05:32Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:09:21Z pgrep -af '^php tools/run-tests\.php$': 1123709 php tools/run-tests.php
owner evidence: 1123709 claude 1093724 Rs 00:22 php tools/run-tests.php
2026-05-24T19:11:51Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:13:47Z pgrep -af '^php tools/run-tests\.php$': 1143717 php tools/run-tests.php
owner evidence: 1143717 claude 1143662 R+ 00:21 php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The exact no-argument root harness
gate was clear in early samples, briefly occupied by one non-audit root process,
cleared, was later occupied by active non-audit PID `1123709`, cleared again,
and was active as PID `1143717` in the final sample. I did not start a
duplicate. The stability gate failed:
`HEAD` changed during audit sampling, tracked/default dirty rows and shortstat
moved materially, recent integration commits are still rejections, and no
owner-free reduced lane batch was accepted into a frozen repository snapshot.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          manifest mapped/total     status phpPass field
difftastic    1075/1225                 3671
dolt          613/613                   452 (manifest native PHP ledger still says 442)
esbuild       473/2567                  473
gitoxide      2877/2877                 7606
libsqlite     378/1589                  378
LightningCSS  2992/3548                 3910
markerPDF     161/non-numeric total     266
pandoc        2276/2276                 395
quadrable     55/55                     256
rclone        975/1601                  975
Readability   1984/1984                 3856
syncthing     658/658                   8983
```

## Findings

1. **Critical - the repository is still a live dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:15`, `progress.md:49-51`,
     `lanes/difftastic/lane-status.json:11-14`,
     `lanes/dolt/lane-status.json:11-14`,
     `lanes/esbuild/lane-status.json:11-14`,
     `lanes/gitoxide/lane-status.json:11-14`,
     `lanes/libsqlite/lane-status.json:11-14`,
     `lanes/lightningcss/lane-status.json:11-14`,
     `lanes/markerpdf/lane-status.json:11-14`,
     `lanes/pandoc/lane-status.json:11-14`,
     `lanes/quadrable/lane-status.json:11-14`,
     `lanes/rclone/lane-status.json:11-14`,
     `lanes/readability/lane-status.json:11-14`,
     `lanes/syncthing/lane-status.json:11-14`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified handoffs,
     and visible stable progress for every lane.
   - Evidence: `HEAD` moved from `22e041c9620e` to `fb2cbe491951` to
     `836e60b2fb57` to `3ebca3ab3ad9` to `7370ac38f396` while this audit was running. Tracked
     dirty rows moved `330 -> 243 -> 246 -> 249`; default status rows including
     untracked files moved `21648 -> 21661 -> 21460 -> 21567 -> 21637 -> 21703`; and
     dirty shortstat moved from
     `330 files changed, 274932 insertions(+), 33779 deletions(-)` to
     `249 files changed, 259410 insertions(+), 33380 deletions(-)`. The
     sampled lane statuses still describe pending, uncommitted, reduced,
     stale-commit, or supervisor-owned work rather than accepted lane commits.

2. **Critical - no trustworthy no-argument root acceptance result exists for the current tree.**
   - Paths: `tools/run-tests.php`, `progress.md:51`,
     `audits/integration-status.md:14-24`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; this audit run also required the exact
     duplicate-root gate before any root run.
   - Evidence: the exact root gate returned no rows in early samples, then
     briefly matched `1015626 php tools/run-tests.php`; `ps` owner evidence
     could not be captured because that process exited between `pgrep` and
     `ps`. A later exact gate matched active PID `1123709 php tools/run-tests.php`
     with owner evidence `1123709 claude 1093724 Rs 00:22 php tools/run-tests.php`.
     A still later sample returned no rows, but the final exact gate matched
     active PID `1143717 php tools/run-tests.php` with owner evidence
     `1143717 claude 1143662 R+ 00:21 php tools/run-tests.php`.
     I did not start a duplicate or any audit-owned root run because the
     checkout failed the frozen-snapshot gate. The newest integration entries
     for markerPDF and Gitoxide are rejections and do not produce accepted root
     results.

3. **Critical - `porting.html` and `porting-summary.json` are stale against current lane metadata.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:1-18`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase, audit,
     current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71`, generated
     `2026-05-24 12:29:46 UTC`, while current sampled `HEAD` is
     `7370ac38f396`. Current lane files report Difftastic `1075/1225` and
     `3671` pass-field units while the dashboard says `851/1077` and `3245`;
     Dolt `452` pass-field units while the dashboard says `425`; esbuild
     `473/2567` while the dashboard says `429/2567`; libsqlite
     `378/1589` while the dashboard says `349/1589`; LightningCSS `2992/3548`
     and `3910` pass-field units while the dashboard says `2765/3548` and `4065`;
     markerPDF now has a non-numeric manifest denominator total with `161`
     mapped units and `266` status units while the dashboard says `347/396` and
     `484`; Pandoc `395` while the dashboard says `362`; rclone `975/1601`
     mapped and status units while the dashboard says `906/1601`; Readability status now says
     `3856` while the dashboard still says `3545`; Quadrable `256` while the
     dashboard says `232`; and Syncthing `8983` while
     the dashboard says `7902`.

4. **High - recent integration history confirms broad dirty batches are still being rejected.**
   - Paths: `audits/integration-status.md:3-78`,
     `audits/integration-status.md:80-120`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small correct slices, passing tests, and supervisor
     verification before committing or integrating output.
   - Evidence: recent history is dominated by rejection or hold commits:
     libsqlite, rclone, Gitoxide, markerPDF, LightningCSS, repeated Readability,
     Quadrable, Pandoc, and Difftastic. The newest libsqlite rejection says the
     tracked diff is an accumulated multi-slice rewrite across JSON5, JSONB,
     JSON path, B-tree/page/header/database, create-index parsing, notes, and a
     very large test expansion, while the untracked set has many older storage
     and example files. The Gitoxide rejection says the
     focused discovery evidence is plausible, but the dirty Gitoxide state
     includes older credential, protocol, fetch, pack, push, receive-pack,
     sparse checkout, object database, reference, and multi-pack-index files.
     The markerPDF rejection similarly found `89` tracked files and broad
     benchmark, server/runtime, OCR, table, image, metadata, and PDF parser/test
     changes behind a narrow focused claim. The newest rclone rejection says
     status/manifest claim a WebDAV PUT slice that is not present in the
     tracked patch while broad untracked rclone files remain accumulated.

5. **High - manifest/status ledgers remain non-atomic and internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2589-2593`,
     `lanes/dolt/lane-status.json:5-14`,
     `lanes/lightningcss/lane-status.json:5-14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-19`,
     `lanes/markerpdf/lane-status.json:5-14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/readability/lane-status.json:5-14`,
     `porting-summary.json:1-18`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Dolt status reports `452` PASS cases while its manifest native
     PHP ledger still says `phpBehaviorTests: 442`. MarkerPDF's manifest has
     `benchmarkDenominator.total` as a long prose string instead of a numeric
     denominator, while status reports `266` PHP behavior tests and a reduced
     uncommitted batch. LightningCSS status says its latest update is
     `HEAD 836e60b2fb57`, but current `HEAD` is `3ebca3ab3ad9`. Readability's
     manifest records the full `1984/1984` mapped denominator and a
     `nativeImplementation` count of `290`, while lane status reports `3856`
     pass-field units. These are
     coordination-write and unit-normalization defects, not implementation
     progress.

6. **High - support-library coverage is visible but still not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:1-22`,
     `dependency-backlog.json:80-95`,
     `dependency-backlog.json:129-176`,
     `dependency-backlog.json:179-190`,
     `dependency-backlog.json:400-426`,
     `dependency-backlog.json:629-646`,
     `porting.html:71-78`, `progress.md:17-36`.
   - Goal requirement at risk: `goal.md:35-40` require meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers. The latest support-library directive requires bounded
     native support components with activation gates, dependency-specific
     upstream/spec denominators, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and bounded `sudo -n`
     install-attempt or ruled-out notes when packages are missing.
   - Evidence: the backlog has all Pandoc needs named in the latest directive:
     DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     templates, citations, math, tables, package containers, XML/HTML,
     Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression. It also routes important rich behavior for the other
     base tools through shared WebDAV, URL, source-map, browser-target,
     package-resolution, tree-sitter, sequence-diff, protobuf, checksum, SQL,
     archive, pathspec, and metadata rows. But there are still `0` active
     support rows, no accepted dependency-specific support manifests, no
     support PHP pass/fail ledgers, no malformed/corrupt evidence records, no
     accepted activation records, and no bounded install-attempt notes. None of
     the current lane-local rich slices should receive support-library progress
     credit.

7. **High - Pandoc remains far short of the original rich conversion-kernel goal despite `99%` status language.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:396-400`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1405-1411`,
     `dependency-backlog.json:80-95`,
     `dependency-backlog.json:129-176`,
     `dependency-backlog.json:413-426`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: Pandoc records `2276/2276` mapped focused checks, but the
     denominator is still a cloned static inventory, not full Haskell runner
     parity. The latest slice explicitly does not invoke upstream Pandoc, live
     fetching, browser tooling, converter shell-outs, PDF processing,
     ZIP/package parsers, citation/CSL engines, PlainMath/MathML conversion,
     TeX math/ref conversion, or broader XML/HTML/syntax-highlighting support.
     DOC/DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     citations, math, templates, tables, JSON/YAML metadata, package
     containers, XML/HTML, Unicode, charset, syntax highlighting, and
     archive/compression remain inactive support rows rather than accepted
     conversion-kernel coverage.

8. **High - markerPDF still mixes useful native PDF extraction with plan-only runtime/application evidence.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:847-858`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:863-870`,
     `lanes/markerpdf/lane-status.json:5-14`,
     `audits/integration-status.md:3-78`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and
     `goal.md:35-40` say wrappers, shell-outs, and plan-only application
     behavior must not count as native implementation progress.
   - Evidence: the native PDF text extraction slices are useful and explicitly
     avoid Python/pdftext/pypdfium/Poppler/Ghostscript, but the current manifest
     has also regressed to a prose denominator and still carries Streamlit app
     plans, FastAPI/Uvicorn server shape, OCR install plans, Ghostscript/Tesseract
     build plans, Poetry/package metadata, lockfile/package artifact inventory,
     Nougat subprocess planning, shell lifecycle planning, benchmark archive
     planning, and model-runtime dependency graphs. Those can be
     coordination/preflight evidence, but not native port progress for the PDF
     extraction pipeline. The newest integration decision rejected the
     markerPDF handoff because the dirty scope is much broader than the focused
     spacing claim.

9. **Medium - the 98-99 percent progress claims remain misleading.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:7-18`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35-40` require meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers for hard features.
   - Evidence: the published average remains `98.3%` and most lane statuses
     say `98-99%`, but the dashboard is stale, no accepted root result exists
     for the current tree, lane work is pending/uncommitted/reduced or
     rejected/deferred, full upstream runners remain static-only or bounded in
     several lanes, manifest/status units disagree, and no support-library row
     is active.

## Next Intervention

Freeze or wait out active lane workers, root/focused runners, status
publishers, dashboard publishers, evaluator/auditor loops, capacity jobs, and
integrator loops. Require two stable dirty-count/HEAD polls with
`pgrep -af '^php tools/run-tests\.php$'` clear before any audit-owned root run.
The best next intake remains a single owner-free reduced lane batch from a
frozen snapshot: honor the current libsqlite, rclone, Gitoxide, and markerPDF
rejections by re-emitting only the smallest required foundation plus the focused
slice, or choose another ready lane whose dirty scope already matches its evidence.
Normalize markerPDF's denominator schema, Dolt/readability manifest-status
count drift, and stale status commit fields; enforce atomic manifest/status
writes; run focused verification plus `git diff --check`; run exactly one
serialized no-argument `php tools/run-tests.php` only from a frozen accepted
snapshot; regenerate `porting.html` and `porting-summary.json` from the
accepted commit; and keep support-library rows inactive until a base-lane rich
slice is accepted or accepted-blocked on one bounded component.
