<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$settingsJson = '{"plugin":{"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7},{"name":"forms","priority":4}],"flags":{"network":true,"beta":false}}}';
$settingsJsonSql = str_replace("'", "''", $settingsJson);
$jsonbHex = bin2hex(SQLiteJsonB::encode([
    'plugin' => [
        'rules' => [
            ['name' => 'seo', 'priority' => 2],
            ['name' => 'cache', 'priority' => 7],
            ['name' => 'forms', 'priority' => 4],
        ],
        'flags' => ['network' => true, 'beta' => false],
    ],
]));

$cases = [
    'executes commuted json hidden constraint for json_each bare table' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT key, type FROM json_each WHERE '{$settingsJsonSql}' = json ORDER BY key", []);
        $t->same(['plugin'], array_column($rows, 'key'));
        $t->same(['object'], array_column($rows, 'type'));
    },
    'executes commuted json hidden constraint for json_tree bare table' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom FROM json_tree WHERE '{$settingsJsonSql}' = json AND key = 'priority' ORDER BY atom", []);
        $t->same([2, 4, 7], array_column($rows, 'atom'));
    },
    'executes commuted root hidden constraint for json_tree bare table' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom FROM json_tree WHERE json = '{$settingsJsonSql}' AND '$.plugin.rules' = root AND type = 'integer' ORDER BY atom DESC", []);
        $t->same(['priority', 'priority', 'priority'], array_column($rows, 'key'));
        $t->same([7, 4, 2], array_column($rows, 'atom'));
    },
    'executes both hidden constraints in commuted order' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT key, type FROM json_each WHERE '$.plugin.flags' = root AND '{$settingsJsonSql}' = json ORDER BY key", []);
        $t->same(['beta', 'network'], array_column($rows, 'key'));
        $t->same(['false', 'true'], array_column($rows, 'type'));
    },
    'executes qualified commuted json hidden constraint through alias' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom FROM json_tree AS j WHERE '{$settingsJsonSql}' = j.json AND j.root = '$.plugin.rules' AND type = 'text' ORDER BY atom", []);
        $t->same(['cache', 'forms', 'seo'], array_column($rows, 'atom'));
    },
    'executes qualified commuted root hidden constraint through alias' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom, fullkey FROM json_tree j WHERE j.json = '{$settingsJsonSql}' AND '$.plugin.rules' = j.root AND key = 'name' ORDER BY fullkey DESC", []);
        $t->same(['forms', 'cache', 'seo'], array_column($rows, 'atom'));
    },
    'preserves residual predicates after removing commuted hidden constraints' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $plan = SQLiteSelectSql::plan("SELECT key, atom FROM json_tree WHERE '{$settingsJsonSql}' = json AND '$.plugin.rules' = root AND atom >= 4 ORDER BY atom", []);
        $t->same(['from', 'select', 'where', 'orderBy'], array_keys($plan));
        $t->same('>=', $plan['where']['operator']);
        $t->same('atom', $plan['where']['left']['name']);
        $t->same(4, $plan['where']['right']['value']);
    },
    'removes all commuted hidden constraints when they are the whole predicate' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $plan = SQLiteSelectSql::plan("SELECT key FROM json_each WHERE '{$settingsJsonSql}' = json AND '$.plugin.flags' = root", []);
        $t->same(['from', 'select'], array_keys($plan));
        $t->same(['network', 'beta'], array_column($plan['from'], 'key'));
    },
    'executes commuted hidden constraints with literal jsonb blob' => static function (TestRunner $t) use ($jsonbHex): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom FROM json_tree WHERE X'{$jsonbHex}' = json AND '$.plugin.rules' = root AND key = 'priority' ORDER BY atom DESC", []);
        $t->same([7, 4, 2], array_column($rows, 'atom'));
    },
    'commuted malformed jsonb hidden constraint returns empty rowset' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute("SELECT key FROM json_each WHERE X'1c00' = json AND '$.plugin' = root", []);
        $t->same([], $rows);
    },
    'commuted sql null hidden json constraint returns empty rowset' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute("SELECT key FROM json_tree WHERE NULL = json AND '$.plugin' = root", []);
        $t->same([], $rows);
    },
    'commuted root constraint can be combined with not in visible residual' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom FROM json_each WHERE json = '{$settingsJsonSql}' AND '$.plugin.flags' = root AND key NOT IN ('beta') ORDER BY key", []);
        $t->same(['network'], array_column($rows, 'key'));
        $t->same([1], array_column($rows, 'atom'));
    },
    'commuted root constraint can be combined with between visible residual' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom FROM json_tree WHERE '{$settingsJsonSql}' = json AND '$.plugin.rules' = root AND atom BETWEEN 3 AND 8 ORDER BY atom", []);
        $t->same([4, 7], array_column($rows, 'atom'));
    },
    'commuted root constraint can be combined with like visible residual' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT key, fullkey FROM json_tree WHERE '{$settingsJsonSql}' = json AND '$.plugin.rules' = root AND fullkey LIKE '$.plugin.rules[1]%' ORDER BY fullkey", []);
        $t->same(['$.plugin.rules[1]', '$.plugin.rules[1].name', '$.plugin.rules[1].priority'], array_column($rows, 'fullkey'));
    },
    'commuted root constraint can be combined with glob visible residual' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT key, fullkey FROM json_tree WHERE '{$settingsJsonSql}' = json AND '$.plugin.rules' = root AND fullkey GLOB '*.priority' ORDER BY fullkey", []);
        $t->same(['$.plugin.rules[0].priority', '$.plugin.rules[1].priority', '$.plugin.rules[2].priority'], array_column($rows, 'fullkey'));
    },
    'commuted hidden json constraint supports escaped string literals' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom FROM json_each WHERE '{\"quote\":\"canary''s\"}' = json ORDER BY key", []);
        $t->same(['quote'], array_column($rows, 'key'));
        $t->same(["canary's"], array_column($rows, 'atom'));
    },
    'commuted hidden json constraint supports json5 text' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom FROM json_each WHERE '{plugin:{modes:[''sync'',''cache'',],enabled:false}}' = json AND '$.plugin.modes' = root ORDER BY key", []);
        $t->same([0, 1], array_column($rows, 'key'));
        $t->same(['sync', 'cache'], array_column($rows, 'atom'));
    },
    'commuted hidden constraints preserve limit and offset execution' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom FROM json_tree WHERE '{$settingsJsonSql}' = json AND '$.plugin.rules' = root AND key = 'priority' ORDER BY atom LIMIT 1 OFFSET 1", []);
        $t->same([4], array_column($rows, 'atom'));
    },
    'commuted hidden constraints preserve comma limit execution' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom FROM json_tree WHERE '{$settingsJsonSql}' = json AND '$.plugin.rules' = root AND key = 'priority' ORDER BY atom LIMIT 1, 2", []);
        $t->same([4, 7], array_column($rows, 'atom'));
    },
    'commuted hidden constraints feed grouped json table execution' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("SELECT type, count(*) AS rows, sum(atom) AS total FROM json_tree WHERE '{$settingsJsonSql}' = json AND '$.plugin.rules' = root GROUP BY type HAVING count(*) >= 3 ORDER BY rows DESC, type", []);
        $t->same(['integer', 'object', 'text'], array_column($rows, 'type'));
        $t->same([3, 3, 3], array_column($rows, 'rows'));
        $t->same([13, null, 0], array_column($rows, 'total'));
    },
    'commuted hidden constraints feed cte materialization' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $rows = SQLiteSelectSql::execute("WITH priorities AS (SELECT atom AS priority FROM json_tree WHERE '{$settingsJsonSql}' = json AND '$.plugin.rules' = root AND key = 'priority') SELECT priority FROM priorities WHERE priority >= 4 ORDER BY priority DESC", []);
        $t->same([7, 4], array_column($rows, 'priority'));
    },
    'left side non-hidden column equality remains a residual predicate' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $plan = SQLiteSelectSql::plan("SELECT key FROM json_tree WHERE '{$settingsJsonSql}' = json AND 'priority' = key ORDER BY id", []);
        $t->same(['from', 'select', 'where', 'orderBy'], array_keys($plan));
        $t->same('=', $plan['where']['operator']);
        $t->same('priority', $plan['where']['left']['value']);
        $t->same('key', $plan['where']['right']['name']);
    },
    'right side non-literal json comparison remains a residual predicate' => static function (TestRunner $t): void {
        $plan = SQLiteSelectSql::plan("SELECT key FROM json_each WHERE json = root", []);
        $t->same(['from', 'select', 'where'], array_keys($plan));
        $t->same('=', $plan['where']['operator']);
        $t->same('json', $plan['where']['left']['name']);
        $t->same('root', $plan['where']['right']['name']);
    },
    'commuted malformed text hidden json constraint still raises malformed text' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT key FROM json_each WHERE '{bad' = json", []));
    },
    'commuted non-text root hidden constraint still raises root type error' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT key FROM json_each WHERE '{$settingsJsonSql}' = json AND 1 = root", []));
    },
    'commuted malformed root hidden constraint still raises path error' => static function (TestRunner $t) use ($settingsJsonSql): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT key FROM json_each WHERE '{$settingsJsonSql}' = json AND '$.bad[' = root", []));
    },
    'commuted hidden json constraint works with bind parameters' => static function (TestRunner $t) use ($settingsJson): void {
        $rows = SQLiteSelectSql::execute("SELECT key FROM json_each WHERE :settings = json AND '$.plugin.flags' = root ORDER BY key", [], ['settings' => $settingsJson]);
        $t->same(['beta', 'network'], array_column($rows, 'key'));
    },
    'commuted hidden root constraint works with bind parameters' => static function (TestRunner $t) use ($settingsJson): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom FROM json_tree WHERE :settings = json AND :root = root AND key = 'priority' ORDER BY atom", [], ['settings' => $settingsJson, 'root' => '$.plugin.rules']);
        $t->same([2, 4, 7], array_column($rows, 'atom'));
    },
    'commuted jsonb bind parameter hidden constraint works' => static function (TestRunner $t) use ($jsonbHex): void {
        $rows = SQLiteSelectSql::execute("SELECT key, atom FROM json_tree WHERE ? = json AND '$.plugin.rules' = root AND key = 'name' ORDER BY atom", [], [new SQLiteBlobValue(hex2bin($jsonbHex))]);
        $t->same(['cache', 'forms', 'seo'], array_column($rows, 'atom'));
    },
    'commuted sql null bind parameter hidden constraint returns empty rowset' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute("SELECT key FROM json_tree WHERE ? = json AND '$.plugin.rules' = root", [], [null]);
        $t->same([], $rows);
    },
];

return $cases;
