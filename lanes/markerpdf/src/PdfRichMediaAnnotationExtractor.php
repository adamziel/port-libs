<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfRichMediaAnnotationExtractor
{
    private const MAX_ACTION_CHAIN_DEPTH = 20;

    private const REVIEW_SUBTYPES = [
        '3D' => true,
        'Movie' => true,
        'RichMedia' => true,
        'Screen' => true,
        'Sound' => true,
    ];

    /**
     * Native review boundary for interactive PDF media annotations.
     *
     * The port records page-local metadata for rich media/screen annotations
     * without executing media actions, JavaScript, launch actions, or embedded
     * players.
     *
     * @return list<array{pnum: int, page_object: int, annotations: list<array<string, mixed>>}>
     */
    public function extractReviewAnnotations(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $pages = [];

        foreach ($this->orderedPageObjectNumbers($objects) as $pnum => $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber])) {
                continue;
            }

            $records = $this->annotationBodiesForPage($objects[$pageObjectNumber], $objects);
            $reversePopups = $this->popupRecordsByParentObject($records);
            $annotations = [];
            foreach ($records as $annotation) {
                $review = $this->reviewAnnotationFromBody($annotation['body'], $objects, $annotation['object'], $reversePopups);
                if ($review !== null) {
                    $annotations[] = $review;
                }
            }

            if ($annotations === []) {
                continue;
            }

            $pages[] = [
                'pnum' => $pnum,
                'page_object' => $pageObjectNumber,
                'annotations' => $annotations,
            ];
        }

        return $pages;
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesForPage(string $pageBody, array $objects): array
    {
        $annots = $this->valueAfterName($pageBody, 'Annots');
        if ($annots === null) {
            return [];
        }

        return $this->annotationBodiesFromValue($annots, $objects);
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return [];
            }

            $objectBody = trim($objects[$objectNumber]);
            if (str_starts_with($objectBody, '[')) {
                return $this->annotationBodiesFromArray($this->arrayBodyFromValue($objectBody), $objects);
            }

            $dictionary = $this->dictionaryObjectBody($objectBody);
            return $dictionary === null ? [] : [['body' => $dictionary, 'object' => $objectNumber]];
        }

        if (str_starts_with($value, '[')) {
            return $this->annotationBodiesFromArray($this->arrayBodyFromValue($value), $objects);
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null ? [] : [['body' => $dictionary, 'object' => null]];
        }

        return [];
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesFromArray(?string $arrayBody, array $objects): array
    {
        if ($arrayBody === null) {
            return [];
        }

        $annotations = [];
        foreach ($this->objectReferences($arrayBody) as $objectNumber) {
            if (!isset($objects[$objectNumber])) {
                continue;
            }

            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary !== null) {
                $annotations[] = ['body' => $dictionary, 'object' => $objectNumber];
            }
        }

        foreach ($this->directDictionaries($arrayBody) as $dictionary) {
            $annotations[] = ['body' => $dictionary, 'object' => null];
        }

        return $annotations;
    }

    /**
     * @param list<array{body: string, object: int|null}> $records
     * @return array<int, array{body: string, object: int|null}>
     */
    private function popupRecordsByParentObject(array $records): array
    {
        $popups = [];
        foreach ($records as $record) {
            if ($this->nameValueAfterName($record['body'], 'Subtype') !== 'Popup') {
                continue;
            }

            $parentObject = $this->objectReferenceValueAfterName($record['body'], 'Parent');
            if ($parentObject === null || isset($popups[$parentObject])) {
                continue;
            }

            $popups[$parentObject] = $record;
        }

        return $popups;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array{body: string, object: int|null}> $reversePopups
     * @return array<string, mixed>|null
     */
    private function reviewAnnotationFromBody(string $annotationBody, array $objects, ?int $annotationObject, array $reversePopups): ?array
    {
        $subtype = $this->nameValueAfterName($annotationBody, 'Subtype');
        if ($subtype === null || !isset(self::REVIEW_SUBTYPES[$subtype])) {
            return null;
        }

        $chainSafety = $this->emptyActionChainSafety();
        $actions = $this->actionReviewRowsFromAnnotation($annotationBody, $objects, $chainSafety);
        $actionDictionaries = $this->actionDictionaries($annotationBody, $objects);
        $contextBodies = $this->dedupeStrings(array_merge(
            [$annotationBody],
            $actionDictionaries,
            $this->referencedDictionaryBodies($annotationBody, $objects, 3),
            ...array_map(fn (string $body): array => $this->referencedDictionaryBodies($body, $objects, 2), $actionDictionaries)
        ));
        $actionTypes = $this->dedupeStrings(array_values(array_filter(
            array_map(static fn (array $action): ?string => $action['action_type'] ?? null, $actions),
            static fn (?string $value): bool => $value !== null && $value !== ''
        )));
        $actionUris = $this->dedupeStrings(array_values(array_filter(
            array_map(static fn (array $action): ?string => $action['uri'] ?? null, $actions),
            static fn (?string $value): bool => $value !== null && $value !== ''
        )));
        $assetNames = $this->assetNamesFromBodies($contextBodies);
        $fileNames = $this->fileNamesFromBodies($contextBodies);

        return [
            'subtype' => $subtype,
            'annotation_object' => $annotationObject,
            'rect' => $this->rectFromAnnotation($annotationBody),
            'title' => $this->stringAfterName($annotationBody, 'T'),
            'contents' => $this->stringAfterName($annotationBody, 'Contents'),
            'alternate_text' => $this->stringAfterName($annotationBody, 'Alt'),
            'action_types' => $actionTypes,
            'action_uris' => $actionUris,
            'actions' => $actions,
            'action_chain_safety' => $chainSafety,
            'asset_names' => $assetNames,
            'file_names' => $fileNames,
            'movie' => $this->movieDetailsFromAnnotation($annotationBody, $objects),
            'sound' => $this->soundDetailsFromAnnotation($annotationBody, $objects),
            'popup' => $this->popupFromAnnotation($annotationBody, $objects, $annotationObject, $reversePopups),
            'has_appearance' => $this->valueAfterName($annotationBody, 'AP') !== null,
            'has_rich_media_content' => $this->valueAfterName($annotationBody, 'RichMediaContent') !== null,
            'requires_review' => true,
            'executes_media' => false,
            'executes_javascript' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array{body: string, object: int|null}> $reversePopups
     * @return array<string, mixed>|null
     */
    private function popupFromAnnotation(string $annotationBody, array $objects, ?int $annotationObject, array $reversePopups): ?array
    {
        $record = null;
        $popup = $this->valueAfterName($annotationBody, 'Popup');
        if ($popup !== null) {
            $record = $this->resolvedDictionaryFromValue($popup, $objects);
        }

        if ($record === null && $annotationObject !== null) {
            $record = $reversePopups[$annotationObject] ?? null;
        }

        if ($record === null) {
            return null;
        }

        return [
            'object' => $record['object'],
            'rect' => $this->rectFromAnnotation($record['body']),
            'open' => $this->boolValueAfterName($record['body'], 'Open', $objects),
            'parent_object' => $this->objectReferenceValueAfterName($record['body'], 'Parent'),
            'contents' => $this->stringAfterName($record['body'], 'Contents'),
        ];
    }

    /**
     * @return array{max_depth: int, cycle_edges_blocked: int, max_depth_edges_blocked: int}
     */
    private function emptyActionChainSafety(): array
    {
        return [
            'max_depth' => self::MAX_ACTION_CHAIN_DEPTH,
            'cycle_edges_blocked' => 0,
            'max_depth_edges_blocked' => 0,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array{max_depth: int, cycle_edges_blocked: int, max_depth_edges_blocked: int} $chainSafety
     * @return list<array<string, mixed>>
     */
    private function actionReviewRowsFromAnnotation(string $annotationBody, array $objects, array &$chainSafety): array
    {
        $actions = [];
        $seen = [];

        $action = $this->topLevelValueAfterName($annotationBody, 'A');
        if ($action !== null) {
            $this->appendActionReviewRowsFromValue(
                $action,
                $objects,
                ['source' => 'annotation_action', 'event' => 'A'],
                $actions,
                $seen,
                $chainSafety
            );
        }

        $additionalActions = $this->topLevelValueAfterName($annotationBody, 'AA');
        if ($additionalActions === null) {
            return $actions;
        }

        $dictionary = $this->resolvedDictionaryFromValue($additionalActions, $objects);
        if ($dictionary === null) {
            return $actions;
        }

        foreach ($this->topLevelDictionaryEntries($dictionary['body']) as $entry) {
            $this->appendActionReviewRowsFromValue(
                $entry['value'],
                $objects,
                ['source' => 'annotation_additional_action', 'event' => $entry['name']],
                $actions,
                $seen,
                $chainSafety
            );
        }

        return $actions;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, mixed> $context
     * @param list<array<string, mixed>> $actions
     * @param array<string, true> $seen
     * @param array{max_depth: int, cycle_edges_blocked: int, max_depth_edges_blocked: int} $chainSafety
     * @param array<string, true> $chainPath
     */
    private function appendActionReviewRowsFromValue(
        string $value,
        array $objects,
        array $context,
        array &$actions,
        array &$seen,
        array &$chainSafety,
        int $chainIndex = 0,
        array $chainPath = []
    ): void {
        if ($chainIndex > self::MAX_ACTION_CHAIN_DEPTH) {
            $chainSafety['max_depth_edges_blocked']++;
            return;
        }

        $arrayValues = $this->arrayValuesFromPdfValue($value, $objects);
        if ($arrayValues !== null) {
            foreach ($arrayValues as $arrayValue) {
                $this->appendActionReviewRowsFromValue($arrayValue, $objects, $context, $actions, $seen, $chainSafety, $chainIndex, $chainPath);
            }

            return;
        }

        $record = $this->resolvedDictionaryFromValue($value, $objects);
        if ($record === null) {
            return;
        }

        $visitKey = $record['object'] === null ? 'inline:' . hash('sha256', $record['body']) : 'object:' . $record['object'];
        if (isset($chainPath[$visitKey])) {
            $chainSafety['cycle_edges_blocked']++;
            return;
        }
        $chainPath[$visitKey] = true;

        $identity = hash('sha256', serialize([$context, $chainIndex, $visitKey]));
        if (!isset($seen[$identity])) {
            $seen[$identity] = true;
            $review = $this->actionReviewRowFromBody($record['body'], $objects, $record['object'], $context, $chainIndex);
            if ($review !== null) {
                $actions[] = $review;
            }
        }

        $next = $this->topLevelValueAfterName($record['body'], 'Next');
        if ($next !== null) {
            $this->appendActionReviewRowsFromValue($next, $objects, $context, $actions, $seen, $chainSafety, $chainIndex + 1, $chainPath);
        }
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function actionReviewRowFromBody(string $actionBody, array $objects, ?int $actionObject, array $context, int $chainIndex): ?array
    {
        $actionType = $this->topLevelNameValueAfterName($actionBody, 'S');
        if ($actionType === null || $actionType === '') {
            return null;
        }

        $row = [
            'source' => $context['source'] ?? 'annotation_action',
            'event' => $context['event'] ?? null,
            'event_label' => $this->actionEventLabel($context['event'] ?? null),
            'action_type' => $actionType,
            'action_object' => $actionObject,
            'chain_index' => $chainIndex,
            'chained' => $chainIndex > 0,
            'safety' => $this->actionSafety($actionType, $actionBody, $objects),
            'executes_on_import' => false,
            'executes_media' => false,
            'executes_javascript' => false,
        ];

        if ($actionType === 'URI') {
            $uri = $this->topLevelStringValueAfterName($actionBody, 'URI', $objects);
            if ($uri !== null) {
                $row['uri'] = $uri;
                $row['is_safe_uri'] = $this->isSafeUri($uri);
            }
        }

        if ($actionType === 'Launch') {
            $file = $this->fileSpecValueFromTopLevel($actionBody, 'F', $objects);
            $win = $this->dictionaryFromTopLevelValue($actionBody, 'Win', $objects);
            if ($file === null && $win !== null) {
                $file = $this->fileSpecValueFromTopLevel($win['body'], 'F', $objects);
            }
            if ($file !== null) {
                $row['file'] = $file;
            }
            if ($win !== null) {
                $operation = $this->topLevelStringOrNameValueAfterName($win['body'], 'O', $objects);
                if ($operation !== null) {
                    $row['operation'] = $operation;
                }
            }
            $newWindow = $this->topLevelBoolValueAfterName($actionBody, 'NewWindow', $objects);
            if ($newWindow !== null) {
                $row['new_window'] = $newWindow;
            }
        }

        if ($actionType === 'GoToR') {
            $file = $this->fileSpecValueFromTopLevel($actionBody, 'F', $objects);
            if ($file !== null) {
                $row['file'] = $file;
            }
            $destination = $this->topLevelStringOrNameValueAfterName($actionBody, 'D', $objects);
            if ($destination !== null) {
                $row['destination'] = $destination;
            }
            $newWindow = $this->topLevelBoolValueAfterName($actionBody, 'NewWindow', $objects);
            if ($newWindow !== null) {
                $row['new_window'] = $newWindow;
            }
        }

        if ($actionType === 'GoTo') {
            $destination = $this->topLevelStringOrNameValueAfterName($actionBody, 'D', $objects);
            if ($destination !== null) {
                $row['destination'] = $destination;
            }
        }

        if ($actionType === 'Rendition') {
            $operation = $this->topLevelNumberValueAfterName($actionBody, 'OP', $objects);
            if ($operation !== null) {
                $row['operation'] = (int) $operation;
            }
            $rendition = $this->dictionaryFromTopLevelValue($actionBody, 'R', $objects);
            if ($rendition !== null) {
                $bodies = $this->dedupeStrings(array_merge(
                    [$rendition['body']],
                    $this->referencedDictionaryBodies($rendition['body'], $objects, 2)
                ));
                $row['file_names'] = $this->fileNamesFromBodies($bodies);
            }
        }

        if ($actionType === 'RichMediaExecute') {
            $targetAnnotation = $this->objectReferenceValueAfterName($actionBody, 'AN');
            if ($targetAnnotation !== null) {
                $row['target_annotation_object'] = $targetAnnotation;
            }

            $command = $this->dictionaryFromTopLevelValue($actionBody, 'CMD', $objects);
            if ($command !== null) {
                $commandName = $this->topLevelStringOrNameValueAfterName($command['body'], 'C', $objects);
                if ($commandName !== null) {
                    $row['command'] = $commandName;
                }
            }
        }

        if ($actionType === 'JavaScript') {
            $script = $this->topLevelStringValueAfterName($actionBody, 'JS', $objects);
            if ($script !== null) {
                $preview = $this->actionScriptPreview($script, 160);
                $row['script_preview'] = $preview['preview'];
                $row['script_truncated'] = $preview['truncated'];
                $row['script_sha256'] = hash('sha256', $script);
                $row['script_bytes'] = strlen($script);
            }
        }

        return $row;
    }

    private function actionSafety(string $actionType, string $actionBody, array $objects): string
    {
        if ($actionType === 'JavaScript') {
            return 'blocked-javascript';
        }

        if ($actionType === 'Launch') {
            return 'blocked-launch';
        }

        if ($actionType === 'URI') {
            $uri = $this->topLevelStringValueAfterName($actionBody, 'URI', $objects);
            return $uri !== null && $this->isSafeUri($uri) ? 'review-uri' : 'blocked-unsafe-uri';
        }

        return match ($actionType) {
            'Rendition' => 'media-rendition-review',
            'RichMediaExecute' => 'rich-media-execute-review',
            'Movie' => 'movie-action-review',
            'Sound' => 'sound-action-review',
            'GoToR' => 'remote-document-review',
            'GoTo' => 'local-destination',
            default => 'unsupported-action-review',
        };
    }

    private function actionEventLabel(?string $event): ?string
    {
        return match ($event) {
            'A' => 'annotation_activation',
            'E' => 'annotation_cursor_enter',
            'X' => 'annotation_cursor_exit',
            'D' => 'annotation_mouse_down',
            'U' => 'annotation_mouse_up',
            'Fo' => 'annotation_focus',
            'Bl' => 'annotation_blur',
            'PO' => 'annotation_page_open',
            'PC' => 'annotation_page_close',
            'PV' => 'annotation_page_visible',
            'PI' => 'annotation_page_invisible',
            default => $event === null ? null : 'annotation_additional_action',
        };
    }

    /**
     * @return array{preview: string, truncated: bool}
     */
    private function actionScriptPreview(string $script, int $previewBytes): array
    {
        $normalized = trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $script) ?? $script);
        $limit = max(16, $previewBytes);
        if (strlen($normalized) <= $limit) {
            return ['preview' => $normalized, 'truncated' => false];
        }

        return ['preview' => substr($normalized, 0, $limit) . '...', 'truncated' => true];
    }

    private function isSafeUri(string $uri): bool
    {
        $uri = trim($uri);
        if ($uri === '') {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $uri, $match) === 1) {
            return in_array(strtolower(rtrim($match[0], ':')), ['http', 'https', 'mailto', 'ftp'], true);
        }

        return !str_starts_with(ltrim(strtolower($uri)), 'javascript:');
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function movieDetailsFromAnnotation(string $annotationBody, array $objects): ?array
    {
        $movieValue = $this->valueAfterNameSkippingPreviousKeys($annotationBody, 'Movie', ['Subtype']);
        if ($movieValue === null) {
            return null;
        }

        $movie = $this->resolvedDictionaryFromValue($movieValue, $objects);
        if ($movie === null) {
            return null;
        }

        $movieBodies = $this->dedupeStrings(array_merge(
            [$movie['body']],
            $this->referencedDictionaryBodies($movie['body'], $objects, 2)
        ));

        return [
            'dictionary_object' => $movie['object'],
            'title' => $this->stringAfterName($movie['body'], 'T'),
            'file_names' => $this->fileNamesFromBodies($movieBodies),
            'aspect' => $this->numberArrayValueAfterName($movie['body'], 'Aspect', $objects),
            'rotation' => $this->intValueAfterName($movie['body'], 'Rotate', $objects),
            'poster' => $this->moviePosterValue($movie['body'], $objects),
            'activation' => $this->movieActivationFromAnnotation($annotationBody, $objects),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function movieActivationFromAnnotation(string $annotationBody, array $objects): ?array
    {
        $activationValue = $this->valueAfterName($annotationBody, 'A');
        if ($activationValue === null) {
            return null;
        }

        $activation = $this->resolvedDictionaryFromValue($activationValue, $objects);
        if ($activation === null) {
            return null;
        }

        return [
            'dictionary_object' => $activation['object'],
            'start' => $this->numberValueAfterName($activation['body'], 'Start', $objects),
            'duration' => $this->numberValueAfterName($activation['body'], 'Duration', $objects),
            'rate' => $this->numberValueAfterName($activation['body'], 'Rate', $objects),
            'volume' => $this->numberValueAfterName($activation['body'], 'Volume', $objects),
            'show_controls' => $this->boolValueAfterName($activation['body'], 'ShowControls', $objects),
            'mode' => $this->nameValueAfterName($activation['body'], 'Mode'),
            'synchronous' => $this->boolValueAfterName($activation['body'], 'Synchronous', $objects),
            'window_scale' => $this->numberArrayValueAfterName($activation['body'], 'FWScale', $objects),
            'window_position' => $this->numberArrayValueAfterName($activation['body'], 'FWPosition', $objects),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return bool|string|null
     */
    private function moviePosterValue(string $movieBody, array $objects): bool|string|null
    {
        $poster = $this->valueAfterName($movieBody, 'Poster');
        if ($poster === null) {
            return null;
        }

        $bool = $this->boolFromPdfValue($poster, $objects);
        if ($bool !== null) {
            return $bool;
        }

        if ($this->resolvedDictionaryFromValue($poster, $objects) !== null) {
            return 'stream';
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function soundDetailsFromAnnotation(string $annotationBody, array $objects): ?array
    {
        $soundValue = $this->valueAfterNameSkippingPreviousKeys($annotationBody, 'Sound', ['Subtype']);
        if ($soundValue === null) {
            return null;
        }

        $sound = $this->resolvedDictionaryFromValue($soundValue, $objects);
        if ($sound === null) {
            return null;
        }

        return [
            'stream_object' => $sound['object'],
            'icon_name' => $this->nameValueAfterName($annotationBody, 'Name'),
            'sample_rate' => $this->numberValueAfterName($sound['body'], 'R', $objects),
            'channels' => $this->intValueAfterName($sound['body'], 'C', $objects) ?? 1,
            'bits_per_sample' => $this->intValueAfterName($sound['body'], 'B', $objects) ?? 8,
            'encoding' => $this->nameValueAfterName($sound['body'], 'E') ?? 'Raw',
            'compression' => $this->nameValueAfterName($sound['body'], 'CO'),
            'payload_length' => $this->intValueAfterName($sound['body'], 'Length', $objects),
        ];
    }

    /**
     * @return list<string>|null
     * @param array<int, string> $objects
     */
    private function arrayValuesFromPdfValue(string $value, array $objects): ?array
    {
        $value = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            $value = trim($objects[$objectNumber] ?? '');
        }

        if (!str_starts_with($value, '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return null;
        }

        $values = [];
        for ($offset = 0, $length = strlen($arrayBody); $offset < $length;) {
            $this->skipWhitespaceAndComments($arrayBody, $offset);
            if ($offset >= $length) {
                break;
            }

            $endOffset = null;
            $item = $this->readPdfValueAtOffset($arrayBody, $offset, $endOffset);
            if ($item === null || $endOffset === null || $endOffset <= $offset) {
                $offset++;
                continue;
            }

            $values[] = $item;
            $offset = $endOffset;
        }

        return $values;
    }

    private function topLevelValueAfterName(string $body, string $name): ?string
    {
        foreach ($this->topLevelDictionaryEntries($body) as $entry) {
            if ($entry['name'] === $name) {
                return $entry['value'];
            }
        }

        return null;
    }

    private function topLevelNameValueAfterName(string $body, string $name): ?string
    {
        $value = $this->topLevelValueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        return preg_match('/^\/([^\s\[\]()<>{}\/%]+)/', $value, $match) === 1 ? $this->decodePdfName($match[1]) : null;
    }

    private function topLevelStringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $value = $this->topLevelValueAfterName($body, $name);
        return $value === null ? null : $this->pdfStringFromValue($value, $objects);
    }

    private function topLevelStringOrNameValueAfterName(string $body, string $name, array $objects): ?string
    {
        $value = $this->topLevelValueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        return $this->pdfStringFromValue($value, $objects) ?? $this->nameFromPdfValue($value, $objects);
    }

    private function topLevelNumberValueAfterName(string $body, string $name, array $objects): ?float
    {
        $value = $this->topLevelValueAfterName($body, $name);
        return $value === null ? null : $this->numberFromPdfValue($value, $objects);
    }

    private function topLevelBoolValueAfterName(string $body, string $name, array $objects): ?bool
    {
        $value = $this->topLevelValueAfterName($body, $name);
        return $value === null ? null : $this->boolFromPdfValue($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null}|null
     */
    private function dictionaryFromTopLevelValue(string $body, string $name, array $objects): ?array
    {
        $value = $this->topLevelValueAfterName($body, $name);
        return $value === null ? null : $this->resolvedDictionaryFromValue($value, $objects);
    }

    private function fileSpecValueFromTopLevel(string $body, string $name, array $objects): ?string
    {
        $value = $this->topLevelValueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $dictionary = $this->resolvedDictionaryFromValue($value, $objects);
        if ($dictionary !== null) {
            return $this->topLevelStringValueAfterName($dictionary['body'], 'UF', $objects)
                ?? $this->topLevelStringValueAfterName($dictionary['body'], 'F', $objects);
        }

        return $this->pdfStringFromValue($value, $objects);
    }

    private function pdfStringFromValue(string $value, array $objects): ?string
    {
        $value = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->pdfStringFromValue($objects[$objectNumber], $objects) : null;
        }

        $decoded = $this->stringAtOffset($value, 0);
        return $decoded['text'] ?? null;
    }

    private function nameFromPdfValue(string $value, array $objects): ?string
    {
        $value = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->nameFromPdfValue($objects[$objectNumber], $objects) : null;
        }

        return preg_match('/^\/([^\s\[\]()<>{}\/%]+)/', $value, $match) === 1 ? $this->decodePdfName($match[1]) : null;
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private function topLevelDictionaryEntries(string $body): array
    {
        $entries = [];
        for ($offset = 0, $length = strlen($body); $offset < $length;) {
            $this->skipWhitespaceAndComments($body, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($body[$offset] !== '/') {
                $offset++;
                continue;
            }

            $nameStart = ++$offset;
            while ($offset < $length && !$this->isDelimiter($body[$offset])) {
                $offset++;
            }

            $name = $this->decodePdfName(substr($body, $nameStart, $offset - $nameStart));
            $endOffset = null;
            $value = $this->readPdfValueAtOffset($body, $offset, $endOffset);
            if ($value === null || $endOffset === null) {
                continue;
            }

            $entries[] = ['name' => $name, 'value' => $value];
            $offset = $endOffset;
        }

        return $entries;
    }

    private function readPdfValueAtOffset(string $body, int $offset, ?int &$endOffset = null): ?string
    {
        $this->skipWhitespaceAndComments($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if ($body[$offset] === '[') {
            $this->readPdfArrayAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $this->readPdfDictionaryAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '(') {
            $endOffset = $this->skipLiteralString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $endOffset = $this->skipHexString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            $endOffset = $offset + strlen($ref[0]);
            return $ref[0];
        }

        if ($body[$offset] === '/') {
            $end = $offset + 1;
            while ($end < strlen($body) && !$this->isDelimiter($body[$end])) {
                $end++;
            }

            $endOffset = $end;
            return substr($body, $offset, $end - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !$this->isDelimiter($body[$end])) {
            $end++;
        }

        $endOffset = $end;
        return substr($body, $offset, max(0, $end - $offset));
    }

    private function skipWhitespaceAndComments(string $value, int &$offset): void
    {
        for ($length = strlen($value); $offset < $length;) {
            if (ctype_space($value[$offset])) {
                $offset++;
                continue;
            }

            if ($value[$offset] === '%') {
                while ($offset < $length && $value[$offset] !== "\n" && $value[$offset] !== "\r") {
                    $offset++;
                }
                continue;
            }

            break;
        }
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}/%', $char);
    }

    private function objectReferenceValueAfterName(string $body, string $name): ?int
    {
        $value = $this->topLevelValueAfterName($body, $name) ?? $this->valueAfterName($body, $name);
        if ($value === null || preg_match('/^(\d+)\s+\d+\s+R\b/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function actionDictionaries(string $annotationBody, array $objects): array
    {
        $actions = [];

        $action = $this->valueAfterName($annotationBody, 'A');
        if ($action !== null) {
            array_push($actions, ...$this->dictionariesFromValue($action, $objects));
        }

        $additionalActions = $this->valueAfterName($annotationBody, 'AA');
        if ($additionalActions !== null) {
            foreach ($this->dictionariesFromValue($additionalActions, $objects) as $dictionary) {
                foreach ($this->directDictionaries($dictionary) as $nestedDictionary) {
                    if ($this->nameValueAfterName($nestedDictionary, 'S') !== null) {
                        $actions[] = $nestedDictionary;
                    }
                }

                foreach ($this->referencedDictionaryBodies($dictionary, $objects, 1) as $referencedDictionary) {
                    if ($this->nameValueAfterName($referencedDictionary, 'S') !== null) {
                        $actions[] = $referencedDictionary;
                    }
                }
            }
        }

        return $this->dedupeStrings($actions);
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function dictionariesFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null ? [] : [$dictionary];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return [];
            }

            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            return $dictionary === null ? [] : [$dictionary];
        }

        return [];
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function referencedDictionaryBodies(string $body, array $objects, int $depth, array $seen = []): array
    {
        if ($depth <= 0) {
            return [];
        }

        $bodies = [];
        foreach ($this->objectReferences($body) as $objectNumber) {
            if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
                continue;
            }

            $seen[$objectNumber] = true;
            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary === null) {
                continue;
            }

            $bodies[] = $dictionary;
            array_push($bodies, ...$this->referencedDictionaryBodies($dictionary, $objects, $depth - 1, $seen));
        }

        return $bodies;
    }

    /**
     * @param list<string> $bodies
     * @return list<string>
     */
    private function assetNamesFromBodies(array $bodies): array
    {
        $names = [];
        foreach ($bodies as $body) {
            $namesValue = $this->valueAfterName($body, 'Names');
            if ($namesValue === null || !str_starts_with(trim($namesValue), '[')) {
                continue;
            }

            $arrayBody = $this->arrayBodyFromValue($namesValue);
            if ($arrayBody !== null) {
                array_push($names, ...$this->stringsInValue($arrayBody));
            }
        }

        return $this->dedupeStrings($names);
    }

    /**
     * @param list<string> $bodies
     * @return list<string>
     */
    private function fileNamesFromBodies(array $bodies): array
    {
        $names = [];
        foreach ($bodies as $body) {
            array_push($names, ...$this->stringsAfterName($body, 'F'));
            array_push($names, ...$this->stringsAfterName($body, 'UF'));
        }

        return $this->dedupeStrings($names);
    }

    /**
     * @return list<float>|null
     */
    private function rectFromAnnotation(string $annotationBody): ?array
    {
        $value = $this->valueAfterName($annotationBody, 'Rect');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 4) {
            return null;
        }

        $rect = array_slice($numbers, 0, 4);
        return [
            min($rect[0], $rect[2]),
            min($rect[1], $rect[3]),
            max($rect[0], $rect[2]),
            max($rect[1], $rect[3]),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>|null
     */
    private function numberArrayValueAfterName(string $body, string $name, array $objects): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
        return $arrayBody === null ? null : $this->numbersFromPdfArray($arrayBody);
    }

    /**
     * @param array<int, string> $objects
     */
    private function intValueAfterName(string $body, string $name, array $objects): ?int
    {
        $value = $this->numberValueAfterName($body, $name, $objects);
        return $value === null ? null : (int) $value;
    }

    /**
     * @param array<int, string> $objects
     */
    private function numberValueAfterName(string $body, string $name, array $objects): ?float
    {
        $value = $this->valueAfterName($body, $name);
        return $value === null ? null : $this->numberFromPdfValue($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function numberFromPdfValue(string $value, array $objects): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->numberFromPdfValue($objects[$objectNumber], $objects) : null;
        }

        return preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', $value, $match) === 1 ? (float) $match[0] : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function boolValueAfterName(string $body, string $name, array $objects): ?bool
    {
        $value = $this->valueAfterName($body, $name);
        return $value === null ? null : $this->boolFromPdfValue($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function boolFromPdfValue(string $value, array $objects): ?bool
    {
        $value = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->boolFromPdfValue($objects[$objectNumber], $objects) : null;
        }

        return match ($value) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null}|null
     */
    private function resolvedDictionaryFromValue(string $value, array $objects): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null ? null : ['body' => $dictionary, 'object' => null];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        if (!isset($objects[$objectNumber])) {
            return null;
        }

        $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
        return $dictionary === null ? null : ['body' => $dictionary, 'object' => $objectNumber];
    }

    /**
     * @param array<int, string> $objects
     */
    private function arrayBodyFromPdfValue(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '[')) {
            return $this->arrayBodyFromValue($value);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        if (!isset($objects[$objectNumber])) {
            return null;
        }

        return $this->arrayBodyFromValue(trim($objects[$objectNumber]));
    }

    private function valueAfterName(string $body, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        return $this->valueStartingAtOffset($body, $offset);
    }

    /**
     * @param list<string> $previousKeys
     */
    private function valueAfterNameSkippingPreviousKeys(string $body, string $name, array $previousKeys): ?string
    {
        $offset = 0;
        while (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $match[0][1];
            $before = rtrim(substr($body, 0, $start));
            $skip = false;
            foreach ($previousKeys as $previousKey) {
                if (preg_match('/\/' . preg_quote($previousKey, '/') . '\s*$/s', $before) === 1) {
                    $skip = true;
                    break;
                }
            }

            if (!$skip) {
                return $this->valueStartingAtOffset($body, $start + strlen($match[0][0]));
            }

            $offset = $start + strlen($match[0][0]);
        }

        return null;
    }

    private function valueStartingAtOffset(string $body, int $offset): ?string
    {
        while ($offset < strlen($body) && ctype_space($body[$offset])) {
            $offset++;
        }

        if ($offset >= strlen($body)) {
            return null;
        }

        if ($body[$offset] === '[') {
            $endOffset = null;
            $this->readPdfArrayAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $endOffset = null;
            $this->readPdfDictionaryAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '(') {
            $endOffset = $this->skipLiteralString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $endOffset = $this->skipHexString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            return $ref[0];
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }
        if ($end < strlen($body) && $body[$end] === '/') {
            return substr($body, $offset, $end - $offset);
        }

        return substr($body, $offset, max(0, $end - $offset));
    }

    private function nameValueAfterName(string $body, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s*\/([^\s\[\]()<>{}\/%]+)/s', $body, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1]);
    }

    private function stringAfterName(string $body, string $name): ?string
    {
        $strings = $this->stringsAfterName($body, $name);
        return $strings[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function stringsAfterName(string $body, string $name): array
    {
        $strings = [];
        $offset = 0;
        while (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $valueOffset = $match[0][1] + strlen($match[0][0]);
            while ($valueOffset < strlen($body) && ctype_space($body[$valueOffset])) {
                $valueOffset++;
            }

            $decoded = $this->stringAtOffset($body, $valueOffset);
            if ($decoded !== null) {
                $strings[] = $decoded['text'];
                $offset = $decoded['end'];
                continue;
            }

            $offset = $match[0][1] + strlen($match[0][0]);
        }

        return $strings;
    }

    /**
     * @return list<string>
     */
    private function stringsInValue(string $value): array
    {
        $strings = [];
        for ($offset = 0, $length = strlen($value); $offset < $length; $offset++) {
            $decoded = $this->stringAtOffset($value, $offset);
            if ($decoded === null) {
                continue;
            }

            $strings[] = $decoded['text'];
            $offset = $decoded['end'] - 1;
        }

        return $strings;
    }

    /**
     * @return array{text: string, end: int}|null
     */
    private function stringAtOffset(string $body, int $offset): ?array
    {
        if ($offset >= strlen($body)) {
            return null;
        }

        if ($body[$offset] === '(') {
            $endOffset = $this->skipLiteralString($body, $offset);
            return [
                'text' => $this->decodePdfStringBytes($this->decodeLiteralString(substr($body, $offset + 1, $endOffset - $offset - 2))),
                'end' => $endOffset,
            ];
        }

        if ($body[$offset] === '<' && substr($body, $offset, 2) !== '<<') {
            $endOffset = $this->skipHexString($body, $offset);
            $hex = preg_replace('/\s+/', '', substr($body, $offset + 1, $endOffset - $offset - 2));
            if ($hex === null || $hex === '') {
                return null;
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bytes = hex2bin($hex);
            if ($bytes === false) {
                return null;
            }

            return [
                'text' => $this->decodePdfStringBytes($bytes),
                'end' => $endOffset,
            ];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $objects = [];
        if (!preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $objects;
        }

        foreach ($matches as $match) {
            $objects[(int) $match[1]] = $match[2];
        }

        return $objects;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) !== 1 || preg_match('/\/Pages\s+(\d+)\s+\d+\s+R\b/s', $body, $match) !== 1) {
                continue;
            }

            $pages = $this->pageObjectNumbersFromTree((int) $match[1], $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $objects[$objectNumber];
        if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
            return [$objectNumber];
        }

        $kids = $this->valueAfterName($body, 'Kids');
        if ($kids === null || !str_starts_with(trim($kids), '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($kids);
        if ($arrayBody === null) {
            return [];
        }

        $pages = [];
        foreach ($this->objectReferences($arrayBody) as $childObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($childObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     */
    private function objectReferences(string $value): array
    {
        if (!preg_match_all('/(\d+)\s+\d+\s+R\b/', $value, $matches)) {
            return [];
        }

        return array_map('intval', $matches[1]);
    }

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    /**
     * @return list<string>
     */
    private function directDictionaries(string $value): array
    {
        $dictionaries = [];
        $offset = 0;
        while (($start = strpos($value, '<<', $offset)) !== false) {
            $endOffset = null;
            $body = $this->readPdfDictionaryAt($value, $start, $endOffset);
            if ($body === null || $endOffset === null) {
                break;
            }
            $dictionaries[] = $body;
            $offset = $endOffset;
        }

        return $dictionaries;
    }

    private function arrayBodyFromValue(string $value): ?string
    {
        $offset = strpos($value, '[');
        return $offset === false ? null : $this->readPdfArrayAt($value, $offset);
    }

    private function readPdfDictionaryAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) !== '<<') {
                $index = $this->skipHexString($value, $index) - 1;
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<') {
                $depth++;
                $index++;
                continue;
            }

            if ($pair !== '>>') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $endOffset = $index + 2;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
            $index++;
        }

        return null;
    }

    private function readPdfArrayAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (($value[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 1;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $endDictionary = null;
                $this->readPdfDictionaryAt($value, $index, $endDictionary);
                if ($endDictionary !== null) {
                    $index = $endDictionary - 1;
                    continue;
                }
            }
            if ($char === '<') {
                $index = $this->skipHexString($value, $index) - 1;
                continue;
            }
            if ($char === '[') {
                $depth++;
                continue;
            }
            if ($char !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $endOffset = $index + 1;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
        }

        return null;
    }

    private function skipLiteralString(string $value, int $offset): int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
        }

        return strlen($value);
    }

    private function skipHexString(string $value, int $offset): int
    {
        $end = strpos($value, '>', $offset + 1);
        return $end === false ? strlen($value) : $end + 1;
    }

    /**
     * @return list<float>
     */
    private function numbersFromPdfArray(string $arrayBody): array
    {
        if (!preg_match_all('/[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', $arrayBody, $matches)) {
            return [];
        }

        return array_map(static fn (string $value): float => (float) $value, $matches[0]);
    }

    private function decodeLiteralString(string $value): string
    {
        $out = '';
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char !== '\\') {
                $out .= $char;
                continue;
            }

            $index++;
            if ($index >= $length) {
                break;
            }

            $escaped = $value[$index];
            if (str_contains("nrtbf()\\", $escaped)) {
                $out .= match ($escaped) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\f",
                    default => $escaped,
                };
                continue;
            }

            if ($escaped === "\r" || $escaped === "\n") {
                if ($escaped === "\r" && ($value[$index + 1] ?? '') === "\n") {
                    $index++;
                }
                continue;
            }

            if ($escaped >= '0' && $escaped <= '7') {
                $octal = $escaped;
                for ($extra = 0; $extra < 2 && ($value[$index + 1] ?? '') >= '0' && ($value[$index + 1] ?? '') <= '7'; $extra++) {
                    $index++;
                    $octal .= $value[$index];
                }
                $out .= chr(octdec($octal) & 0xff);
                continue;
            }

            $out .= $escaped;
        }

        return $out;
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        if (str_starts_with($bytes, "\xfe\xff")) {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        if (str_starts_with($bytes, "\xff\xfe")) {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]+/', '', $bytes) ?? $bytes;
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function dedupeStrings(array $values): array
    {
        $seen = [];
        $out = [];
        foreach ($values as $value) {
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $out[] = $value;
        }

        return $out;
    }
}
