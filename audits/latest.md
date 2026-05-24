# Independent Audit - 2026-05-24T18:08Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, all 12
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12 sampled
`lanes/*/lane-status.json`, `audits/integration-status.md`, and recent Git
history through `53f6bfd9 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files. A later integration-status commit landed while this audit diff was being
validated, so the final history reference is `92805f12 Record readability
handoff rejection`.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 17:57-18:08
current HEAD at audit write: 92805f12b0c2
observed HEAD movement during audit: 726dad0861dd -> 53f6bfd934e6 -> 92805f12b0c2
recent history: 92805f12 Record readability handoff rejection; 53f6bfd9 Record integration hold status; 726dad08 Refresh independent audit status
branch sample: main...origin/main [ahead 967, behind 68]
default status rows including untracked: 20188 -> 20327
git diff --shortstat HEAD samples: 329 files changed, 266462 insertions(+), 31514 deletions(-) -> 331 files changed, 266998 insertions(+), 31648 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required exact pre-root process gate:

```text
first sample: pgrep -af '^php tools/run-tests\.php$' matched `619832 php tools/run-tests.php`
owner evidence: `619832 claude 00:30 php tools/run-tests.php`
later sample: pgrep matched `623820 php tools/run-tests.php`
owner evidence: `623820 claude 623748 R 01:41 php tools/run-tests.php`
pre-commit sample: pgrep matched `636948 php tools/run-tests.php`
owner evidence: `636948 claude 636890 R 02:01 php tools/run-tests.php`
final validation sample: pgrep returned no rows
pre-commit recheck: pgrep matched `652045 php tools/run-tests.php`
owner evidence: `652045 claude 651991 R 00:34 php tools/run-tests.php`
```

I did not start `php tools/run-tests.php`. The exact no-argument root harness
gate was occupied during audit sampling, and the checkout still failed the
stability gate when one validation gate briefly cleared; the final pre-commit
recheck was occupied again.

Latest sampled manifest/status counts versus the published dashboard:

```text
lane          latest sampled manifest/status                                  dashboard
difftastic    manifest 1044/1213; status 3594 assertions                      3245 pass, 851/1077
dolt          manifest 613/613; status 445 PASS cases                         425 pass, 613/613
esbuild       manifest 464/2567; status 464 tests                             429 pass, 429/2567
gitoxide      manifest 2877/2877; status 7536 assertions                      7152 pass, 2877/2877
libsqlite     manifest 373/1589; status 373 cases / 5957 assertions           348 pass, 349/1589
LightningCSS  manifest 2940/3548; status 4241 assertions                      4065 pass, 2765/3548
markerPDF     manifest 375/424; status 513 behavior tests                     484 pass, 347/396
pandoc        manifest 2233/2276; status 2216 checks / 389 behavior tests     362 pass, 1891/2276
quadrable     manifest 55/55; status 250 behavior tests                       232 pass, 55/55
rclone        manifest 970/1601; status 969 tests / 10086 assertions          906 pass, 906/1601
readability   manifest 1984/1984; status 284 behavior tests / 3763 assertions 3545 pass, 1984/1984
syncthing     manifest 658/658; status 8741 assertions                        7902 pass, 658/658
```

## Findings

1. **Critical - the repository is still a live dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:15`, `progress.md:51`,
     `audits/integration-status.md:15-33`,
     `audits/integration-status.md:37-49`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: `HEAD` moved during this audit from `726dad0861dd` through
     `53f6bfd934e6` to `92805f12b0c2`, untracked-inclusive status rows moved
     `20188 -> 20327`, and tracked shortstat moved from
     `329 files changed, 266462 insertions(+), 31514 deletions(-)` to
     `331 files changed, 266998 insertions(+), 31648 deletions(-)`.
     The latest integration records a Readability handoff rejection, no
     accepted lane/status claim, no dashboard regeneration, and no support-row
     activation; it also says the shared tree moved during the hold and active
     root runners blocked a serialized accepted-root snapshot.

