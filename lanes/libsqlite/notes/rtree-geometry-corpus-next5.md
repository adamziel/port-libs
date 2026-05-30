# RTREE Geometry Corpus Next5

- Scope: added a bounded native PHP `SQLiteRTreeGeometry` helper and a focused upstream-style corpus for RTREE geometry callback semantics: bounding-box overlap, containment, boundary-inclusive edges/corners, rectangle metrics, point distance, circle overlap/containment, row filtering, and malformed geometry guards.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRTreeGeometryCorpusTest.php` reports 1 selected file, 56 PASS lines, 79 assertions, 0 failures.
- PASS delta: +56 verified PASS lines. `lane-status.json` `phpPass` moves from 1684 to 1740. `benchmarkDenominator.mapped` is unchanged because this slice adds PHP corpus coverage rather than a newly hydrated upstream Tcl inventory unit.
- Application smoke: `php lanes/libsqlite/examples/application-rtree-geometry-corpus.php` previews copied Application event/location rows filtered by RTREE-style rectangles without ext/sqlite.
- Non-overlap: avoids accepted JSON table, WAL/VFS, B-tree, SELECT SQL, Unicode GLOB, rollback, and batch4 corpus clusters; this is a separate RTREE geometry predicate corpus.
- Dependency closure: no new support component is needed; the slice uses bounded native PHP geometry arithmetic only.
