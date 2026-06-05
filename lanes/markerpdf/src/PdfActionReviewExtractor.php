<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfActionReviewExtractor
{
    private const MAX_ACTION_CHAIN_DEPTH = 20;

    private const VALID_DESTINATION_VIEW_NAMES = [
        'Fit' => true,
        'FitB' => true,
        'FitBH' => true,
        'FitBV' => true,
        'FitH' => true,
        'FitR' => true,
        'FitV' => true,
        'XYZ' => true,
    ];

    private const SUBMIT_FORM_FLAG_NAMES = [
        2 => 'include_no_value_fields',
        3 => 'html_format',
        4 => 'get_method',
        5 => 'submit_coordinates',
        6 => 'xfdf_format',
        7 => 'include_append_saves',
        8 => 'include_annotations',
        9 => 'submit_pdf',
        10 => 'canonical_format',
        11 => 'exclude_non_user_annotations',
        12 => 'exclude_f_key',
        14 => 'embed_form',
    ];

    private const ANNOTATION_ACTION_EVENT_LABELS = [
        'E' => 'cursor_enter',
        'X' => 'cursor_exit',
        'D' => 'mouse_down',
        'U' => 'mouse_up',
        'Fo' => 'focus',
        'Bl' => 'blur',
        'PO' => 'page_open',
        'PC' => 'page_close',
        'PV' => 'page_visible',
        'PI' => 'page_invisible',
    ];

    /** @var array<int, mixed> */
    private array $objects;

    /** @var array<int, array<int, mixed>> */
    private array $objectsByGeneration = [];

    /** @var array<string, int> */
    private array $pageIndexesByReference;

    /** @var array<string, mixed> */
    private array $destinations;

    private ?string $uriBase;

    public function __construct(string $pdfBytes)
    {
        $this->objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($this->objects);

        $pageObjectReferences = $this->orderedPageObjectReferences($this->objects, $catalog);
        $this->pageIndexesByReference = [];
        foreach ($pageObjectReferences as $index => $reference) {
            $this->pageIndexesByReference[$this->referenceKey($reference['object'], $reference['generation'])] = $index;
        }

        $this->destinations = $catalog === null ? [] : $this->destinationMap($catalog, $this->objects);
        $this->uriBase = $catalog === null ? null : $this->catalogUriBase($catalog);
    }

    /**
     * @return array{
     *     actions: list<array<string, mixed>>,
     *     additional_actions: list<array<string, mixed>>,
     *     previous_uri_actions: list<array<string, mixed>>,
     *     executes_actions_on_import: false
     * }
     */
    public function reviewAnnotationActions(string $annotationBody): array
    {
        $dict = $this->dictionaryFromBody($annotationBody);
        if ($dict === null) {
            return [
                'actions' => [],
                'additional_actions' => [],
                'previous_uri_actions' => [],
                'executes_actions_on_import' => false,
            ];
        }

        $seen = [];
        $actions = [];
        if (array_key_exists('A', $dict)) {
            $actions = $this->reviewPrimaryAnnotationActionsFromValue($dict['A'], $seen);
        } elseif (array_key_exists('Dest', $dict)) {
            $action = $this->localDestinationReview($dict['Dest']);
            if ($action !== null) {
                $actions[] = $action;
            }
        }

        $previousUriActions = [];
        if (array_key_exists('PA', $dict)) {
            $seen = [];
            foreach ($this->reviewActionsFromValue($dict['PA'], $seen) as $action) {
                $action['previous_uri_action'] = true;
                $previousUriActions[] = $action;
            }
        }

        return [
            'actions' => $actions,
            'additional_actions' => $this->additionalActionMetadata($dict['AA'] ?? null),
            'previous_uri_actions' => $previousUriActions,
            'executes_actions_on_import' => false,
        ];
    }

    /**
     * Link and annotation `/A` entries are a single action dictionary with an
     * `/S` action subtype. Arrays are valid under action `/Next`, and local
     * destinations are valid under `/Dest`, but malformed top-level `/A`
     * values must not donate a primary WordPress link target.
     *
     * @param array<string, true> $seen
     * @return list<array<string, mixed>>
     */
    private function reviewPrimaryAnnotationActionsFromValue(mixed $value, array &$seen): array
    {
        $resolved = $this->resolveValue($value);
        $dict = $this->dictionaryItems($resolved);
        if ($dict === null || $this->nameValue($this->resolveValue($dict['S'] ?? null)) === null) {
            return [];
        }

        return $this->reviewActionsFromValue($value, $seen);
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param array<string, mixed> $structureParent
     * @return list<array<string, mixed>>
     */
    public static function actionsWithAnnotationStructureParentContext(
        array $actions,
        ?int $annotationObject,
        int $structParent,
        array $structureParent
    ): array {
        if ($actions === []) {
            return [];
        }

        $context = self::compactAnnotationStructureParentActionContext($structureParent);
        $associatedFiles = is_array($structureParent['associated_files'] ?? null)
            ? array_values($structureParent['associated_files'])
            : [];
        $associatedFileCount = is_int($structureParent['associated_file_count'] ?? null)
            ? $structureParent['associated_file_count']
            : count($associatedFiles);

        return array_map(
            static function (array $action) use (
                $annotationObject,
                $structParent,
                $context,
                $associatedFiles,
                $associatedFileCount
            ): array {
                $action['source_annotation_object'] = $annotationObject;
                $action['annotation_struct_parent'] = $structParent;
                $action['annotation_structure_parent'] = $context;
                if ($associatedFileCount > 0) {
                    $action['annotation_associated_file_count'] = $associatedFileCount;
                    $action['annotation_associated_files'] = $associatedFiles;
                }

                return $action;
            },
            $actions
        );
    }

    /**
     * @param array<string, mixed> $structureParent
     * @return array<string, mixed>
     */
    private static function compactAnnotationStructureParentActionContext(array $structureParent): array
    {
        $context = [];
        foreach ([
            'source',
            'key',
            'struct_object',
            'annotation_object',
            'raw_role',
            'role',
            'role_mapped',
            'title',
            'language',
            'language_inherited',
            'alternate_text',
            'actual_text',
            'expansion_text',
            'id',
            'classes',
            'associated_file_count',
            'associated_files',
            'current_annotation_object_ref_matched',
            'current_page_annotation',
            'review_only',
            'visible_text_source',
        ] as $key) {
            if (array_key_exists($key, $structureParent)) {
                $context[$key] = $structureParent[$key];
            }
        }

        return array_filter($context, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function additionalActionMetadata(mixed $value): array
    {
        $additionalActions = $this->resolveDictionary($value);
        if ($additionalActions === null) {
            return [];
        }

        $actions = [];
        foreach ($additionalActions as $event => $actionValue) {
            $seen = [];
            foreach ($this->reviewActionsFromValue($actionValue, $seen) as $action) {
                $actions[] = [
                    'event' => $event,
                    'event_label' => self::ANNOTATION_ACTION_EVENT_LABELS[$event] ?? 'annotation_additional_action',
                ] + $action;
            }
        }

        return $actions;
    }

    /**
     * @param array<string, true> $seen
     * @return list<array<string, mixed>>
     */
    private function reviewActionsFromValue(mixed $value, array &$seen, int $depth = 0): array
    {
        if ($value === null || $depth > self::MAX_ACTION_CHAIN_DEPTH) {
            return [];
        }

        $resolved = $this->resolveValue($value);
        $array = $this->arrayItems($resolved);
        if ($array !== null) {
            $actions = [];
            foreach ($array as $item) {
                foreach ($this->reviewActionsFromValue($item, $seen, $depth + 1) as $action) {
                    $actions[] = $action;
                }
            }

            return $actions;
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            $action = $this->localDestinationReview($value);
            return $action === null ? [] : [$action];
        }

        $actionReference = $this->referenceObject($value);
        $actionObject = $actionReference['object'] ?? null;
        $identity = $actionReference === null
            ? 'dict:' . hash('sha256', serialize($dict))
            : 'obj:' . $actionReference['object'] . ':' . $actionReference['generation'];
        if (isset($seen[$identity])) {
            return [];
        }
        $seen[$identity] = true;

        $type = $this->nameValue($this->resolveValue($dict['S'] ?? null));
        $action = $this->reviewActionFromDictionary($dict, $value, $type);
        if ($action === null && $type !== null) {
            $action = $this->reviewAction($type, 'unsupported-action-review', null, null, null, [], [], null, null, null, null);
        }

        $actions = [];
        if ($action !== null) {
            if ($actionObject !== null) {
                $action['action_object'] = $actionObject;
            }
            if ($depth > 0) {
                $action['chained'] = true;
                $action['chain_index'] = $depth;
            }
            $actions[] = $action;
        }

        if (array_key_exists('Next', $dict)) {
            foreach ($this->reviewActionsFromValue($dict['Next'], $seen, $depth + 1) as $nextAction) {
                $nextAction['chained'] = true;
                $nextAction['chain_index'] = $nextAction['chain_index'] ?? ($depth + 1);
                $actions[] = $nextAction;
            }
        }

        return $actions;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>|null
     */
    private function reviewActionFromDictionary(array $action, mixed $originalValue, ?string $type): ?array
    {
        if ($type === 'GoTo' && array_key_exists('D', $action)) {
            return $this->localDestinationReview($action['D']);
        }

        if ($type === 'URI') {
            $uri = $this->stringOrNameValue($this->resolveValue($action['URI'] ?? null));
            if ($uri === null || trim($uri) === '') {
                return null;
            }

            $uriReview = $this->uriReview($uri);
            $isMap = $this->boolValue($action['IsMap'] ?? null) ?? false;
            $safety = $uriReview['is_safe_uri']
                ? ($isMap ? 'coordinate-dependent-uri-review' : 'review-uri')
                : 'blocked-unsafe-uri';

            return $this->reviewAction(
                'URI',
                $safety,
                null,
                null,
                null,
                [],
                [],
                $uriReview['uri'],
                null,
                null,
                $uriReview['is_safe_uri']
            ) + $uriReview['metadata'] + [
                'uri_is_map' => $isMap,
                'requires_activation_coordinates' => $isMap,
            ];
        }

        if ($type === 'GoToR') {
            $target = $this->remoteGoToTargetFromAction($action);
            if ($target === null) {
                return null;
            }

            return $this->reviewAction(
                'GoToR',
                'remote-document-review',
                $target['page'],
                $target['destination'],
                $target['view_mode'],
                $target['view_position'],
                $target['view_parameters'],
                null,
                $target['file'],
                null,
                null,
                $target['new_window']
            );
        }

        if ($type === 'Launch') {
            $file = $this->fileSpecValue($action['F'] ?? null);
            $win = $this->resolveDictionary($action['Win'] ?? null);
            if ($file === null && $win !== null) {
                $file = $this->fileSpecValue($win['F'] ?? null);
            }
            if ($file === null || trim($file) === '') {
                return null;
            }

            $operation = $win === null ? null : $this->stringOrNameValue($this->resolveValue($win['O'] ?? null));

            return $this->reviewAction(
                'Launch',
                'blocked-launch',
                null,
                null,
                null,
                [],
                [],
                null,
                $file,
                $operation,
                null,
                is_bool($action['NewWindow'] ?? null) ? $action['NewWindow'] : null
            );
        }

        if ($type === 'JavaScript') {
            return $this->reviewAction('JavaScript', 'blocked-javascript', null, null, null, [], [], null, null, null, null);
        }

        if ($type === 'Named') {
            return $this->namedActionReview($action);
        }

        if ($type === 'ImportData') {
            return $this->importDataActionReview($action);
        }

        if ($type === 'Hide') {
            return $this->hideActionReview($action);
        }

        if ($type === 'SubmitForm' || $type === 'ResetForm') {
            return $this->formActionReview($action, $type);
        }

        if ($type === null) {
            return $this->localDestinationReview($originalValue);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function namedActionReview(array $action): array
    {
        $name = $this->stringOrNameValue($this->resolveValue($action['N'] ?? null));

        return $this->reviewAction('Named', 'named-action-review', null, null, null, [], [], null, null, null, null)
            + ['named_action' => $name];
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function importDataActionReview(array $action): array
    {
        $file = $this->fileSpecValue($action['F'] ?? null);

        return $this->reviewAction('ImportData', 'import-data-action-review', null, null, null, [], [], null, $file, null, null)
            + [
                'target' => $file,
                'target_scheme' => $this->uriScheme($file),
                'imports_form_data' => false,
            ];
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function hideActionReview(array $action): array
    {
        $selection = $this->actionTargetSelection($action['T'] ?? null);
        $hide = is_bool($action['H'] ?? null) ? $action['H'] : true;

        $row = $this->reviewAction('Hide', 'hide-action-review', null, null, null, [], [], null, null, null, null);
        $row['hide'] = $hide;
        $row['operation'] = $hide ? 'hide' : 'show';
        $row['field_objects'] = $selection['field_objects'];
        $row['field_names'] = $selection['field_names'];
        $row['unresolved_field_objects'] = $selection['unresolved_field_objects'];

        return $row;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function formActionReview(array $action, string $type): array
    {
        $flags = $this->intValue($action['Flags'] ?? null) ?? 0;
        $selection = $this->actionFieldSelection($action['Fields'] ?? null, $type, $flags);
        $file = $type === 'SubmitForm' ? $this->fileSpecValue($action['F'] ?? null) : null;

        $row = $this->reviewAction(
            $type,
            $type === 'SubmitForm' ? 'submit-form-action-review' : 'reset-form-action-review',
            null,
            null,
            null,
            [],
            [],
            null,
            $file,
            null,
            null
        ) + [
            'flags' => $flags,
            'flag_names' => $this->actionFlagNames($type, $flags),
            'fields_mode' => $selection['fields_mode'],
            'field_objects' => $selection['field_objects'],
            'field_names' => $selection['field_names'],
            'unresolved_field_objects' => $selection['unresolved_field_objects'],
        ];

        if ($type === 'SubmitForm') {
            $row += [
                'target' => $file,
                'target_scheme' => $this->uriScheme($file),
                'submit_format' => $this->submitFormatFromFlags($flags),
                'requested_submit_format' => $this->submitFormatFromFlags($flags),
                'include_no_value_fields' => $this->hasFlagBit($flags, 2),
                'html_format_requested' => $this->hasFlagBit($flags, 3),
                'get_method_requested' => $this->hasFlagBit($flags, 4),
                'submit_coordinates_requested' => $this->hasFlagBit($flags, 5),
                'xfdf_requested' => $this->hasFlagBit($flags, 6),
                'include_append_saves_requested' => $this->hasFlagBit($flags, 7),
                'include_annotations_requested' => $this->hasFlagBit($flags, 8),
                'submit_pdf_requested' => $this->hasFlagBit($flags, 9),
                'canonical_format_requested' => $this->hasFlagBit($flags, 10),
                'exclude_non_user_annotations_requested' => $this->hasFlagBit($flags, 11),
                'exclude_f_key_requested' => $this->hasFlagBit($flags, 12),
                'embed_form_requested' => $this->hasFlagBit($flags, 14),
                'submits_pdf_on_import' => false,
                'embeds_form_on_import' => false,
                'includes_annotations_on_import' => false,
                'uses_get_method_on_import' => false,
                'default_excludes_no_export' => true,
            ];
        } else {
            $row['reset_to_default'] = true;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function localDestinationReview(mixed $destination): ?array
    {
        $details = $this->destinationViewDetails($destination);
        if ($details === null) {
            return null;
        }

        return $this->reviewAction(
            'GoTo',
            'local-destination',
            $details['page'],
            $details['destination'],
            $details['view_mode'],
            $details['view_position'],
            $details['view_parameters'],
            null,
            null,
            null,
            null
        );
    }

    /**
     * @param list<float|null> $viewPosition
     * @param array<string, float|null> $viewParameters
     * @return array<string, mixed>
     */
    private function reviewAction(
        string $type,
        string $safety,
        ?int $page,
        ?string $destination,
        ?string $viewMode,
        array $viewPosition,
        array $viewParameters,
        ?string $uri,
        ?string $file,
        ?string $operation,
        ?bool $isSafeUri,
        ?bool $newWindow = null
    ): array {
        return [
            'action_type' => $type,
            'safety' => $safety,
            'page' => $page,
            'destination_page' => $page,
            'destination' => $destination,
            'view_mode' => $viewMode,
            'view_position' => $viewPosition,
            'view_parameters' => $viewParameters,
            'uri' => $uri,
            'file' => $file,
            'operation' => $operation,
            'new_window' => $newWindow,
            'is_safe_uri' => $isSafeUri,
            'executes_on_import' => false,
        ];
    }

    /**
     * @return array{file: string, destination: string|null, page: int|null, view_mode: string|null, view_position: list<float|null>, view_parameters: array<string, float|null>, new_window: bool|null}|null
     */
    private function remoteGoToTargetFromAction(array $action): ?array
    {
        $file = $this->fileSpecValue($action['F'] ?? null);
        if ($file === null || !array_key_exists('D', $action)) {
            return null;
        }

        $destination = $this->remoteDestinationValue($action['D']);
        if ($destination === null) {
            return null;
        }

        return [
            'file' => $file,
            'destination' => $destination['destination'],
            'page' => $destination['page'],
            'view_mode' => $destination['view_mode'],
            'view_position' => $destination['view_position'],
            'view_parameters' => $destination['view_parameters'],
            'new_window' => is_bool($action['NewWindow'] ?? null) ? $action['NewWindow'] : null,
        ];
    }

    /**
     * @return array{destination: string|null, page: int|null, view_mode: string|null, view_position: list<float|null>, view_parameters: array<string, float|null>}|null
     */
    private function remoteDestinationValue(mixed $value): ?array
    {
        $resolved = $this->resolveValue($value);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            return [
                'destination' => $name,
                'page' => null,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null && array_key_exists('D', $dict)) {
            return $this->remoteDestinationValue($dict['D']);
        }

        $array = $this->arrayItems($resolved);
        if ($array === null || $array === []) {
            return null;
        }

        $first = $this->resolveValue($array[0]);
        if (is_int($first) && $first >= 0) {
            $viewMode = $this->nameValue($this->resolveValue($array[1] ?? null));
            $viewPosition = [];
            for ($index = 2, $count = count($array); $index < $count; $index++) {
                $viewPosition[] = $this->numericOrNullValue($this->resolveValue($array[$index]));
            }
            if ($viewMode === 'XYZ' && array_key_exists(2, $viewPosition) && $viewPosition[2] === 0.0) {
                $viewPosition[2] = null;
            }

            return [
                'destination' => null,
                'page' => $first,
                'view_mode' => $viewMode,
                'view_position' => $viewPosition,
                'view_parameters' => $this->viewParameters($viewMode, $viewPosition),
            ];
        }

        $name = $this->stringOrNameValue($first);
        if ($name !== null) {
            return [
                'destination' => $name,
                'page' => null,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        return null;
    }

    private function fileSpecValue(mixed $value): ?string
    {
        $resolved = $this->resolveValue($value);
        $file = $this->stringOrNameValue($resolved);
        if ($file !== null && $file !== '') {
            return $file;
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            return null;
        }

        foreach (['UF', 'F', 'DOS', 'Unix', 'Mac'] as $key) {
            $file = $this->stringOrNameValue($this->resolveValue($dict[$key] ?? null));
            if ($file !== null && $file !== '') {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return array{fields_mode: string, field_objects: list<int>, field_names: list<string>, unresolved_field_objects: list<int>}
     */
    private function actionFieldSelection(mixed $value, string $actionType, int $flags): array
    {
        if ($value === null) {
            return [
                'fields_mode' => $actionType === 'SubmitForm' ? 'all_exportable' : 'all',
                'field_objects' => [],
                'field_names' => [],
                'unresolved_field_objects' => [],
            ];
        }

        $selection = $this->actionTargetSelection($value);

        return [
            'fields_mode' => $this->hasFlagBit($flags, 1) ? 'exclude' : 'include',
            'field_objects' => $selection['field_objects'],
            'field_names' => $selection['field_names'],
            'unresolved_field_objects' => $selection['unresolved_field_objects'],
        ];
    }

    /**
     * @return array{field_objects: list<int>, field_names: list<string>, unresolved_field_objects: list<int>}
     */
    private function actionTargetSelection(mixed $value): array
    {
        $selection = [
            'field_objects' => [],
            'field_names' => [],
            'unresolved_field_objects' => [],
        ];

        $this->collectActionTarget($value, $selection);

        $selection['field_objects'] = array_values(array_unique($selection['field_objects']));
        $selection['field_names'] = array_values(array_unique($selection['field_names']));
        $selection['unresolved_field_objects'] = array_values(array_unique($selection['unresolved_field_objects']));

        return $selection;
    }

    /**
     * @param array{field_objects: list<int>, field_names: list<string>, unresolved_field_objects: list<int>} $selection
     */
    private function collectActionTarget(mixed $value, array &$selection, int $depth = 0): void
    {
        if ($value === null || $depth > 20) {
            return;
        }

        $objectNumber = $this->referenceObjectNumber($value);
        if ($objectNumber !== null) {
            $resolved = $this->resolveValue($value);
            $array = $this->arrayItems($resolved);
            if ($array !== null) {
                foreach ($array as $item) {
                    $this->collectActionTarget($item, $selection, $depth + 1);
                }

                return;
            }

            $selection['field_objects'][] = $objectNumber;
            $dict = $this->dictionaryItems($resolved);
            if ($dict !== null) {
                $name = $this->stringOrNameValue($this->resolveValue($dict['T'] ?? null));
                if ($name !== null && $name !== '') {
                    $selection['field_names'][] = $name;
                } else {
                    $selection['unresolved_field_objects'][] = $objectNumber;
                }

                return;
            }

            $name = $this->stringOrNameValue($resolved);
            if ($name !== null && $name !== '') {
                $selection['field_names'][] = $name;
            } else {
                $selection['unresolved_field_objects'][] = $objectNumber;
            }

            return;
        }

        $resolved = $this->resolveValue($value);
        $array = $this->arrayItems($resolved);
        if ($array !== null) {
            foreach ($array as $item) {
                $this->collectActionTarget($item, $selection, $depth + 1);
            }

            return;
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null) {
            $this->collectActionTarget($dict['T'] ?? null, $selection, $depth + 1);

            return;
        }

        $name = $this->stringOrNameValue($resolved);
        if ($name !== null && $name !== '') {
            $selection['field_names'][] = $name;
        }
    }

    /**
     * @return list<string>
     */
    private function actionFlagNames(string $actionType, int $flags): array
    {
        $names = [];
        if ($this->hasFlagBit($flags, 1)) {
            $names[] = 'exclude_list';
        }

        if ($actionType === 'SubmitForm') {
            foreach (self::SUBMIT_FORM_FLAG_NAMES as $bit => $name) {
                if ($this->hasFlagBit($flags, $bit)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    private function submitFormatFromFlags(int $flags): string
    {
        if ($this->hasFlagBit($flags, 9)) {
            return 'pdf';
        }

        if ($this->hasFlagBit($flags, 6)) {
            return 'xfdf';
        }

        if ($this->hasFlagBit($flags, 3)) {
            return 'html';
        }

        return 'fdf';
    }

    private function hasFlagBit(int $flags, int $oneBasedBit): bool
    {
        return ($flags & (1 << ($oneBasedBit - 1))) !== 0;
    }

    private function intValue(mixed $value): ?int
    {
        $resolved = $this->resolveValue($value);
        if (is_int($resolved)) {
            return $resolved;
        }

        return is_float($resolved) ? (int) $resolved : null;
    }

    private function boolValue(mixed $value): ?bool
    {
        $resolved = $this->resolveValue($value);

        return is_bool($resolved) ? $resolved : null;
    }

    /**
     * @return array{page: int, destination: string|null, view_mode: string|null, view_position: list<float|null>, view_parameters: array<string, float|null>}|null
     */
    private function destinationViewDetails(mixed $destination, ?string $destinationName = null, array $seenNames = []): ?array
    {
        $pageReference = $this->referenceObject($destination);
        $pageIndex = $pageReference === null ? null : $this->pageIndexForReference($pageReference);
        if ($pageIndex !== null) {
            return [
                'page' => $pageIndex,
                'destination' => $destinationName,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        $resolved = $this->resolveValue($destination);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            if (isset($seenNames[$name]) || !array_key_exists($name, $this->destinations)) {
                return null;
            }
            $seenNames[$name] = true;

            return $this->destinationViewDetails($this->destinations[$name], $name, $seenNames);
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null && array_key_exists('D', $dict)) {
            return $this->destinationViewDetails($dict['D'], $destinationName, $seenNames);
        }

        $array = $this->arrayItems($resolved);
        if ($array !== null && $array !== []) {
            if (!$this->destinationArrayViewModeIsValid($array)) {
                return null;
            }

            return $this->explicitDestinationDetails($array, $destinationName);
        }

        if (is_int($resolved) && $resolved >= 0) {
            return [
                'page' => $resolved,
                'destination' => $destinationName,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        return null;
    }

    /**
     * @param list<mixed> $array
     * @return array{page: int, destination: string|null, view_mode: string|null, view_position: list<float|null>, view_parameters: array<string, float|null>}|null
     */
    private function explicitDestinationDetails(array $array, ?string $destinationName): ?array
    {
        $page = $this->destinationPageFromValue($array[0] ?? null);
        if ($page === null) {
            return null;
        }

        $viewMode = $this->nameValue($this->resolveValue($array[1] ?? null));
        $viewPosition = [];
        for ($index = 2, $count = count($array); $index < $count; $index++) {
            $viewPosition[] = $this->numericOrNullValue($this->resolveValue($array[$index]));
        }

        if ($viewMode === 'XYZ' && array_key_exists(2, $viewPosition) && $viewPosition[2] === 0.0) {
            $viewPosition[2] = null;
        }

        return [
            'page' => $page,
            'destination' => $destinationName,
            'view_mode' => $viewMode,
            'view_position' => $viewPosition,
            'view_parameters' => $this->viewParameters($viewMode, $viewPosition),
        ];
    }

    private function destinationPageFromValue(mixed $value): ?int
    {
        $pageReference = $this->referenceObject($value);
        if ($pageReference !== null) {
            $pageIndex = $this->pageIndexForReference($pageReference);
            if ($pageIndex !== null) {
                return $pageIndex;
            }
        }

        $resolved = $this->resolveValue($value);
        $resolvedPageReference = $this->referenceObject($resolved);
        if ($resolvedPageReference !== null) {
            $pageIndex = $this->pageIndexForReference($resolvedPageReference);
            if ($pageIndex !== null) {
                return $pageIndex;
            }
        }

        if (is_int($resolved) && $resolved >= 0 && $resolved < count($this->pageIndexesByReference)) {
            return $resolved;
        }

        return $this->destinationViewDetails($resolved)['page'] ?? null;
    }

    private function destinationValueAllowedForMap(mixed $value, int $depth = 0): bool
    {
        if ($depth > 20) {
            return false;
        }

        $pageReference = $this->referenceObject($value);
        if ($pageReference !== null) {
            $pageIndex = $this->pageIndexForReference($pageReference);
            if ($pageIndex !== null) {
                return true;
            }
        }

        $resolved = $this->resolveValue($value);
        if ($this->stringOrNameValue($resolved) !== null) {
            return true;
        }

        $resolvedPageReference = $this->referenceObject($resolved);
        if ($resolvedPageReference !== null) {
            $pageIndex = $this->pageIndexForReference($resolvedPageReference);
            if ($pageIndex !== null) {
                return true;
            }
        }

        if (is_int($resolved)) {
            return $resolved >= 0 && $resolved < count($this->pageIndexesByReference);
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null && array_key_exists('D', $dict)) {
            return $this->destinationValueAllowedForMap($dict['D'], $depth + 1);
        }

        $array = $this->arrayItems($resolved);
        if ($array !== null && $array !== []) {
            if (!$this->destinationArrayViewModeIsValid($array)) {
                return false;
            }

            return $this->destinationValueAllowedForMap($array[0], $depth + 1);
        }

        return false;
    }

    /**
     * @param list<mixed> $array
     */
    private function destinationArrayViewModeIsValid(array $array): bool
    {
        if (count($array) < 2) {
            return false;
        }

        $viewMode = $this->nameValue($this->resolveValue($array[1] ?? null));

        return $viewMode !== null
            && isset(self::VALID_DESTINATION_VIEW_NAMES[$viewMode])
            && $this->destinationViewCoordinateOperandsAreValid($array, $viewMode);
    }

    /**
     * @param list<mixed> $array
     */
    private function destinationViewCoordinateOperandsAreValid(array $array, string $viewMode): bool
    {
        $requiredOperands = match ($viewMode) {
            'XYZ' => [2 => true, 3 => true, 4 => true],
            'FitH', 'FitBH', 'FitV', 'FitBV' => [2 => true],
            'FitR' => [2 => true, 3 => true, 4 => true, 5 => true],
            default => [],
        };

        foreach ($requiredOperands as $index => $allowsNull) {
            if (!array_key_exists($index, $array)) {
                return false;
            }

            $resolved = $this->resolveValue($array[$index]);
            if ($resolved === null) {
                if ($allowsNull) {
                    continue;
                }

                return false;
            }

            if (!is_int($resolved) && !is_float($resolved)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<float|null> $viewPosition
     * @return array<string, float|null>
     */
    private function viewParameters(?string $viewMode, array $viewPosition): array
    {
        $names = match ($viewMode) {
            'XYZ' => ['left', 'top', 'zoom'],
            'FitH', 'FitBH' => ['top'],
            'FitV', 'FitBV' => ['left'],
            'FitR' => ['left', 'bottom', 'right', 'top'],
            default => [],
        };

        $parameters = [];
        foreach ($names as $index => $name) {
            $parameters[$name] = $viewPosition[$index] ?? null;
        }

        return $parameters;
    }

    private function numericOrNullValue(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    /**
     * @return array<int, mixed>
     */
    private function parsedObjectValues(string $pdfBytes): array
    {
        $values = [];
        $this->objectsByGeneration = [];

        foreach ($this->rawObjectDefinitions($pdfBytes) as $definition) {
            $tokens = $this->tokens($this->firstObjectValue(trim($definition['body'])));
            if ($tokens === []) {
                continue;
            }

            $index = 0;
            $value = $this->parseValue($tokens, $index);
            $objectNumber = $definition['object'];
            $generation = $definition['generation'];
            $this->objectsByGeneration[$objectNumber][$generation] = $value;
            $values[$objectNumber] = $value;
        }

        return $values;
    }

    /**
     * @return list<array{object: int, generation: int, body: string}>
     */
    private function rawObjectDefinitions(string $pdfBytes): array
    {
        $objects = [];
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $objects;
        }

        foreach ($matches as $match) {
            $objects[] = [
                'object' => (int) $match[1],
                'generation' => (int) $match[2],
                'body' => $match[3],
            ];
        }

        return $objects;
    }

    private function firstObjectValue(string $body): string
    {
        if (!str_starts_with($body, '<<')) {
            return $body;
        }

        $endOffset = $this->dictionaryEndOffset($body, 0);
        return $endOffset === null ? $body : substr($body, 0, $endOffset);
    }

    private function dictionaryEndOffset(string $value, int $offset): ?int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $this->readLiteralToken($value, $index);
                $index--;
                continue;
            }

            if ($char === '%') {
                while ($index < $length && $value[$index] !== "\n" && $value[$index] !== "\r") {
                    $index++;
                }
                continue;
            }

            if ($char === '<' && substr($value, $index, 2) !== '<<') {
                $this->readHexToken($value, $index);
                $index--;
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
                return $index + 2;
            }

            $index++;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dictionaryFromBody(string $body): ?array
    {
        $tokens = $this->tokens('<< ' . $body . ' >>');
        $index = 0;

        return $this->dictionaryItems($this->parseValue($tokens, $index));
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>|null
     */
    private function catalogDictionary(array $objects): ?array
    {
        foreach ($objects as $value) {
            $dict = $this->dictionaryItems($value);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Catalog') {
                return $dict;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<string, mixed>|null $catalog
     * @return list<array{object: int, generation: int}>
     */
    private function orderedPageObjectReferences(array $objects, ?array $catalog): array
    {
        if ($catalog !== null) {
            $pagesRoot = $this->referenceObject($catalog['Pages'] ?? null);
            if ($pagesRoot !== null) {
                $pages = $this->pageObjectReferencesFromTree($pagesRoot['object'], $pagesRoot['generation'], $objects);
                if ($pages !== []) {
                    return $pages;
                }
            }
        }

        $pages = [];
        foreach ($this->objectsByGeneration as $objectNumber => $generations) {
            foreach ($generations as $generation => $value) {
                $dict = $this->dictionaryItems($value);
                if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Page') {
                    $pages[] = ['object' => $objectNumber, 'generation' => $generation];
                }
            }
        }

        if ($pages !== []) {
            return $pages;
        }

        foreach ($objects as $objectNumber => $value) {
            $dict = $this->dictionaryItems($value);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Page') {
                $pages[] = ['object' => $objectNumber, 'generation' => 0];
            }
        }

        return $pages;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<string, true> $seen
     * @return list<array{object: int, generation: int}>
     */
    private function pageObjectReferencesFromTree(int $objectNumber, int $generation, array $objects, array $seen = []): array
    {
        $key = $this->referenceKey($objectNumber, $generation);
        if (isset($seen[$key])) {
            return [];
        }
        $seen[$key] = true;

        $dict = $this->resolveDictionary($this->refValue($objectNumber, $generation));
        if ($dict === null) {
            return [];
        }

        if ($this->nameValue($dict['Type'] ?? null) === 'Page') {
            return [['object' => $objectNumber, 'generation' => $generation]];
        }

        $kids = $this->resolveArray($dict['Kids'] ?? null);
        if ($kids === null) {
            return [];
        }

        $pages = [];
        foreach ($kids as $kid) {
            $kidReference = $this->referenceObject($kid);
            if ($kidReference === null) {
                continue;
            }

            foreach ($this->pageObjectReferencesFromTree($kidReference['object'], $kidReference['generation'], $objects, $seen) as $pageReference) {
                $pages[] = $pageReference;
            }
        }

        return $pages;
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<int, mixed> $objects
     * @return array<string, mixed>
     */
    private function destinationMap(array $catalog, array $objects): array
    {
        $destinations = [];

        $legacyDests = $this->resolveDictionary($catalog['Dests'] ?? null);
        if ($legacyDests !== null) {
            foreach ($legacyDests as $name => $destination) {
                if ($this->destinationValueAllowedForMap($destination)) {
                    $destinations[$name] = $destination;
                }
            }
        }

        $names = $this->resolveDictionary($catalog['Names'] ?? null);
        $nameTreeRoot = $names === null ? null : $this->resolveDictionary($names['Dests'] ?? null);
        if ($nameTreeRoot !== null) {
            $this->collectNameTreeDestinations($nameTreeRoot, $destinations);
        }

        return $destinations;
    }

    /**
     * @param array<string, mixed> $catalog
     */
    private function catalogUriBase(array $catalog): ?string
    {
        $uriDictionary = $this->resolveDictionary($catalog['URI'] ?? null);
        if ($uriDictionary === null) {
            return null;
        }

        $base = $this->stringOrNameValue($this->resolveValue($uriDictionary['Base'] ?? null));
        if ($base === null) {
            return null;
        }

        $base = trim($base);
        $scheme = $this->uriScheme($base);
        if ($base === '' || $scheme === null || !$this->isSafeUri($base)) {
            return null;
        }

        return in_array($scheme, ['http', 'https', 'ftp'], true) ? $base : null;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seen
     * @param array{lower: string, upper: string}|null $inheritedLimits
     */
    private function collectNameTreeDestinations(
        array $node,
        array &$destinations,
        array $seen = [],
        ?array $inheritedLimits = null,
        int $depth = 0
    ): void {
        if ($depth > 20) {
            return;
        }

        $limits = $this->nameTreeEffectiveLimits($node, $inheritedLimits);
        $kids = $this->resolveArray($node['Kids'] ?? null) ?? [];
        $names = $this->resolveArray($node['Names'] ?? null);
        if ($kids === [] && $names !== null) {
            $entryLimits = $this->nameTreeLimitsMatchAnyPairKey($names, $limits)
                ? $limits
                : $inheritedLimits;

            for ($index = 0, $count = count($names); $index + 1 < $count; $index += 2) {
                $name = $this->stringValue($this->resolveValue($names[$index]));
                if (
                    $name !== null
                    && $name !== ''
                    && $this->nameTreeNameWithinLimits($name, $entryLimits)
                    && $this->destinationValueAllowedForMap($names[$index + 1])
                ) {
                    $destinations[$name] = $names[$index + 1];
                }
            }
        }

        if ($kids === []) {
            return;
        }

        foreach ($kids as $kid) {
            $reference = $this->referenceObject($kid);
            if ($reference === null) {
                continue;
            }

            $seenKey = $reference['object'] . ':' . $reference['generation'];
            if (isset($seen[$seenKey])) {
                continue;
            }
            $seen[$seenKey] = true;

            $child = $this->resolveDictionary($kid);
            if ($child !== null) {
                $this->collectNameTreeDestinations($child, $destinations, $seen, $limits, $depth + 1);
            }
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param array{lower: string, upper: string}|null $inheritedLimits
     * @return array{lower: string, upper: string}|null
     */
    private function nameTreeEffectiveLimits(array $node, ?array $inheritedLimits): ?array
    {
        $nodeLimits = $this->nameTreeNodeLimits($node);
        if ($nodeLimits === null) {
            return $inheritedLimits;
        }

        if ($inheritedLimits === null) {
            return $nodeLimits;
        }

        $limits = [
            'lower' => strcmp($nodeLimits['lower'], $inheritedLimits['lower']) < 0
                ? $inheritedLimits['lower']
                : $nodeLimits['lower'],
            'upper' => strcmp($nodeLimits['upper'], $inheritedLimits['upper']) > 0
                ? $inheritedLimits['upper']
                : $nodeLimits['upper'],
        ];

        return strcmp($limits['lower'], $limits['upper']) > 0 ? $inheritedLimits : $limits;
    }

    /**
     * @param array<string, mixed> $node
     * @return array{lower: string, upper: string}|null
     */
    private function nameTreeNodeLimits(array $node): ?array
    {
        $limits = $this->resolveArray($node['Limits'] ?? null);
        if ($limits === null || count($limits) < 2) {
            return null;
        }

        $lower = $this->stringValue($this->resolveValue($limits[0]));
        $upper = $this->stringValue($this->resolveValue($limits[1]));
        if ($lower === null || $upper === null || $lower === '' || $upper === '') {
            return null;
        }
        if (strcmp($lower, $upper) > 0) {
            return null;
        }

        return [
            'lower' => $lower,
            'upper' => $upper,
        ];
    }

    /**
     * @param array{lower: string, upper: string}|null $limits
     */
    private function nameTreeNameWithinLimits(string $name, ?array $limits): bool
    {
        if ($limits === null) {
            return true;
        }

        return strcmp($limits['lower'], $limits['upper']) <= 0
            && strcmp($name, $limits['lower']) >= 0
            && strcmp($name, $limits['upper']) <= 0;
    }

    /**
     * @param list<mixed> $items
     * @param array{lower: string, upper: string}|null $limits
     */
    private function nameTreeLimitsMatchAnyPairKey(array $items, ?array $limits): bool
    {
        if ($limits === null || $items === []) {
            return true;
        }

        for ($index = 0, $count = count($items); $index + 1 < $count; $index += 2) {
            $name = $this->stringValue($this->resolveValue($items[$index]));
            if ($name !== null && $this->nameTreeNameWithinLimits($name, $limits)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $tokens = [];
        $length = strlen($value);
        for ($index = 0; $index < $length;) {
            $char = $value[$index];
            if (ctype_space($char)) {
                $index++;
                continue;
            }

            if ($char === '%') {
                while ($index < $length && $value[$index] !== "\n" && $value[$index] !== "\r") {
                    $index++;
                }
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<' || $pair === '>>') {
                $tokens[] = $pair;
                $index += 2;
                continue;
            }

            if ($char === '[' || $char === ']') {
                $tokens[] = $char;
                $index++;
                continue;
            }

            if ($char === '(') {
                $tokens[] = $this->readLiteralToken($value, $index);
                continue;
            }

            if ($char === '<') {
                $tokens[] = $this->readHexToken($value, $index);
                continue;
            }

            if ($char === '/') {
                $start = $index;
                $index++;
                while ($index < $length && !$this->isDelimiter($value[$index])) {
                    $index++;
                }
                $tokens[] = substr($value, $start, $index - $start);
                continue;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($value[$index])) {
                $index++;
            }
            $tokens[] = substr($value, $start, $index - $start);
        }

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    /**
     * @param list<string> $tokens
     */
    private function parseValue(array $tokens, int &$index): mixed
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return null;
        }

        if ($token === '<<') {
            $index++;
            $items = [];
            while (($tokens[$index] ?? null) !== null && $tokens[$index] !== '>>') {
                $key = $tokens[$index] ?? '';
                $index++;
                if (!is_string($key) || !str_starts_with($key, '/')) {
                    continue;
                }

                $items[$this->decodePdfName(substr($key, 1))] = $this->parseValue($tokens, $index);
            }
            if (($tokens[$index] ?? null) === '>>') {
                $index++;
            }

            return ['pdfType' => 'dict', 'items' => $items];
        }

        if ($token === '[') {
            $index++;
            $items = [];
            while (($tokens[$index] ?? null) !== null && $tokens[$index] !== ']') {
                $items[] = $this->parseValue($tokens, $index);
            }
            if (($tokens[$index] ?? null) === ']') {
                $index++;
            }

            return ['pdfType' => 'array', 'items' => $items];
        }

        if (
            preg_match('/^[+-]?\d+$/', $token) === 1
            && preg_match('/^[+-]?\d+$/', (string) ($tokens[$index + 1] ?? '')) === 1
            && ($tokens[$index + 2] ?? null) === 'R'
        ) {
            $generation = (int) $tokens[$index + 1];
            $index += 3;

            return [
                'pdfType' => 'ref',
                'object' => (int) $token,
                'generation' => $generation,
            ];
        }

        $index++;
        if (str_starts_with($token, '/')) {
            return ['pdfType' => 'name', 'value' => $this->decodePdfName(substr($token, 1))];
        }

        if (str_starts_with($token, '(')) {
            return ['pdfType' => 'string', 'value' => $this->decodeLiteralString($token)];
        }

        if (str_starts_with($token, '<')) {
            return ['pdfType' => 'string', 'value' => $this->decodeHexString($token)];
        }

        if ($token === 'null') {
            return null;
        }

        if ($token === 'true' || $token === 'false') {
            return $token === 'true';
        }

        if (preg_match('/^[+-]?\d+$/', $token) === 1) {
            return (int) $token;
        }

        if (is_numeric($token)) {
            return (float) $token;
        }

        return ['pdfType' => 'keyword', 'value' => $token];
    }

    private function readLiteralToken(string $value, int &$index): string
    {
        $start = $index;
        $depth = 0;
        $length = strlen($value);
        while ($index < $length) {
            $char = $value[$index];
            if ($char === '\\') {
                $index += 2;
                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $index++;
                    break;
                }
            }
            $index++;
        }

        return substr($value, $start, $index - $start);
    }

    private function readHexToken(string $value, int &$index): string
    {
        $start = $index;
        $length = strlen($value);
        $index++;
        while ($index < $length && $value[$index] !== '>') {
            $index++;
        }
        if ($index < $length) {
            $index++;
        }

        return substr($value, $start, $index - $start);
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}/%', $char);
    }

    private function resolveValue(mixed $value, int $depth = 0): mixed
    {
        $reference = $this->referenceObject($value);
        if ($reference === null || $depth > 20) {
            return $value;
        }

        $objectNumber = $reference['object'];
        $generation = $reference['generation'];
        if (array_key_exists($generation, $this->objectsByGeneration[$objectNumber] ?? [])) {
            return $this->resolveValue($this->objectsByGeneration[$objectNumber][$generation], $depth + 1);
        }

        if ($generation === 0 && array_key_exists($objectNumber, $this->objects)) {
            return $this->resolveValue($this->objects[$objectNumber], $depth + 1);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDictionary(mixed $value): ?array
    {
        return $this->dictionaryItems($this->resolveValue($value));
    }

    /**
     * @return list<mixed>|null
     */
    private function resolveArray(mixed $value): ?array
    {
        return $this->arrayItems($this->resolveValue($value));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dictionaryItems(mixed $value): ?array
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'dict' && is_array($value['items'] ?? null)
            ? $value['items']
            : null;
    }

    /**
     * @return list<mixed>|null
     */
    private function arrayItems(mixed $value): ?array
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'array' && is_array($value['items'] ?? null)
            ? array_values($value['items'])
            : null;
    }

    private function referenceObjectNumber(mixed $value): ?int
    {
        $reference = $this->referenceObject($value);
        return $reference === null ? null : $reference['object'];
    }

    /**
     * @return array{object: int, generation: int}|null
     */
    private function referenceObject(mixed $value): ?array
    {
        if (!is_array($value) || ($value['pdfType'] ?? null) !== 'ref' || !is_int($value['object'] ?? null)) {
            return null;
        }

        return [
            'object' => $value['object'],
            'generation' => is_int($value['generation'] ?? null) ? $value['generation'] : 0,
        ];
    }

    /**
     * @return array{pdfType: string, object: int, generation: int}
     */
    private function refValue(int $objectNumber, int $generation = 0): array
    {
        return ['pdfType' => 'ref', 'object' => $objectNumber, 'generation' => $generation];
    }

    /**
     * @param array{object: int, generation: int} $reference
     */
    private function pageIndexForReference(array $reference): ?int
    {
        return $this->pageIndexesByReference[$this->referenceKey($reference['object'], $reference['generation'])] ?? null;
    }

    private function referenceKey(int $objectNumber, int $generation): string
    {
        return $objectNumber . ':' . $generation;
    }

    private function nameValue(mixed $value): ?string
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'name' && is_string($value['value'] ?? null)
            ? $value['value']
            : null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'string' && is_string($value['value'] ?? null)
            ? $value['value']
            : null;
    }

    private function stringOrNameValue(mixed $value): ?string
    {
        $string = $this->stringValue($value);
        if ($string !== null) {
            return $string;
        }

        return $this->nameValue($value);
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr(hexdec($match[1]));
        }, $name) ?? $name;
    }

    private function isSafeUri(string $uri): bool
    {
        $trimmed = trim($uri);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/[\x00-\x20\x7F]/', $uri) === 1) {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed, $match) === 1) {
            return in_array(strtolower(rtrim($match[0], ':')), ['http', 'https', 'mailto', 'ftp'], true);
        }

        return str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/') || str_starts_with($trimmed, './') || str_starts_with($trimmed, '../');
    }

    /**
     * @return array{uri: string, is_safe_uri: bool, metadata: array<string, mixed>}
     */
    private function uriReview(string $rawUri): array
    {
        $relative = $this->isRelativeUri($rawUri);
        $resolved = $relative && $this->uriBase !== null
            ? $this->resolveRelativeUriAgainstBase($rawUri, $this->uriBase)
            : null;
        $uri = $resolved ?? $rawUri;
        $metadata = [
            'uri_relative' => $relative,
            'uri_resolved_from_base' => $resolved !== null,
        ];

        if ($resolved !== null) {
            $metadata['raw_uri'] = $rawUri;
            $metadata['uri_base'] = $this->uriBase;
        }

        return [
            'uri' => $uri,
            'is_safe_uri' => $this->isSafeUri($uri),
            'metadata' => $metadata,
        ];
    }

    private function isRelativeUri(string $uri): bool
    {
        $trimmed = trim($uri);
        if ($trimmed === '' || preg_match('/[\x00-\x20\x7F]/', $uri) === 1) {
            return false;
        }

        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed) !== 1
            && !str_starts_with($trimmed, '//');
    }

    private function resolveRelativeUriAgainstBase(string $relativeUri, string $baseUri): ?string
    {
        $base = parse_url($baseUri);
        if (!is_array($base) || !is_string($base['scheme'] ?? null) || !is_string($base['host'] ?? null)) {
            return null;
        }

        $relative = parse_url($relativeUri);
        if ($relative === false) {
            return null;
        }

        $scheme = strtolower($base['scheme']);
        if (!in_array($scheme, ['http', 'https', 'ftp'], true)) {
            return null;
        }

        $basePath = is_string($base['path'] ?? null) && $base['path'] !== '' ? $base['path'] : '/';
        $baseQuery = is_string($base['query'] ?? null) ? $base['query'] : null;
        $relativePath = is_string($relative['path'] ?? null) ? $relative['path'] : '';
        $path = $basePath;
        $query = null;
        $fragment = is_string($relative['fragment'] ?? null) ? $relative['fragment'] : null;

        if ($relativePath !== '') {
            $baseDirectory = str_ends_with($basePath, '/')
                ? $basePath
                : substr($basePath, 0, (int) strrpos($basePath, '/') + 1);
            $path = str_starts_with($relativePath, '/')
                ? $relativePath
                : $baseDirectory . $relativePath;
            $query = is_string($relative['query'] ?? null) ? $relative['query'] : null;
        } else {
            $query = is_string($relative['query'] ?? null) ? $relative['query'] : $baseQuery;
        }

        return $this->buildUri(
            $scheme,
            $base['host'],
            is_int($base['port'] ?? null) ? $base['port'] : null,
            $this->normalizeUriPath($path),
            $query,
            $fragment
        );
    }

    private function buildUri(string $scheme, string $host, ?int $port, string $path, ?string $query, ?string $fragment): string
    {
        $uri = $scheme . '://' . $host;
        if ($port !== null) {
            $uri .= ':' . $port;
        }

        $uri .= $path === '' || $path[0] !== '/' ? '/' . $path : $path;
        if ($query !== null && $query !== '') {
            $uri .= '?' . $query;
        }
        if ($fragment !== null && $fragment !== '') {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    private function normalizeUriPath(string $path): string
    {
        $absolute = str_starts_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);
        return ($absolute ? '/' : '') . $normalized;
    }

    private function uriScheme(?string $uri): ?string
    {
        if ($uri === null || preg_match('/^([a-z][a-z0-9+.-]*):/i', trim($uri), $match) !== 1) {
            return null;
        }

        return strtolower($match[1]);
    }

    private function decodeLiteralString(string $token): string
    {
        $bytes = substr($token, 1, -1);
        $decoded = '';
        $length = strlen($bytes);
        for ($index = 0; $index < $length; $index++) {
            $char = $bytes[$index];
            if ($char !== '\\') {
                $decoded .= $char;
                continue;
            }

            if ($index + 1 >= $length) {
                break;
            }
            $next = $bytes[++$index];
            if ($next === "\r" || $next === "\n") {
                if ($next === "\r" && ($bytes[$index + 1] ?? '') === "\n") {
                    $index++;
                }
                continue;
            }
            if (preg_match('/[0-7]/', $next) === 1) {
                $octal = $next;
                for ($count = 0; $count < 2 && preg_match('/[0-7]/', (string) ($bytes[$index + 1] ?? '')) === 1; $count++) {
                    $octal .= $bytes[++$index];
                }
                $decoded .= chr(octdec($octal) & 0xff);
                continue;
            }

            $decoded .= match ($next) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                default => $next,
            };
        }

        return $this->decodePdfStringBytes($decoded);
    }

    private function decodeHexString(string $token): string
    {
        $hex = preg_replace('/\s+/', '', substr($token, 1, -1));
        if ($hex === null || $hex === '' || preg_match('/^[\da-fA-F]+$/', $hex) !== 1) {
            return '';
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $bytes = hex2bin($hex);
        return $bytes === false ? '' : $this->decodePdfStringBytes($bytes);
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        if (str_starts_with($bytes, "\xFE\xFF")) {
            $utf16 = substr($bytes, 2);
            if (strlen($utf16) % 2 !== 0 || !mb_check_encoding($utf16, 'UTF-16BE')) {
                return '';
            }

            $decoded = @iconv('UTF-16BE', 'UTF-8//IGNORE', $utf16);
            return $decoded === false ? '' : $decoded;
        }

        if (str_starts_with($bytes, "\xFF\xFE")) {
            $utf16 = substr($bytes, 2);
            if (strlen($utf16) % 2 !== 0 || !mb_check_encoding($utf16, 'UTF-16LE')) {
                return '';
            }

            $decoded = @iconv('UTF-16LE', 'UTF-8//IGNORE', $utf16);
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }
}
