<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\SingleDocumentConverter;

return [
    'preserves convert.py equals-token argparse errors while allowing equals abbreviations' => static function (
        TestRunner $t
    ): void {
        $batch = new BatchConverter();

        $unknownEquals = $batch->runtimeMainArgumentPreflightPlan([
            '/wp/uploads',
            '/wp/marker-output',
            '--gpu=1',
        ]);
        $ambiguousEquals = $batch->runtimeMainArgumentPreflightPlan([
            '/wp/uploads',
            '/wp/marker-output',
            '--m=3',
        ]);
        $metadataAbbrev = $batch->runtimeMainArgumentPreflightPlan([
            '/wp/uploads',
            '/wp/marker-output',
            '--metadata=wordpress-metadata.json',
            '--work=3',
            '--n=2',
        ]);

        $t->same(false, $unknownEquals['parse_args']['parse_args_success']);
        $t->same('--gpu=1', $unknownEquals['parse_args']['error_argument']);
        $t->same('unrecognized arguments: --gpu=1', $unknownEquals['parse_args']['error_message']);
        $t->same(false, $unknownEquals['parse_args']['filesystem_touched_before_error']);
        $t->same('parse_args', $unknownEquals['blocked_by']);
        $t->same(false, $unknownEquals['executes_python_or_models']);

        $t->same(false, $ambiguousEquals['parse_args']['parse_args_success']);
        $t->same('--m=3', $ambiguousEquals['parse_args']['error_argument']);
        $t->contains(
            'ambiguous option: --m=3 could match --max, --metadata_file, --min_length',
            $ambiguousEquals['parse_args']['error_message']
        );
        $t->same(false, $ambiguousEquals['executes_multiprocessing']);
        $t->same(false, $ambiguousEquals['executes_external_pdf_tools']);

        $t->same(true, $metadataAbbrev['parse_args']['parse_args_success']);
        $t->same('wordpress-metadata.json', $metadataAbbrev['arguments']['options']['metadata_file']);
        $t->same(3, $metadataAbbrev['arguments']['options']['workers']);
        $t->same(2, $metadataAbbrev['arguments']['options']['num_chunks']);
        $t->same(false, $metadataAbbrev['arguments']['defaults_applied']['metadata_file']);
        $t->same(false, $metadataAbbrev['arguments']['defaults_applied']['workers']);
        $t->same(false, $metadataAbbrev['arguments']['defaults_applied']['num_chunks']);
        $t->same(true, $metadataAbbrev['semantic_boundaries']['metadata_file_truthy_for_json_load']);
        $t->same(false, $metadataAbbrev['executes_python_or_models']);
    },
    'preserves convert_single.py equals-token argparse errors while allowing equals abbreviations' => static function (
        TestRunner $t
    ): void {
        $single = new SingleDocumentConverter();

        $unknownEquals = $single->runtimeArgumentPreflightPlan([
            '/wp/uploads/editorial.pdf',
            '/wp/marker-output',
            '--gpu=1',
        ]);
        $maxPagesAbbrev = $single->runtimeArgumentPreflightPlan([
            '/wp/uploads/editorial.pdf',
            '/wp/marker-output',
            '--max=3',
            '--s=2',
            '--b=4',
            '--langs=English,French',
        ]);

        $t->same(false, $unknownEquals['parse_args']['parse_args_success']);
        $t->same('--gpu=1', $unknownEquals['parse_args']['error_argument']);
        $t->same('unrecognized arguments: --gpu=1', $unknownEquals['parse_args']['error_message']);
        $t->same(false, $unknownEquals['parse_args']['filesystem_touched_before_error']);
        $t->same('parse_args', $unknownEquals['blocked_by']);
        $t->same(false, $unknownEquals['executes_python_or_models']);
        $t->same(false, $unknownEquals['executes_external_pdf_tools']);

        $t->same(true, $maxPagesAbbrev['parse_args']['parse_args_success']);
        $t->same(3, $maxPagesAbbrev['arguments']['options']['max_pages']);
        $t->same(2, $maxPagesAbbrev['arguments']['options']['start_page']);
        $t->same(4, $maxPagesAbbrev['arguments']['options']['batch_multiplier']);
        $t->same('English,French', $maxPagesAbbrev['arguments']['options']['langs']);
        $t->same(['English', 'French'], $maxPagesAbbrev['language_parse']['parsed_langs']);
        $t->same(false, $maxPagesAbbrev['arguments']['defaults_applied']['max_pages']);
        $t->same(false, $maxPagesAbbrev['arguments']['defaults_applied']['start_page']);
        $t->same(false, $maxPagesAbbrev['arguments']['defaults_applied']['batch_multiplier']);
        $t->same(false, $maxPagesAbbrev['arguments']['defaults_applied']['langs']);
        $t->same(false, $maxPagesAbbrev['executes_python_or_models']);
    },
];
