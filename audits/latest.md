# Independent Audit - 2026-05-24T19:30Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `ca5c7111 Record markerPDF handoff rejection`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 19:27-19:30
HEAD moved during this audit window: 4af74d410c0d -> ca5c711190ac
recent history: ca5c7111 Record markerPDF handoff rejection; 4af74d41 Refresh independent audit status; 49b5a511 Record Quadrable handoff rejection; eebc7e29 Refresh independent audit status; 7370ac38 Record libsqlite handoff rejection; 3ebca3ab Record rclone handoff rejection; 836e60b2 Record Gitoxide handoff rejection; fb2cbe49 Record markerPDF handoff rejection
branch sample: main...origin/main [ahead 994, behind 68] before ca5c7111, then local HEAD advanced again
default status rows including untracked moved: 21941 -> 21954 -> 21955 -> 21981 -> 21995
dirty shortstat moved: 242 files changed, 212848 insertions(+), 25658 deletions(-) -> 241 files changed, 213049 insertions(+), 25658 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows, 0 active (blocked 1, candidate 25, deferred 11), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required exact pre-root process gate:

```text
2026-05-24T19:27:44Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:28:14Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:29:19Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:30:04Z pgrep -af '^php tools/run-tests\.php$': 1235372 php tools/run-tests.php
owner evidence: 1235372 claude 1229638 Rs 00:18 php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The checkout was not a frozen
accepted snapshot, `HEAD` and dirty counts moved during inspection, an
integration-owned markerPDF root run had just failed, and the final exact
process gate was occupied by PID `1235372`.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1095/1235                 3701/0
dolt          613/613                   454/0
esbuild       477/2567                  477/0
gitoxide      1451/2877                 7535/0
libsqlite     211/1589                  211/0
LightningCSS  3000/3548                 3956/0
markerPDF     162/78                    267/0
pandoc        2276/2276                 397/0
quadrable     55/55                     257/0
rclone        976/1601                  976/0
Readability   1984/1984                 3875/0
syncthing     658/658                   9059/0
```

## Findings

1. **Critical - the repository is still a live dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:15`, `progress.md:49-52`,
     `audits/integration-status.md:1-75`, `lanes/*/lane-status.json:12-14`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verified handoff cleanup, and honest
     repo-wide verification before progress is accepted.
   - Evidence: `HEAD` moved from `4af74d410c0d` to `ca5c711190ac` during this
     audit window. Default status rows including untracked files moved
     `21941 -> 21954 -> 21955 -> 21981 -> 21995`, and dirty shortstat moved
     from `242 files changed, 212848 insertions(+), 25658 deletions(-)` to
     `241 files changed, 213049 insertions(+), 25658 deletions(-)`. The newest
     history remains rejection/status dominated, not accepted lane output.

2. **Critical - the current root harness evidence is red or duplicate-unsafe.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md:1-75`,
     `progress.md:51`, `lanes/*/lane-status.json:12-14`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; this audit also had to avoid duplicate
     no-argument root harnesses.
   - Evidence: the latest markerPDF integration intake rejected a coherent
     reduced handoff because integration-owned `php tools/run-tests.php`
     completed with `378` test files, `58733` assertions, and `1` failure while
     `HEAD` moved during verification. This audit then sampled final active
     no-argument root PID `1235372 php tools/run-tests.php`, owned by `claude`.
     No audit-owned root run was started, and no current root result should be
     treated as a serialized frozen-snapshot acceptance result.

3. **Critical - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:1-8`, `porting-summary.json:11-213`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71`, generated
     `2026-05-24 12:29:46 UTC`, while current sampled `HEAD` is
     `ca5c711190ac`. Current lane metadata now differs across the table:
     Difftastic is `1095/1235` and `3701` pass units versus dashboard
     `851/1077` and `3245`; Gitoxide is `1451/2877` and `7535` versus
     dashboard `2877/2877` and `7152`; libsqlite is `211/1589` and `211`
     versus dashboard `349/1589` and `348`; markerPDF is `162/78` and `267`
     versus dashboard `347/396` and `484`; Pandoc is `397` pass units versus
     dashboard `362`; Syncthing is `9059` versus dashboard `7902`.

4. **High - support-library coverage is visible but still not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:1-22`,
     `dependency-backlog.json:25-42`, `dependency-backlog.json:81-95`,
     `dependency-backlog.json:129-176`,
     `dependency-backlog.json:179-230`,
     `dependency-backlog.json:233-269`,
     `dependency-backlog.json:272-337`,
     `dependency-backlog.json:340-388`,
     `dependency-backlog.json:391-426`,
     `dependency-backlog.json:629-646`, `porting.html:71-129`,
     `progress.md:17-36`.
   - Goal requirement at risk: `goal.md:35-40` require real denominators,
     meaningful fixture parity, edge-case coverage, and honest blockers. The
     latest support-library directive requires bounded native components,
     activation gates, dependency-specific upstream/spec denominators, mapped
     fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant,
     and bounded install-attempt notes before missing packages become final
     blockers.
   - Evidence: the backlog covers Pandoc DOC, DOCX/OpenXML, PDF input/output
     handoff, EPUB, ODT/OpenDocument, templates, citations, math, tables,
     package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression, plus rich-function support for the
     other lanes. But all 37 rows remain `candidate`, `deferred`, or one
     `blocked`; there are still 0 active support rows, no accepted support
     manifests, no dependency-specific PHP ledgers, no malformed/corrupt
     evidence records, no accepted activation records, and no bounded
     install-attempt notes. Current lane-local rich slices must not receive
     support-library progress credit.

