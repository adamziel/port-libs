<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfCallableSemanticRecordProcessor;
use PortLibs\Pandoc\PdfSemanticRecordPipeline;

return [
    'pdf semantic pipeline runs named stages in order and audits each boundary' => static function (TestRunner $t): void {
        $pipeline = new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('append', static function (array $records): array {
                $records[0]['text'] .= ' beta';

                return $records;
            }),
            new PdfCallableSemanticRecordProcessor('classify', static function (array $records): array {
                $records[0]['layout']['role'] = 'body';

                return $records;
            }),
        ]);

        $result = $pipeline->run([['text' => 'alpha', 'layout' => ['page' => 1]]]);

        $t->same('alpha beta', $result['records'][0]['text']);
        $t->same('body', $result['records'][0]['layout']['role']);
        $t->same(['append', 'classify'], array_column($result['trace'], 'processor'));
        $t->same(true, $result['trace'][0]['changed']);
        $t->same(true, $result['trace'][1]['changed'], 'Layout-only classifications must be visible in the audit digest.');
        $t->same(5, $result['trace'][0]['textByteDelta']);
    },

    'pdf semantic pipeline reports a deterministic no-op stage' => static function (TestRunner $t): void {
        $pipeline = new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('noop', static fn (array $records): array => $records),
        ]);
        $result = $pipeline->run([['text' => 'alpha', 'layout' => null]]);

        $t->same(false, $result['trace'][0]['changed']);
        $t->same($result['trace'][0]['inputDigest'], $result['trace'][0]['outputDigest']);
        $t->same(0, $result['trace'][0]['recordDelta']);
    },

    'pdf semantic pipeline rejects duplicate processor names' => static function (TestRunner $t): void {
        $t->throws(RuntimeException::class, static fn (): PdfSemanticRecordPipeline => new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('same', static fn (array $records): array => $records),
            new PdfCallableSemanticRecordProcessor('same', static fn (array $records): array => $records),
        ]));
    },

    'pdf semantic pipeline rejects malformed processor output' => static function (TestRunner $t): void {
        $pipeline = new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('invalid', static fn (array $records): array => [['text' => 42]]),
        ]);

        $t->throws(RuntimeException::class, static fn (): array => $pipeline->run([
            ['text' => 'alpha', 'layout' => null],
        ]));
    },
];
