<?php

declare(strict_types=1);

use PortLibs\Pandoc\HtmlReader;
use PortLibs\Pandoc\PandocConverter;

return [
    'keeps WP HTML Processor imports byte-identical to source-token imports' => static function (TestRunner $t): void {
        if (!class_exists('WP_HTML_Processor') || !method_exists('WP_HTML_Processor', 'create_full_parser')) {
            $t->same(true, true, 'WP HTML Processor is optional outside WordPress');

            return;
        }

        $fixtures = glob(__DIR__ . '/../fixtures/upstream-html-*.html') ?: [];
        sort($fixtures);
        $t->true($fixtures !== [], 'Expected the HTML fixture corpus to be available');

        $render = static function ($document, string $format): array {
            try {
                return ['ok', PandocConverter::write($document, $format)];
            } catch (Throwable $exception) {
                return ['error', $exception::class . ':' . $exception->getMessage()];
            }
        };

        foreach ($fixtures as $fixture) {
            $html = file_get_contents($fixture);
            if (!is_string($html)) {
                throw new RuntimeException("Unable to read HTML fixture {$fixture}");
            }

            $sourceTokenDocument = (new HtmlReader(['htmlTokenizerBackend' => 'tagsoup']))->read($html);
            $processorDocument = (new HtmlReader())->read($html);
            $sourceAst = serialize($sourceTokenDocument);
            $processorAst = serialize($processorDocument);
            if ($sourceAst !== $processorAst) {
                throw new RuntimeException('WP HTML Processor changed the AST for ' . basename($fixture));
            }
            $t->same(
                hash('sha256', $sourceAst),
                hash('sha256', $processorAst),
                'WP HTML Processor must preserve the AST for ' . basename($fixture)
            );

            foreach (['wordpress', 'html', 'markdown', 'native', 'json', 'plain'] as $format) {
                $sourceOutput = $render($sourceTokenDocument, $format);
                $processorOutput = $render($processorDocument, $format);
                if ($sourceOutput !== $processorOutput) {
                    throw new RuntimeException("WP HTML Processor changed {$format} output for " . basename($fixture));
                }
                $t->same(
                    hash('sha256', serialize($sourceOutput)),
                    hash('sha256', serialize($processorOutput)),
                    "WP HTML Processor must preserve {$format} output for " . basename($fixture)
                );
            }
        }
    },
];