5. **High - Pandoc remains far short of the original rich conversion-kernel goal despite 99% status language.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:410-413`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1427-1433`,
     `lanes/pandoc/lane-status.json:5-14`, `dependency-backlog.json:81-95`,
     `dependency-backlog.json:129-176`,
     `dependency-backlog.json:179-230`,
     `dependency-backlog.json:233-269`,
     `dependency-backlog.json:272-337`,
     `dependency-backlog.json:340-388`,
     `dependency-backlog.json:391-426`,
     `dependency-backlog.json:629-646`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: Pandoc records `2276/2276` over a static inventory and `397`
     focused PHP behavior tests, but full upstream Haskell runner parity is
     unexecuted. The latest slice explicitly excludes upstream Pandoc
     invocation, network fetches, browser tooling, converter shell-outs, PDF
     processing, ZIP/package parsers, citation/CSL engines, PlainMath/MathML
     full conversion, broader XML/HTML DOM support, Unicode/charset support,
     TeX math/ref conversion beyond embedded annotations, and broader syntax
     highlighting. Those are central to the original document-conversion goal,
     not optional polish.

6. **High - markerPDF still overstates denominator progress and mixes native extraction with runtime/application planning.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-20`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:217`,
     `lanes/markerpdf/lane-status.json:5-14`,
     `audits/integration-status.md:1-75`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and
     `goal.md:35-40` say wrappers, shell-outs, whole applications, runtime
     launchers, and plan-only behavior must not count as native implementation
     progress.
   - Evidence: the current manifest reports `mapped: 162` against a
     `total: 78` repository-path denominator, so its numerator exceeds its
     stated denominator. The manifest also inventories Streamlit/FastAPI
     launcher paths, batch/chunk conversion scripts, model setup, OCR/Texify,
     Surya, pypdfium/PDF rendering, and tabled helper behavior. The latest
     `PdfTextExtractor` slice is narrow and useful, but the lane cannot count
     application launchers, external PDF/model runtimes, or inspected
     third-party helper plans as native markerPDF port progress without
     bounded support manifests and native pass/fail ledgers.

