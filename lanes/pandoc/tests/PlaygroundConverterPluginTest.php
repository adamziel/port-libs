<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('add_filter')) {
    function add_filter(string $hookName, callable $callback): void
    {
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hookName, callable $callback): void
    {
    }
}

require_once dirname(__DIR__, 3) . '/tools/playground-converter-plugin/port-libs-playground-converter.php';

return [
    'playground pdf importer keeps geometry table reconstruction enabled with prose repair' => static function (TestRunner $t): void {
        $options = plpc_converter_options('pdf');

        $t->same(80000, $options['readerOptions']['maxTextBytes'] ?? null);
        $t->same(true, $options['readerOptions']['pdfGeometryTables'] ?? null);
        $t->same(true, $options['readerOptions']['pdfRepairProseText'] ?? null);
    },
    'playground csv importer still permits blank records' => static function (TestRunner $t): void {
        $options = plpc_converter_options('csv');

        $t->same(true, $options['readerOptions']['allowBlankRecords'] ?? null);
    },
];
