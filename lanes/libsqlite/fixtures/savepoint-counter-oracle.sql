CREATE TABLE t(id INTEGER PRIMARY KEY, v TEXT);
INSERT INTO t(v) VALUES('a'),('b'),('c');
UPDATE t SET v = v WHERE 0;
SELECT 'before savepoint', last_insert_rowid(), changes(), total_changes();
SAVEPOINT sp;
UPDATE t SET v = 'x' WHERE id IN (1, 2);
SELECT 'after update', last_insert_rowid(), changes(), total_changes();
ROLLBACK TO sp;
SELECT 'after rollback to', last_insert_rowid(), changes(), total_changes();
