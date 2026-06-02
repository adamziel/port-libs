<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfAnnotationExtractor
{
    private const BORDER_STYLE_NAMES = [
        'S' => 'solid',
        'D' => 'dashed',
        'B' => 'beveled',
        'I' => 'inset',
        'U' => 'underline',
    ];

    private const ANNOTATION_FLAGS = [
        1 => 'invisible',
        2 => 'hidden',
        3 => 'print',
        4 => 'no_zoom',
        5 => 'no_rotate',
        6 => 'no_view',
        7 => 'read_only',
        8 => 'locked',
        9 => 'toggle_no_view',
        10 => 'locked_contents',
    ];

    private const COMMON_FIELD_FLAGS = [
        1 => 'read_only',
        2 => 'required',
        3 => 'no_export',
    ];

    private const FIELD_TYPE_FLAGS = [
        'Tx' => [
            13 => 'multiline',
            14 => 'password',
            21 => 'file_select',
            23 => 'do_not_spell_check',
            24 => 'do_not_scroll',
            25 => 'comb',
            26 => 'rich_text',
        ],
        'Btn' => [
            15 => 'no_toggle_to_off',
            16 => 'radio',
            17 => 'push_button',
            26 => 'radios_in_unison',
        ],
        'Ch' => [
            18 => 'combo',
            19 => 'edit',
            20 => 'sort',
            22 => 'multi_select',
            23 => 'do_not_spell_check',
            27 => 'commit_on_sel_change',
        ],
    ];

    private const WIDGET_HIGHLIGHT_MODE_LABELS = [
        'N' => 'none',
        'I' => 'invert',
        'O' => 'outline',
        'P' => 'push',
        'T' => 'toggle',
    ];

    private const WIDGET_TEXT_POSITION_LABELS = [
        0 => 'caption_only',
        1 => 'caption_above_icon',
        2 => 'caption_below_icon',
        3 => 'caption_right_of_icon',
        4 => 'caption_left_of_icon',
        5 => 'caption_overlaid_icon',
        6 => 'icon_only',
    ];

    /**
     * Native boundary for PDF page /Annots presentation metadata.
     *
     * @return list<array{pnum: int, page_object: int, annotations: list<array<string, mixed>>}>
     */
    public function extractPageAnnotations(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $actionReviewer = new PdfActionReviewExtractor($pdfBytes);
        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pages = [];

        foreach ($pageObjectNumbers as $pnum => $pageObjectNumber) {
            $pageBody = $objects[$pageObjectNumber] ?? null;
            if ($pageBody === null) {
                continue;
            }

            $records = $this->annotationRecordsForPage($pageBody, $objects);
            if ($records === []) {
                continue;
            }

            $reversePopups = $this->popupRecordsByParentObject($records);
            $annotations = [];
            foreach ($records as $record) {
                $subtype = $this->subtypeFromAnnotation($record['body']);
                if ($subtype === 'Popup' && $this->objectReferenceValueAfterName($record['body'], 'Parent') !== null) {
                    continue;
                }

                $annotations[] = $this->annotationReviewRow($record, $objects, $reversePopups, $actionReviewer);
            }

            if ($annotations !== []) {
                $pages[] = [
                    'pnum' => $pnum,
                    'page_object' => $pageObjectNumber,
                    'annotations' => $annotations,
                ];
            }
        }

        return $pages;
    }

    /**
     * @param array{body: string, object: int|null} $record
     * @param array<int, string> $objects
     * @param array<int, array{body: string, object: int|null}> $reversePopups
     * @return array<string, mixed>
     */
    private function annotationReviewRow(
        array $record,
        array $objects,
        array $reversePopups,
        PdfActionReviewExtractor $actionReviewer
    ): array
    {
        $body = $record['body'];
        $subtype = $this->subtypeFromAnnotation($body);
        $rect = $this->rectFromAnnotation($body);
        $actionReview = $actionReviewer->reviewAnnotationActions($body);

        $row = [
            'subtype' => $subtype,
            'annotation_object' => $record['object'],
            'rect' => $rect,
            'contents' => $this->pdfStringValueAfterName($body, 'Contents', $objects),
            'title' => $this->pdfStringValueAfterName($body, 'T', $objects),
            'name' => $this->pdfStringValueAfterName($body, 'NM', $objects),
            'modified_at' => $this->pdfStringValueAfterName($body, 'M', $objects),
            'border_color' => $this->colorValueAfterName($body, 'C', $objects),
            'interior_color' => $this->colorValueAfterName($body, 'IC', $objects),
            'opacity' => $this->opacityFromAnnotation($body, $objects),
            'border' => $this->borderFromAnnotation($body, $objects),
            'popup' => $this->popupFromAnnotation($body, $objects, $record['object'], $reversePopups),
            'actions' => $actionReview['actions'],
            'additional_actions' => $actionReview['additional_actions'],
            'executes_actions_on_import' => $actionReview['executes_actions_on_import'],
        ];

        $appearance = $this->appearanceFromAnnotation($body, $objects);
        if ($appearance !== null) {
            $row['appearance'] = $appearance;
        }

        if ($subtype === 'Widget') {
            $row['widget'] = $this->widgetReviewFromAnnotation(
                $body,
                $objects,
                $record['object'],
                $appearance,
                $actionReview
            );
        }

        $sound = $this->soundFromAnnotation($body, $objects);
        if ($sound !== null) {
            $row['sound'] = $sound;
        }

        $geometry = $this->geometryFromAnnotation($body, $objects, $subtype, $rect);
        if ($geometry !== null) {
            $row['geometry'] = $geometry;
        }

        return $row;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, mixed>|null $appearance
     * @param array{actions: list<array<string, mixed>>, additional_actions: list<array<string, mixed>>, executes_actions_on_import: false} $actionReview
     * @return array<string, mixed>
     */
    private function widgetReviewFromAnnotation(
        string $body,
        array $objects,
        ?int $widgetObject,
        ?array $appearance,
        array $actionReview
    ): array {
        $parentObjects = $this->parentFieldObjects($body, $objects);
        $parentBodies = [];
        foreach ($parentObjects as $parentObject) {
            $parentBody = $this->dictionaryObjectBody($objects[$parentObject] ?? '');
            if ($parentBody !== null) {
                $parentBodies[$parentObject] = $parentBody;
            }
        }

        $effectiveBodies = array_merge([$body], array_values($parentBodies));
        $fieldType = $this->pdfValueToStringFromFirst($effectiveBodies, 'FT', $objects);
        $fieldFlags = $this->intValueFromFirst($effectiveBodies, 'Ff', $objects) ?? 0;
        $annotationFlags = $this->intValueAfterName($body, 'F', $objects) ?? 0;
        $appearanceState = $this->pdfNameValueAfterName($body, 'AS');
        $appearanceSummary = $this->widgetAppearanceSummary($appearance, $appearanceState);
        $highlightMode = $this->pdfNameValueAfterName($body, 'H') ?? 'I';

        $review = [
            'source' => 'page_annotation_widget',
            'widget_object' => $widgetObject,
            'field_object' => $parentObjects[0] ?? ($this->widgetDictionaryHasFieldKeys($body) ? $widgetObject : null),
            'parent_field_objects' => $parentObjects,
            'field_name' => $this->widgetFieldName($body, $parentBodies, $objects),
            'mapping_name' => $this->pdfValueToStringFromFirst($effectiveBodies, 'TM', $objects),
            'field_type' => $fieldType,
            'field_type_label' => $this->fieldTypeLabel($fieldType),
            'field_flags' => $fieldFlags,
            'field_flag_names' => $this->fieldFlagNames($fieldFlags, $fieldType),
            'current_value' => $this->pdfValueToStringFromFirst($effectiveBodies, 'V', $objects),
            'default_value' => $this->pdfValueToStringFromFirst($effectiveBodies, 'DV', $objects),
            'annotation_flags' => $annotationFlags,
            'annotation_flag_names' => $this->annotationFlagNames($annotationFlags),
            'annotation_visibility' => $this->annotationVisibility($annotationFlags),
            'visible' => !$this->annotationFlagsHideWidget($annotationFlags),
            'hidden' => $this->annotationFlagsHideWidget($annotationFlags),
            'printable' => $this->hasFlagBit($annotationFlags, 3),
            'no_view' => $this->hasFlagBit($annotationFlags, 6),
            'highlight_mode' => $highlightMode,
            'highlight_mode_label' => self::WIDGET_HIGHLIGHT_MODE_LABELS[$highlightMode] ?? 'unknown',
            'appearance_state' => $appearanceState,
            'appearance_states' => $appearanceSummary['appearance_states'],
            'normal_appearance_type' => $appearanceSummary['normal_appearance_type'],
            'selected_appearance_object' => $appearanceSummary['selected_appearance_object'],
            'stale_appearance_state' => $appearanceSummary['stale_appearance_state'],
            'appearance_value_used_for_import' => false,
            'executes_appearance_streams' => false,
            'renders_appearance' => false,
            'executes_action' => false,
            'action_count' => count($actionReview['actions']),
            'additional_action_count' => count($actionReview['additional_actions']),
            'actions_are_review_only' => true,
            'current_page_annotation' => true,
            'detached_field_only' => false,
        ];

        $appearanceCharacteristics = $this->widgetAppearanceCharacteristics($body, $objects);
        if ($appearanceCharacteristics !== null) {
            $review['appearance_characteristics'] = $appearanceCharacteristics;
        }

        return $review;
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function parentFieldObjects(string $body, array $objects): array
    {
        $parents = [];
        $seen = [];
        $parentObject = $this->objectReferenceValueAfterName($body, 'Parent');
        while ($parentObject !== null && isset($objects[$parentObject]) && !isset($seen[$parentObject])) {
            $seen[$parentObject] = true;
            $parents[] = $parentObject;
            $parentBody = $this->dictionaryObjectBody($objects[$parentObject] ?? '');
            if ($parentBody === null) {
                break;
            }

            $parentObject = $this->objectReferenceValueAfterName($parentBody, 'Parent');
        }

        return $parents;
    }

    /**
     * @param array<int, string> $parentBodies
     */
    private function widgetFieldName(string $body, array $parentBodies, array $objects): ?string
    {
        $parts = [];
        foreach (array_reverse($parentBodies, true) as $parentBody) {
            $name = $this->pdfStringValueAfterName($parentBody, 'T', $objects);
            if ($name !== null && $name !== '') {
                $parts[] = $name;
            }
        }

        if ($parts === []) {
            $name = $this->pdfStringValueAfterName($body, 'T', $objects);
            if ($name !== null && $name !== '') {
                $parts[] = $name;
            }
        }

        return $parts === [] ? null : implode('.', $parts);
    }

    private function widgetDictionaryHasFieldKeys(string $body): bool
    {
        foreach (['FT', 'T', 'TM', 'V', 'DV', 'Ff', 'Kids'] as $name) {
            if ($this->valueAfterName($body, $name) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{appearance_states: list<string>, normal_appearance_type: string|null, selected_appearance_object: int|null, stale_appearance_state: bool|null}
     */
    private function widgetAppearanceSummary(?array $appearance, ?string $appearanceState): array
    {
        $summary = [
            'appearance_states' => [],
            'normal_appearance_type' => null,
            'selected_appearance_object' => null,
            'stale_appearance_state' => null,
        ];

        $normal = is_array($appearance['normal'] ?? null) ? $appearance['normal'] : null;
        if ($normal === null) {
            return $summary;
        }

        if (($normal['kind'] ?? null) === 'state-dictionary') {
            $selected = is_array($normal['selected'] ?? null) ? $normal['selected'] : null;
            return [
                'appearance_states' => array_values(array_filter(
                    $normal['states'] ?? [],
                    static fn (mixed $state): bool => is_string($state)
                )),
                'normal_appearance_type' => 'state-dictionary',
                'selected_appearance_object' => $selected['object'] ?? null,
                'stale_appearance_state' => $appearanceState === null ? null : $selected === null,
            ];
        }

        if (($normal['kind'] ?? null) === 'stream') {
            return [
                'appearance_states' => [],
                'normal_appearance_type' => 'direct_stream',
                'selected_appearance_object' => $normal['object'] ?? null,
                'stale_appearance_state' => null,
            ];
        }

        return $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function widgetAppearanceCharacteristics(string $body, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($body, 'MK') ?? $this->valueAfterName($body, 'MK');
        if ($value === null) {
            return null;
        }

        $mk = $this->resolvedDictionaryFromValue($value, $objects);
        if ($mk === null) {
            return null;
        }

        $textPosition = $this->intValueAfterName($mk['body'], 'TP', $objects);
        $iconFit = $this->widgetIconFitFromAppearanceCharacteristics($mk['body'], $objects);

        $review = [
            'source' => 'widget_mk_appearance_characteristics',
            'dictionary_object' => $mk['object'],
            'rotation' => $this->intValueAfterName($mk['body'], 'R', $objects),
            'border_color' => $this->colorValueAfterName($mk['body'], 'BC', $objects),
            'background_color' => $this->colorValueAfterName($mk['body'], 'BG', $objects),
            'normal_caption' => $this->pdfStringValueAfterName($mk['body'], 'CA', $objects),
            'rollover_caption' => $this->pdfStringValueAfterName($mk['body'], 'RC', $objects),
            'alternate_caption' => $this->pdfStringValueAfterName($mk['body'], 'AC', $objects),
            'text_position' => $textPosition,
            'text_position_label' => $textPosition === null ? null : (self::WIDGET_TEXT_POSITION_LABELS[$textPosition] ?? 'unknown'),
            'icon_object' => $this->objectReferenceFromValue($this->dictionaryRawValue($mk['body'], 'I')),
            'rollover_icon_object' => $this->objectReferenceFromValue($this->dictionaryRawValue($mk['body'], 'RI')),
            'alternate_icon_object' => $this->objectReferenceFromValue($this->dictionaryRawValue($mk['body'], 'IX')),
            'icon_fit' => $iconFit,
            'renders_appearance' => false,
            'executes_action' => false,
        ];

        return array_filter($review, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function widgetIconFitFromAppearanceCharacteristics(string $mkBody, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($mkBody, 'IF') ?? $this->valueAfterName($mkBody, 'IF');
        if ($value === null) {
            return null;
        }

        $iconFit = $this->resolvedDictionaryFromValue($value, $objects);
        if ($iconFit === null) {
            return null;
        }

        return array_filter([
            'scale_when' => $this->pdfNameValueAfterName($iconFit['body'], 'SW'),
            'scale_type' => $this->pdfNameValueAfterName($iconFit['body'], 'S'),
            'position' => $this->numberArrayValueAfterName($iconFit['body'], 'A', $objects),
            'fit_bounds' => $this->boolValueAfterName($iconFit['body'], 'FB'),
            'renders_icon' => false,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param list<string> $bodies
     * @param array<int, string> $objects
     */
    private function pdfValueToStringFromFirst(array $bodies, string $name, array $objects): ?string
    {
        foreach ($bodies as $body) {
            $value = $this->valueAfterName($body, $name);
            if ($value === null) {
                continue;
            }

            return $this->pdfValueToString($value, $objects);
        }

        return null;
    }

    /**
     * @param list<string> $bodies
     * @param array<int, string> $objects
     */
    private function intValueFromFirst(array $bodies, string $name, array $objects): ?int
    {
        foreach ($bodies as $body) {
            $value = $this->valueAfterName($body, $name);
            if ($value === null) {
                continue;
            }

            return $this->intValueFromPdfValue($value, $objects);
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function intValueFromPdfValue(string $value, array $objects): ?int
    {
        $value = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectBody = trim($objects[(int) $match[1]] ?? '');
            return $objectBody === '' ? null : $this->intValueFromPdfValue($objectBody, $objects);
        }

        return preg_match('/^[+-]?\d+/', $value, $match) === 1 ? (int) $match[0] : null;
    }

    /**
     * @return list<string>
     */
    private function fieldFlagNames(int $flags, ?string $fieldType): array
    {
        $names = [];
        foreach (self::COMMON_FIELD_FLAGS as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        foreach (self::FIELD_TYPE_FLAGS[$fieldType ?? ''] ?? [] as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function fieldTypeLabel(?string $fieldType): ?string
    {
        return match ($fieldType) {
            'Btn' => 'button',
            'Tx' => 'text',
            'Ch' => 'choice',
            'Sig' => 'signature',
            null => null,
            default => strtolower($fieldType),
        };
    }

    /**
     * @return list<string>
     */
    private function annotationFlagNames(int $flags): array
    {
        $names = [];
        foreach (self::ANNOTATION_FLAGS as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function annotationFlagsHideWidget(int $flags): bool
    {
        return $this->hasFlagBit($flags, 1)
            || $this->hasFlagBit($flags, 2)
            || $this->hasFlagBit($flags, 6);
    }

    private function annotationVisibility(int $flags): string
    {
        if ($this->hasFlagBit($flags, 2)) {
            return 'hidden';
        }

        if ($this->hasFlagBit($flags, 1)) {
            return 'invisible';
        }

        if ($this->hasFlagBit($flags, 6)) {
            return 'no_view';
        }

        return 'visible';
    }

    private function hasFlagBit(int $flags, int $bit): bool
    {
        return ($flags & (1 << ($bit - 1))) !== 0;
    }

    private function objectReferenceFromValue(?string $value): ?int
    {
        if ($value === null || preg_match('/^(\d+)\s+\d+\s+R\b/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function appearanceFromAnnotation(string $body, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($body, 'AP') ?? $this->valueAfterName($body, 'AP');
        if ($value === null) {
            return null;
        }

        $appearance = $this->resolvedDictionaryFromValue($value, $objects);
        if ($appearance === null) {
            return null;
        }

        $selectedState = $this->pdfNameValueAfterName($body, 'AS');
        $details = [
            'dictionary_object' => $appearance['object'],
            'renders_appearance' => false,
            'executes_actions' => false,
        ];

        foreach (['N' => 'normal', 'R' => 'rollover', 'D' => 'down'] as $pdfName => $key) {
            $entryValue = $this->dictionaryRawValue($appearance['body'], $pdfName);
            if ($entryValue === null) {
                continue;
            }

            $entry = $this->appearanceEntryFromValue($entryValue, $objects, $selectedState);
            if ($entry !== null) {
                $details[$key] = $entry;
            }
        }

        return count($details) > 3 ? $details : null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function appearanceEntryFromValue(string $value, array $objects, ?string $selectedState): ?array
    {
        $record = $this->resolvedDictionaryFromValue($value, $objects);
        if ($record === null) {
            return null;
        }

        if ($this->recordLooksLikeStream($record, $objects)) {
            return $this->appearanceStreamSummary($record, $objects);
        }

        $states = [];
        $appearances = [];
        foreach ($this->dictionaryEntries($record['body']) as $entry) {
            $summary = $this->appearanceStreamSummaryFromValue($entry['value'], $objects);
            if ($summary === null) {
                continue;
            }

            $states[] = $entry['name'];
            $appearances[$entry['name']] = $summary;
        }

        if ($appearances === []) {
            return null;
        }

        $selected = $selectedState !== null && isset($appearances[$selectedState])
            ? $appearances[$selectedState]
            : null;

        return [
            'kind' => 'state-dictionary',
            'states' => $states,
            'selected_state' => $selected === null ? null : $selectedState,
            'selected' => $selected,
            'appearances' => $appearances,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function appearanceStreamSummaryFromValue(string $value, array $objects): ?array
    {
        $record = $this->resolvedDictionaryFromValue($value, $objects);
        if ($record === null || !$this->recordLooksLikeStream($record, $objects)) {
            return null;
        }

        return $this->appearanceStreamSummary($record, $objects);
    }

    /**
     * @param array{body: string, object: int|null} $record
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function appearanceStreamSummary(array $record, array $objects): array
    {
        $filters = $this->nameArrayValueAfterName($record['body'], 'Filter', $objects) ?? [];
        $summary = [
            'kind' => 'stream',
            'object' => $record['object'],
            'type' => $this->pdfNameValueAfterName($record['body'], 'Type'),
            'subtype' => $this->pdfNameValueAfterName($record['body'], 'Subtype'),
            'bbox' => $this->numberArrayValueAfterName($record['body'], 'BBox', $objects),
            'matrix' => $this->numberArrayValueAfterName($record['body'], 'Matrix', $objects),
            'resource_keys' => $this->resourceKeysFromAppearanceStream($record['body'], $objects),
            'declared_length' => $this->intValueAfterName($record['body'], 'Length', $objects),
            'filters' => $filters,
            'has_stream_payload' => $this->recordLooksLikeStream($record, $objects),
            'payload_text_visible' => false,
        ];

        return array_filter($summary, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function resourceKeysFromAppearanceStream(string $streamDictionary, array $objects): array
    {
        $value = $this->dictionaryRawValue($streamDictionary, 'Resources');
        if ($value === null) {
            return [];
        }

        $resources = $this->resolvedDictionaryFromValue($value, $objects);
        if ($resources === null) {
            return [];
        }

        return array_values(array_map(
            static fn (array $entry): string => $entry['name'],
            $this->dictionaryEntries($resources['body'])
        ));
    }

    /**
     * @param array{body: string, object: int|null} $record
     * @param array<int, string> $objects
     */
    private function recordLooksLikeStream(array $record, array $objects): bool
    {
        if ($record['object'] !== null && str_contains($objects[$record['object']] ?? '', 'stream')) {
            return true;
        }

        return $this->pdfNameValueAfterName($record['body'], 'Subtype') === 'Form'
            || $this->intValueAfterName($record['body'], 'Length', $objects) !== null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function soundFromAnnotation(string $body, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($body, 'Sound');
        if ($value === null) {
            return null;
        }

        $sound = $this->resolvedDictionaryFromValue($value, $objects);
        if ($sound === null) {
            return null;
        }

        return [
            'stream_object' => $sound['object'],
            'icon_name' => $this->pdfNameValueAfterName($body, 'Name'),
            'sample_rate' => $this->floatValueAfterName($sound['body'], 'R', $objects),
            'channels' => $this->intValueAfterName($sound['body'], 'C', $objects) ?? 1,
            'bits_per_sample' => $this->intValueAfterName($sound['body'], 'B', $objects) ?? 8,
            'encoding' => $this->pdfNameValueAfterName($sound['body'], 'E') ?? 'Raw',
            'compression' => $this->pdfNameValueAfterName($sound['body'], 'CO'),
            'payload_length' => $this->intValueAfterName($sound['body'], 'Length', $objects),
            'filters' => $this->nameArrayValueAfterName($sound['body'], 'Filter', $objects) ?? [],
            'plays_on_import' => false,
            'payload_text_visible' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function annotationRecordsForPage(string $pageBody, array $objects): array
    {
        $annots = $this->valueAfterName($pageBody, 'Annots');
        if ($annots === null) {
            return [];
        }

        return $this->annotationRecordsFromValue($annots, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function annotationRecordsFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[')) {
            $body = $this->arrayBodyFromValue($value);
            return $body === null ? [] : $this->annotationRecordsFromArrayBody($body, $objects);
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null ? [] : [['body' => $dictionary, 'object' => null]];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) !== 1) {
            return [];
        }

        $objectNumber = (int) $match[1];
        $objectBody = trim($objects[$objectNumber] ?? '');
        if ($objectBody === '') {
            return [];
        }

        if (str_starts_with($objectBody, '[')) {
            $body = $this->arrayBodyFromValue($objectBody);
            return $body === null ? [] : $this->annotationRecordsFromArrayBody($body, $objects);
        }

        $dictionary = $this->dictionaryObjectBody($objectBody);
        return $dictionary === null ? [] : [['body' => $dictionary, 'object' => $objectNumber]];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function annotationRecordsFromArrayBody(string $body, array $objects): array
    {
        $records = [];
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            if (substr($body, $offset, 2) === '<<') {
                $endOffset = null;
                $dictionary = $this->readPdfDictionaryAt($body, $offset, $endOffset);
                if ($dictionary === null || $endOffset === null) {
                    $offset++;
                    continue;
                }

                $records[] = ['body' => $dictionary, 'object' => null];
                $offset = $endOffset;
                continue;
            }

            if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
                $objectNumber = (int) $match[1];
                $dictionary = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
                if ($dictionary !== null) {
                    $records[] = ['body' => $dictionary, 'object' => $objectNumber];
                }
                $offset += strlen($match[0]);
                continue;
            }

            $offset++;
        }

        return $records;
    }

    /**
     * @param list<array{body: string, object: int|null}> $records
     * @return array<int, array{body: string, object: int|null}>
     */
    private function popupRecordsByParentObject(array $records): array
    {
        $popups = [];
        foreach ($records as $record) {
            if ($this->subtypeFromAnnotation($record['body']) !== 'Popup') {
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

    private function subtypeFromAnnotation(string $body): string
    {
        return $this->pdfNameValueAfterName($body, 'Subtype') ?? 'Unknown';
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array{body: string, object: int|null}> $reversePopups
     * @return array<string, mixed>|null
     */
    private function popupFromAnnotation(string $body, array $objects, ?int $annotationObject, array $reversePopups): ?array
    {
        $record = null;
        $popup = $this->valueAfterName($body, 'Popup');
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
            'open' => $this->boolValueAfterName($record['body'], 'Open'),
            'parent_object' => $this->objectReferenceValueAfterName($record['body'], 'Parent'),
            'contents' => $this->pdfStringValueAfterName($record['body'], 'Contents', $objects),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function borderFromAnnotation(string $body, array $objects): ?array
    {
        $bs = $this->valueAfterName($body, 'BS');
        if ($bs !== null) {
            $dictionary = $this->resolvedDictionaryFromValue($bs, $objects);
            if ($dictionary !== null) {
                $width = $this->floatValueAfterName($dictionary['body'], 'W', $objects) ?? 1.0;
                $styleCode = $this->pdfNameValueAfterName($dictionary['body'], 'S') ?? 'S';
                $dashPattern = $this->numberArrayValueAfterName($dictionary['body'], 'D', $objects);

                return [
                    'source' => 'BS',
                    'width' => $width,
                    'style' => $width <= 0.0 ? 'none' : (self::BORDER_STYLE_NAMES[$styleCode] ?? strtolower($styleCode)),
                    'style_code' => $styleCode,
                    'dash_pattern' => $dashPattern,
                    'horizontal_corner_radius' => null,
                    'vertical_corner_radius' => null,
                ];
            }
        }

        $border = $this->valueAfterName($body, 'Border');
        if ($border === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($border, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 3) {
            return null;
        }

        $dashPattern = $this->dashPatternFromBorderArrayBody($arrayBody);
        $width = (float) $numbers[2];

        return [
            'source' => 'Border',
            'width' => $width,
            'style' => $width <= 0.0 ? 'none' : ($dashPattern === [] ? 'solid' : 'dashed'),
            'style_code' => $dashPattern === [] ? 'S' : 'D',
            'dash_pattern' => $dashPattern,
            'horizontal_corner_radius' => (float) $numbers[0],
            'vertical_corner_radius' => (float) $numbers[1],
        ];
    }

    /**
     * @return list<float>
     */
    private function dashPatternFromBorderArrayBody(string $arrayBody): array
    {
        $offset = 0;
        while (($start = strpos($arrayBody, '[', $offset)) !== false) {
            $endOffset = null;
            $body = $this->readPdfArrayAt($arrayBody, $start, $endOffset);
            if ($body === null || $endOffset === null) {
                break;
            }

            return $this->numbersFromPdfArray($body);
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return array{space: string, components: list<float>, hex: string|null}|null
     */
    private function colorValueAfterName(string $body, string $name, array $objects): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $components = array_map(fn (float $component): float => $this->clamp($component), $this->numbersFromPdfArray($arrayBody));
        $count = count($components);
        if ($count === 0) {
            return [
                'space' => 'transparent',
                'components' => [],
                'hex' => null,
            ];
        }

        if ($count === 1) {
            $rgb = [$components[0], $components[0], $components[0]];

            return [
                'space' => 'DeviceGray',
                'components' => $components,
                'hex' => $this->rgbHex($rgb),
            ];
        }

        if ($count === 3) {
            return [
                'space' => 'DeviceRGB',
                'components' => $components,
                'hex' => $this->rgbHex($components),
            ];
        }

        if ($count === 4) {
            [$c, $m, $y, $k] = $components;
            $rgb = [
                (1.0 - $c) * (1.0 - $k),
                (1.0 - $m) * (1.0 - $k),
                (1.0 - $y) * (1.0 - $k),
            ];

            return [
                'space' => 'DeviceCMYK',
                'components' => $components,
                'hex' => $this->rgbHex($rgb),
            ];
        }

        return [
            'space' => 'DeviceN',
            'components' => $components,
            'hex' => null,
        ];
    }

    private function opacityFromAnnotation(string $body, array $objects): ?float
    {
        $opacity = $this->floatValueAfterName($body, 'CA', $objects);

        return $opacity === null ? null : $this->clamp($opacity);
    }

    /**
     * @param array<int, string> $objects
     * @param list<float>|null $rect
     * @return array<string, mixed>|null
     */
    private function geometryFromAnnotation(string $body, array $objects, string $subtype, ?array $rect): ?array
    {
        return match ($subtype) {
            'Line' => $this->lineGeometryFromAnnotation($body, $objects),
            'Ink' => $this->inkGeometryFromAnnotation($body, $objects),
            'Polygon', 'PolyLine' => $this->verticesGeometryFromAnnotation($body, $objects, $subtype),
            'Square', 'Circle' => $this->rectShapeGeometryFromAnnotation($body, $objects, $subtype, $rect),
            'FreeText' => $this->freeTextGeometryFromAnnotation($body, $objects, $rect),
            default => null,
        };
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function lineGeometryFromAnnotation(string $body, array $objects): ?array
    {
        $points = $this->pointsFromNumberArray($this->numberArrayValueAfterName($body, 'L', $objects), 2);
        if (count($points) !== 2) {
            return null;
        }

        return [
            'type' => 'line',
            'points' => $points,
            'bbox' => $this->bboxFromPoints($points),
            'line_endings' => $this->nameArrayValueAfterName($body, 'LE', $objects),
            'leader_line_length' => $this->floatValueAfterName($body, 'LL', $objects),
            'leader_line_extension' => $this->floatValueAfterName($body, 'LLE', $objects),
            'caption' => $this->boolValueAfterName($body, 'Cap'),
            'intent' => $this->pdfNameValueAfterName($body, 'IT'),
            'caption_offset' => $this->numberArrayValueAfterName($body, 'CO', $objects),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function inkGeometryFromAnnotation(string $body, array $objects): ?array
    {
        $value = $this->valueAfterName($body, 'InkList');
        if ($value === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $paths = [];
        $offset = 0;
        $length = strlen($arrayBody);
        while ($offset < $length) {
            $start = strpos($arrayBody, '[', $offset);
            if ($start === false) {
                break;
            }

            $endOffset = null;
            $pathBody = $this->readPdfArrayAt($arrayBody, $start, $endOffset);
            if ($pathBody === null || $endOffset === null) {
                break;
            }

            $points = $this->pointsFromNumberArray($this->numbersFromPdfArray($pathBody));
            if (count($points) >= 2) {
                $paths[] = $points;
            }
            $offset = $endOffset;
        }

        if ($paths === []) {
            $points = $this->pointsFromNumberArray($this->numbersFromPdfArray($arrayBody));
            if (count($points) >= 2) {
                $paths[] = $points;
            }
        }

        if ($paths === []) {
            return null;
        }

        $pathBboxes = array_map(fn (array $path): array => $this->bboxFromPoints($path), $paths);

        return [
            'type' => 'ink',
            'paths' => $paths,
            'path_bboxes' => $pathBboxes,
            'bbox' => $this->unionRects($pathBboxes),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function verticesGeometryFromAnnotation(string $body, array $objects, string $subtype): ?array
    {
        $vertices = $this->pointsFromNumberArray($this->numberArrayValueAfterName($body, 'Vertices', $objects));
        if (count($vertices) < 2) {
            return null;
        }

        $geometry = [
            'type' => $subtype === 'Polygon' ? 'polygon' : 'polyline',
            'vertices' => $vertices,
            'bbox' => $this->bboxFromPoints($vertices),
            'closed' => $subtype === 'Polygon',
        ];

        $lineEndings = $this->nameArrayValueAfterName($body, 'LE', $objects);
        if ($lineEndings !== null) {
            $geometry['line_endings'] = $lineEndings;
        }

        return $geometry;
    }

    /**
     * @param array<int, string> $objects
     * @param list<float>|null $rect
     * @return array<string, mixed>|null
     */
    private function rectShapeGeometryFromAnnotation(string $body, array $objects, string $subtype, ?array $rect): ?array
    {
        if ($rect === null) {
            return null;
        }

        $rectDifference = $this->rectDifferenceFromAnnotation($body, $objects);
        $shapeRect = $rectDifference === null ? $rect : $this->insetRect($rect, $rectDifference);
        $size = [
            max(0.0, $shapeRect[2] - $shapeRect[0]),
            max(0.0, $shapeRect[3] - $shapeRect[1]),
        ];

        $geometry = [
            'type' => strtolower($subtype),
            'rect' => $rect,
            'rect_difference' => $rectDifference,
            'shape_rect' => $shapeRect,
            'center' => [
                ($shapeRect[0] + $shapeRect[2]) / 2.0,
                ($shapeRect[1] + $shapeRect[3]) / 2.0,
            ],
            'size' => $size,
        ];

        if ($subtype === 'Circle') {
            $geometry['radii'] = [$size[0] / 2.0, $size[1] / 2.0];
        }

        return $geometry;
    }

    /**
     * @param array<int, string> $objects
     * @param list<float>|null $rect
     * @return array<string, mixed>|null
     */
    private function freeTextGeometryFromAnnotation(string $body, array $objects, ?array $rect): ?array
    {
        $calloutLine = $this->pointsFromNumberArray($this->numberArrayValueAfterName($body, 'CL', $objects));
        if (count($calloutLine) < 2) {
            return null;
        }

        $geometry = [
            'type' => 'callout',
            'callout_line' => $calloutLine,
            'bbox' => $this->bboxFromPoints($calloutLine),
            'rect' => $rect,
            'rect_difference' => $this->rectDifferenceFromAnnotation($body, $objects),
            'elbow_point' => count($calloutLine) >= 3 ? $calloutLine[1] : null,
        ];

        $lineEndings = $this->nameArrayValueAfterName($body, 'LE', $objects);
        if ($lineEndings !== null) {
            $geometry['line_endings'] = $lineEndings;
        }

        return $geometry;
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>|null
     */
    private function rectDifferenceFromAnnotation(string $body, array $objects): ?array
    {
        $numbers = $this->numberArrayValueAfterName($body, 'RD', $objects);
        return $numbers !== null && count($numbers) >= 4 ? array_slice($numbers, 0, 4) : null;
    }

    /**
     * @param list<float> $rect
     * @param list<float> $difference
     * @return list<float>
     */
    private function insetRect(array $rect, array $difference): array
    {
        $shapeRect = [
            $rect[0] + $difference[0],
            $rect[1] + $difference[1],
            $rect[2] - $difference[2],
            $rect[3] - $difference[3],
        ];

        return $this->normalizeRect($shapeRect);
    }

    /**
     * @param list<float>|null $numbers
     * @return list<array{0: float, 1: float}>
     */
    private function pointsFromNumberArray(?array $numbers, ?int $limit = null): array
    {
        if ($numbers === null) {
            return [];
        }

        $points = [];
        for ($index = 0, $count = count($numbers) - 1; $index < $count; $index += 2) {
            $points[] = [(float) $numbers[$index], (float) $numbers[$index + 1]];
            if ($limit !== null && count($points) >= $limit) {
                break;
            }
        }

        return $points;
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     * @return list<float>
     */
    private function bboxFromPoints(array $points): array
    {
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        return [min($xs), min($ys), max($xs), max($ys)];
    }

    /**
     * @param list<list<float>> $rects
     * @return list<float>
     */
    private function unionRects(array $rects): array
    {
        return [
            min(array_column($rects, 0)),
            min(array_column($rects, 1)),
            max(array_column($rects, 2)),
            max(array_column($rects, 3)),
        ];
    }

    /**
     * @param list<float> $components
     */
    private function rgbHex(array $components): string
    {
        $parts = [];
        foreach (array_slice($components, 0, 3) as $component) {
            $parts[] = str_pad(dechex((int) round($this->clamp($component) * 255)), 2, '0', STR_PAD_LEFT);
        }

        return '#' . implode('', $parts);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
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

        return $this->normalizeRect(array_slice($numbers, 0, 4));
    }

    /**
     * @param list<float> $rect
     * @return list<float>
     */
    private function normalizeRect(array $rect): array
    {
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
        $offset = 0;
        while (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $valueOffset = $match[0][1] + strlen($match[0][0]);
            $value = $this->valueStartingAtOffset($body, $valueOffset);
            if ($value === null) {
                return null;
            }

            $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
            if ($arrayBody !== null) {
                return $this->numbersFromPdfArray($arrayBody);
            }

            $offset = $valueOffset;
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>|null
     */
    private function nameArrayValueAfterName(string $body, string $name, array $objects): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
        if ($arrayBody !== null) {
            if (!preg_match_all('/\/([^\s\[\]<>()\/%]+)/', $arrayBody, $matches)) {
                return [];
            }

            return array_map(fn (string $name): string => $this->decodePdfName($name), $matches[0]);
        }

        $value = trim($value);
        if (str_starts_with($value, '/')) {
            return [$this->decodePdfName($value)];
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function floatValueAfterName(string $body, string $name, array $objects): ?float
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectBody = trim($objects[(int) $match[1]] ?? '');
            return $objectBody === '' ? null : $this->floatFromPdfValue($objectBody);
        }

        return $this->floatFromPdfValue($value);
    }

    private function floatFromPdfValue(string $value): ?float
    {
        return preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', trim($value), $match) === 1 ? (float) $match[0] : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function intValueAfterName(string $body, string $name, array $objects): ?int
    {
        $value = $this->floatValueAfterName($body, $name, $objects);

        return $value === null ? null : (int) $value;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfStringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $value = $this->valueAfterName($body, $name);
        return $value === null ? null : $this->pdfValueToString($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfValueToString(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($value[0] === '(') {
            $end = $this->skipLiteralString($value, 0);
            return $this->decodePdfStringBytes($this->decodeLiteralString(substr($value, 1, $end - 2)));
        }

        if ($value[0] === '<' && substr($value, 0, 2) !== '<<') {
            $end = $this->skipHexString($value, 0);
            $hex = preg_replace('/\s+/', '', substr($value, 1, $end - 2)) ?? '';
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bytes = $hex === '' ? '' : hex2bin($hex);
            return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
        }

        if ($value[0] === '/') {
            return $this->decodePdfName($value);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1 && isset($objects[(int) $match[1]])) {
            return $this->pdfValueToString(trim($objects[(int) $match[1]]), $objects);
        }

        return $value === 'null' ? null : null;
    }

    private function pdfNameValueAfterName(string $body, string $name): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || !str_starts_with(trim($value), '/')) {
            return null;
        }

        return $this->decodePdfName($value);
    }

    private function boolValueAfterName(string $body, string $name): ?bool
    {
        $value = $this->valueAfterName($body, $name);

        return match (trim((string) $value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    private function objectReferenceValueAfterName(string $body, string $name): ?int
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || preg_match('/^(\d+)\s+\d+\s+R\b/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function valueAfterName(string $body, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        return $this->valueStartingAtOffset($body, $offset);
    }

    private function valueStartingAtOffset(string $body, int $offset): ?string
    {
        return $this->valueStartingAtOffsetWithEnd($body, $offset);
    }

    private function valueStartingAtOffsetWithEnd(string $body, int $offset, ?int &$endOffset = null): ?string
    {
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            $endOffset = $offset + strlen($ref[0]);
            return $ref[0];
        }

        if ($body[$offset] === '[') {
            $arrayEndOffset = null;
            $this->readPdfArrayAt($body, $offset, $arrayEndOffset);
            $endOffset = $arrayEndOffset;
            return $arrayEndOffset === null ? null : substr($body, $offset, $arrayEndOffset - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $dictionaryEndOffset = null;
            $this->readPdfDictionaryAt($body, $offset, $dictionaryEndOffset);
            $endOffset = $dictionaryEndOffset;
            return $dictionaryEndOffset === null ? null : substr($body, $offset, $dictionaryEndOffset - $offset);
        }

        if ($body[$offset] === '(') {
            $literalEndOffset = $this->skipLiteralString($body, $offset);
            $endOffset = $literalEndOffset;
            return substr($body, $offset, $literalEndOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $hexEndOffset = $this->skipHexString($body, $offset);
            $endOffset = $hexEndOffset;
            return substr($body, $offset, $hexEndOffset - $offset);
        }

        if ($body[$offset] === '/') {
            $nameEndOffset = $this->skipPdfName($body, $offset);
            $endOffset = $nameEndOffset;
            return substr($body, $offset, $nameEndOffset - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        $endOffset = $end;
        return substr($body, $offset, max(0, $end - $offset));
    }

    private function dictionaryRawValue(string $body, string $name): ?string
    {
        foreach ($this->dictionaryEntries($body) as $entry) {
            if ($entry['name'] === $name) {
                return $entry['value'];
            }
        }

        return null;
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private function dictionaryEntries(string $body): array
    {
        $entries = [];
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($body[$offset] !== '/') {
                $offset++;
                continue;
            }

            $nameEnd = $this->skipPdfName($body, $offset);
            $name = $this->decodePdfName(substr($body, $offset + 1, $nameEnd - $offset - 1));
            $valueEnd = null;
            $value = $this->valueStartingAtOffsetWithEnd($body, $nameEnd, $valueEnd);
            if ($value === null || $valueEnd === null || $valueEnd <= $nameEnd) {
                $offset = max($nameEnd, $offset + 1);
                continue;
            }

            $entries[] = [
                'name' => $name,
                'value' => $value,
            ];
            $offset = $valueEnd;
        }

        return $entries;
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
            $body = $this->readPdfDictionaryAt($value, 0);
            return $body === null ? null : ['body' => $body, 'object' => null];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        $body = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
        return $body === null ? null : ['body' => $body, 'object' => $objectNumber];
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

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectBody = trim($objects[(int) $match[1]] ?? '');
            return $objectBody === '' ? null : $this->arrayBodyFromPdfValue($objectBody, $objects);
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

    private function skipWhitespace(string $body, int &$offset): void
    {
        while ($offset < strlen($body) && ctype_space($body[$offset])) {
            $offset++;
        }
    }

    private function skipPdfName(string $body, int $offset): int
    {
        $end = $offset + 1;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return $end;
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

        return array_map('floatval', $matches[0]);
    }

    private function decodePdfName(string $name): string
    {
        $name = ltrim($name, '/');

        return preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        $prefix = strtolower(bin2hex(substr($bytes, 0, 2)));
        if ($prefix === 'feff') {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }
        if ($prefix === 'fffe') {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }

    private function decodeLiteralString(string $value): string
    {
        $value = preg_replace("/\\\\\r\n|\\\\\n|\\\\\r/s", '', $value) ?? $value;

        return preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', static function (array $match): string {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => preg_match('/^[0-7]+$/', $match[1]) === 1 ? chr(octdec($match[1]) & 0xff) : $match[1],
            };
        }, $value) ?? $value;
    }
}