2. **Critical - no trustworthy no-argument root acceptance result exists for the dirty tree.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md:30-33`,
     `audits/integration-status.md:100-109`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the current user instruction requires the
     exact duplicate-root guard before any no-argument root run.
   - Evidence: this audit's exact gate first matched active root PID `619832`
     owned by `claude`, then active root PID `623820` owned by `claude`,
     PPID `623748`, state `R`, elapsed `01:41`, command
     `php tools/run-tests.php`; a later owner sample showed the same PID at
     elapsed `02:01`, one validation gate returned no rows, and the final
     pre-commit recheck matched active root PID `652045`, owned by `claude`,
     PPID `651991`, state `R`, elapsed `00:34`. I did not start a duplicate
     or a later root run because the checkout was still not a frozen accepted
     snapshot. The latest integration status also treats a prior dirty root
     pass as non-acceptance evidence because no lane batch had been accepted
     from a frozen tree.

3. **Critical - `porting.html` and `porting-summary.json` are stale against every active lane.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`, `porting-summary.json:16-205`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71`, generated
     `2026-05-24 12:29:46 UTC`, while current `HEAD` is `92805f12b0c2`.
     Current manifests/statuses have Difftastic `1044/1213` and `3594`
     assertions while the dashboard says `851/1077` and `3245`; esbuild
     `464/2567` while the dashboard says `429/2567`; libsqlite `373/1589`
     and `5957` assertions while the dashboard says `349/1589` and `348`;
     LightningCSS `2940/3548` and `4241` assertions while the dashboard says
     `2765/3548` and `4065`; markerPDF `375/424` and `513` tests while the
     dashboard says `347/396` and `484`; Pandoc `2233/2276` / status `2216`
     checks while the dashboard says `1891/2276`; rclone `970/1601` while
     the dashboard says `906/1601`; Syncthing `8741` assertions while the
     dashboard says `7902`.

4. **High - manifest/status ledgers still disagree internally, so generated coordination input is not reliable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/lane-status.json:5`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2593`,
     `lanes/dolt/lane-status.json:6`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:377`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1375`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Difftastic's manifest reports `1044` mapped while status prose
     still says `1036` focused mappings. Dolt status reports `445` PASS cases
     while manifest `nativeImplementation.phpBehaviorTests` remains `442`.
     Pandoc manifest reports `2233` mapped and a latest style raw-block slice,
     while lane status reports `2216` mapped checks and a doc-noteref/endnotes
     current slice. rclone manifest reports `970` mapped while status says
     `969` behavior tests. These are dashboard-source fields, not harmless
     prose.

5. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:3-4`,
     `dependency-backlog.json:81-94`, `dependency-backlog.json:129-176`,
     `dependency-backlog.json:256-285`, `dependency-backlog.json:413-425`,
     `dependency-backlog.json:629-645`, `porting.html:72-79`,
     `audits/integration-status.md:58-68`.
   - Goal requirement at risk: the latest support-library directives require
     bounded native PHP components with activation gates,
     dependency-specific upstream/spec denominators, mapped fixtures, PHP
     pass/fail ledgers, malformed/corrupt cases where relevant, bounded
     install attempts or ruled-out notes where tooling is missing, and as much
     upstream/spec-suite evidence as can honestly run.
   - Evidence: the backlog has visible rows for Pandoc DOC, DOCX/OpenXML,
     PDF input/output handoff, EPUB, ODT/OpenDocument, templates, citations,
     math, tables, package containers, XML/HTML, Unicode/charset,
     JSON/YAML metadata, syntax highlighting, and archive/compression, but it
     still has `0` active support rows. `git ls-files '*UPSTREAM_TEST_MANIFEST.json'`
     lists only the 12 base lane manifests, not support-library manifests.
     There are no dependency-specific PHP ledgers, malformed/corrupt evidence
     records, accepted activation records, or bounded install-attempt notes.

6. **High - Pandoc remains far short of the original rich conversion-kernel goal despite near-complete status language.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:11-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:377`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1375-1378`,
     `dependency-backlog.json:81-94`, `dependency-backlog.json:129-176`,
     `dependency-backlog.json:256-285`, `dependency-backlog.json:413-425`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel
     with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: Pandoc reports `99%`, `2233/2276` manifest mapping, and green
     focused PHP, but full upstream Haskell runner parity remains unexecuted.
     The current slice explicitly does not invoke upstream Pandoc, browser
     tooling, converter shell-outs, PDF processing, ZIP/package parsers,
     citation/CSL engines, XML/HTML support-library expansion, PlainMath/MathML
     conversion, or broader syntax highlighting. DOC/DOCX/OpenXML, PDF
     input/output handoff, EPUB, ODT/OpenDocument, citations, math, templates,
     tables, JSON/YAML metadata, archive/compression, XML/HTML, Unicode, and
     charset remain inactive support rows. WXR still is not visible as an
     accepted Pandoc reader/writer denominator.

7. **High - markerPDF still mixes native PDF progress with plan-only runtime/application evidence.**
   - Paths: `goal.md:9`, `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12-14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:909-912`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1178-1205`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and the support
     library directives say whole applications, converter wrappers, model
     stacks, and hidden shell-outs are non-progress unless they are explicit
     temporary oracle tooling.
   - Evidence: the native escaped PDF filter-name slice is useful and
     explicitly avoids Python/pdftext/pypdfium/Poppler/Ghostscript, but the
     denominator still includes benchmark archive planning, Streamlit app
     flow, FastAPI/Uvicorn server shape, Poetry/package metadata, OCR install
     planning, multiprocessing/chunk shell lifecycle, model-runtime dependency
     graphs, and other plan-only runtime evidence. Richer searchable PDF,
     OCR/layout, and table work should be credited only through accepted
     bounded rows such as `pdf-text-dictionary-core`,
     `layout-ocr-result-core`, and `table-geometry-core`.

8. **Medium - the 98-99 percent progress claims remain misleading.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35-40` requires meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers for hard features.
   - Evidence: the published average is `98.3%` and most lanes report
     `98-99%`, but the dashboard is stale, current lane changes are
     unaccepted, the dirty tree has no serialized root acceptance result, the
     manifest/status ledgers disagree, several upstream runners remain
     static-only or bounded, and no support-library row is active.

## Next Intervention

Freeze or wait out active lane workers, root/focused runners, status
publishers, dashboard publishers, evaluator/auditor loops, capacity jobs, and
integrator loops. Then accept or reject one owner-free lane batch from a stable
two-poll snapshot. The current best intervention is still an integration
freeze plus one small intake: first normalize the Difftastic/Dolt/Pandoc/rclone
manifest-status count drift, then pick a single owner-free lane batch with
stopped logs, run focused verification, run exactly one serialized
no-argument `php tools/run-tests.php` only after
`pgrep -af '^php tools/run-tests\.php$'` is clear and the checkout is frozen,
run `git diff --check`, regenerate `porting.html` and `porting-summary.json`
from the accepted commit, and keep support-library rows inactive until a
base-lane slice is accepted or accepted-blocked on one bounded component.