7. **High - Gitoxide's current reduced handoff no longer supports the dashboard's full-mapped claim.**
   - Paths: `goal.md:7`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12-22`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:91-105`,
     `lanes/gitoxide/lane-status.json:5-14`, `porting.html:59`,
     `porting-summary.json:61-76`.
   - Goal requirement at risk: `goal.md:7`, `goal.md:25`, and
     `goal.md:35-40` require a Git implementation with packfiles, refs,
     commits, object database, protocol v2, sparse/partial clone, push, merge,
     server-oriented primitives, and a real upstream denominator.
   - Evidence: current Gitoxide manifest says `1451/2877` mapped for a reduced
     `gix-discover` submodule slice and explicitly excludes credential,
     protocol, fetch, push, pack, index, config, attributes, URL/refspec,
     SHA-256, SSH/daemon, and unrelated examples/tests from this batch. The
     dashboard still claims `2877/2877` mapped and `98%`, which hides the
     reduced scope and remaining original-priority surface.

8. **High - recent integration history confirms workers are still handing off accumulated multi-slice patches.**
   - Paths: `audits/integration-status.md:1-220`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35-40`, and
     `goal.md:48` require focused slices, meaningful fixture parity, explicit
     blockers, verification, and cleanup before assigning the next task.
   - Evidence: the newest markerPDF rejection found a focused, coherent
     reduced handoff, but rejected it because root failed and `HEAD` moved
     during verification. The preceding Quadrable rejection found a narrow
     proof-marker claim inside a much broader dirty lane state. The libsqlite
     rejection found `13` tracked files plus `164` untracked files from older
     storage/WAL/JSON/B-tree work. The rclone, Gitoxide, and earlier markerPDF
     rejections show the same accumulated-scope pattern.

9. **Medium - manifest/status ledgers still use inconsistent count units.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json:12-20`,
     `lanes/*/lane-status.json:5-13`, `porting.html:56-67`,
     `porting-summary.json:11-213`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by upstream denominator,
     mapped tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: markerPDF uses repository paths as denominator while mapped
     behaviors exceed that denominator. Difftastic reports `1095` mapped
     artifacts and `3701` assertion-like PHP pass units. Dolt reports `454`
     PASS cases, while its manifest denominator is executable upstream files.
     Pandoc reports `2276/2276` mapped static artifacts with only `397`
     focused PHP behavior tests. These are useful facts, but they are not one
     normalized suite-progress unit.

10. **Medium - high percentage estimates hide acceptance blockers across all lanes.**
    - Paths: `porting.html:56-67`, `lanes/*/lane-status.json:4-14`,
      `progress.md:49-52`.
    - Goal requirement at risk: `goal.md:3`, `goal.md:44-45`, and
      `goal.md:52` require visible honest progress and current blockers in the
      dashboard.
    - Evidence: nearly every lane reports `95-99%` progress while
      `latestCommit` is `pending`, `uncommitted`, `not committed`, or recently
      rejected; full upstream runner parity is absent or bounded; and root
      aggregate verification is red, active, or pending. These percentages are
      not meaningful acceptance percentages until accepted native PHP behavior
      is separated from moving dirty work and static-inventory coverage.

## Required Next Intervention

Freeze writers/runners/status publishers long enough for two stable polls of
`HEAD`, dirty status rows, shortstat, active root PIDs, and relevant handoff/log
mtimes. Do not add more lane breadth while PID `1235372` or any successor
no-argument root harness is active. Triage the latest failed integration-owned
root result, then accept or reject one owner-free reduced lane batch whose
dirty files match its evidence exactly. Normalize manifest/status units for
that lane in the same atomic change, regenerate `porting.html` and
`porting-summary.json` from the accepted commit, then run exactly one
serialized no-argument `php tools/run-tests.php` only if
`pgrep -af '^php tools/run-tests\.php$'` stays empty on that frozen snapshot.
Do not activate a support-library row until the base lane is accepted-ready or
accepted-blocked on that exact bounded component with its own denominator,
mapped fixtures, malformed/corrupt cases, PHP pass/fail ledger, and
install-attempt notes where missing packages matter.
