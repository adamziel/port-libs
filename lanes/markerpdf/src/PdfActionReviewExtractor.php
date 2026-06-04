<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfActionReviewExtractor
{
    private const MAX_ACTION_CHAIN_DEPTH = 20;

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

    /** @var array<int, int> */
    private array $pageIndexes;

    /** @var array<string, mixed> */
    private array $destinations;

    public function __construct(string $pdfBytes)
    {
        $this->objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($this->objects);

        $pageObjectNumbers = $this->orderedPageObjectNumbers($this->objects, $catalog);
        $this->pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $this->pageIndexes[$objectNumber] = $index;
        }

        $this->destinations = $catalog === null ? [] : $this->destinationMap($catalog, $this->objects);
    }

    /**
     * @return array{
     *     actions: list<array<string, mixed>>,
     *     additional_actions: list<array<string, mixed>>,
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
                'executes_actions_on_import' => false,
            ];
        }

        $seen = [];
        $actions = [];
        if (array_key_exists('A', $dict)) {
            $actions = $this->reviewActionsFromValue($dict['A'], $seen);
        } elseif (array_key_exists('Dest', $dict)) {
            $action = $this->localDestinationReview($dict['Dest']);
            if ($action !== null) {
                $actions[] = $action;
            }
        }

        return [
            'actions' => $actions,
            'additional_actions' => $this->additionalActionMetadata($dict['AA'] ?? null),
            'executes_actions_on_import' => false,
        ];
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

        $type = $this->nameValue($dict['S'] ?? null);
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

            $isSafeUri = $this->isSafeUri($uri);

            return $this->reviewAction(
                'URI',
                $isSafeUri ? 'review-uri' : 'blocked-unsafe-uri',
                null,
                null,
                null,
                [],
                [],
                $uri,
                null,
                null,
                $isSafeUri
            );
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
                null,
                [],
                [],
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
     * @return array{file: string, destination: string|null, page: int|null, new_window: bool|null}|null
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
            'new_window' => is_bool($action['NewWindow'] ?? null) ? $action['NewWindow'] : null,
        ];
    }

    /**
     * @return array{destination: string|null, page: int|null}|null
     */
    private function remoteDestinationValue(mixed $value): ?array
    {
        $resolved = $this->resolveValue($value);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            return ['destination' => $name, 'page' => null];
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
            return ['destination' => null, 'page' => $first];
        }

        $name = $this->stringOrNameValue($first);
        if ($name !== null) {
            return ['destination' => $name, 'page' => null];
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

    /**
     * @return array{page: int, destination: string|null, view_mode: string|null, view_position: list<float|null>, view_parameters: array<string, float|null>}|null
     */
    private function destinationViewDetails(mixed $destination, ?string $destinationName = null, array $seenNames = []): ?array
    {
        $pageObjectNumber = $this->referenceObjectNumber($destination);
        if ($pageObjectNumber !== null && isset($this->pageIndexes[$pageObjectNumber])) {
            return [
                'page' => $this->pageIndexes[$pageObjectNumber],
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
        $pageObjectNumber = $this->referenceObjectNumber($value);
        if ($pageObjectNumber !== null) {
            return $this->pageIndexes[$pageObjectNumber] ?? null;
        }

        $resolved = $this->resolveValue($value);
        $pageObjectNumber = $this->referenceObjectNumber($resolved);
        if ($pageObjectNumber !== null) {
            return $this->pageIndexes[$pageObjectNumber] ?? null;
        }

        if (is_int($resolved) && $resolved >= 0) {
            return $resolved;
        }

        return $this->destinationViewDetails($resolved)['page'] ?? null;
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
     * @return list<int>
     */
    private function orderedPageObjectNumbers(array $objects, ?array $catalog): array
    {
        if ($catalog !== null) {
            $pagesRoot = $this->referenceObjectNumber($catalog['Pages'] ?? null);
            if ($pagesRoot !== null) {
                $pages = $this->pageObjectNumbersFromTree($pagesRoot, $objects);
                if ($pages !== []) {
                    return $pages;
                }
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $value) {
            $dict = $this->dictionaryItems($value);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Page') {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }
        $seen[$objectNumber] = true;

        $dict = $this->resolveDictionary($this->refValue($objectNumber));
        if ($dict === null) {
            return [];
        }

        if ($this->nameValue($dict['Type'] ?? null) === 'Page') {
            return [$objectNumber];
        }

        $kids = $this->resolveArray($dict['Kids'] ?? null);
        if ($kids === null) {
            return [];
        }

        $pages = [];
        foreach ($kids as $kid) {
            $kidObjectNumber = $this->referenceObjectNumber($kid);
            if ($kidObjectNumber === null) {
                continue;
            }

            foreach ($this->pageObjectNumbersFromTree($kidObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
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
                $destinations[$name] = $destination;
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
     * @param array<string, mixed> $node
     * @param array<string, mixed> $destinations
     * @param array<int, true> $seen
     */
    private function collectNameTreeDestinations(array $node, array &$destinations, array $seen = []): void
    {
        $names = $this->resolveArray($node['Names'] ?? null);
        if ($names !== null) {
            for ($index = 0, $count = count($names); $index + 1 < $count; $index += 2) {
                $name = $this->stringOrNameValue($this->resolveValue($names[$index]));
                if ($name !== null) {
                    $destinations[$name] = $names[$index + 1];
                }
            }
        }

        $kids = $this->resolveArray($node['Kids'] ?? null);
        if ($kids === null) {
            return;
        }

        foreach ($kids as $kid) {
            $objectNumber = $this->referenceObjectNumber($kid);
            if ($objectNumber !== null) {
                if (isset($seen[$objectNumber])) {
                    continue;
                }
                $seen[$objectNumber] = true;
            }

            $child = $this->resolveDictionary($kid);
            if ($child !== null) {
                $this->collectNameTreeDestinations($child, $destinations, $seen);
            }
        }
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
    private function refValue(int $objectNumber): array
    {
        return ['pdfType' => 'ref', 'object' => $objectNumber, 'generation' => 0];
    }

    private function nameValue(mixed $value): ?string
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'name' && is_string($value['value'] ?? null)
            ? $value['value']
            : null;
    }

    private function stringOrNameValue(mixed $value): ?string
    {
        if (is_array($value) && ($value['pdfType'] ?? null) === 'string' && is_string($value['value'] ?? null)) {
            return $value['value'];
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

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed, $match) === 1) {
            return in_array(strtolower(rtrim($match[0], ':')), ['http', 'https', 'mailto', 'ftp'], true);
        }

        return str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/') || str_starts_with($trimmed, './') || str_starts_with($trimmed, '../');
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
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        if (str_starts_with($bytes, "\xFF\xFE")) {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }
}
