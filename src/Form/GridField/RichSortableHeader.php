<?php

namespace Terraformers\RichFilterHeader\Form\GridField;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridState_Data;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\Sortable;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

/**
 * Allow the filter expand button to be displayed in the sortable header component
 */
class RichSortableHeader extends GridFieldSortableHeader
{
    /**
     * Code of this method is copied from the parent method except of the <INSERT_FILTER_BUTTON> section
     *
     * @param mixed $gridField
     * @return mixed
     */
    public function getHTMLFragments(mixed $gridField): mixed
    {
        /** @var RichFilterHeader $filter */
        $filter = $gridField
            ->getConfig()
            ->getComponentByType(RichFilterHeader::class);

        if (!$filter) {
            // We don't have a matching rich filter header component set up,
            // so we will fall back to the default behaviour
            return parent::getHTMLFragments($gridField);
        }

        $list = $gridField->getList();

        if (!$this->checkDataType($list)) {
            return null;
        }

        /** @var Sortable $list */
        $forTemplate = new ArrayData([]);
        $forTemplate->Fields = new ArrayList;

        $state = $this->getState($gridField);
        $columns = $gridField->getColumns();
        $currentColumn = 0;

        $schema = DataObject::getSchema();

        foreach ($columns as $columnField) {
            $currentColumn++;
            $metadata = $gridField->getColumnMetadata($columnField);
            $fieldName = str_replace('.', '-', $columnField ?? '');
            $title = $metadata['title'];

            if (isset($this->fieldSorting[$columnField]) && $this->fieldSorting[$columnField]) {
                $columnField = $this->fieldSorting[$columnField];
            }

            $allowSort = ($title && $list->canSortBy($columnField));

            if (!$allowSort && strpos($columnField ?? '', '.') !== false) {
                // we have a relation column with dot notation
                // @see DataObject::relField for approximation
                $parts = explode('.', $columnField ?? '');
                $tmpItem = singleton($list->dataClass());

                for ($idx = 0; $idx < sizeof($parts ?? []); $idx++) {
                    $methodName = $parts[$idx];
                    if ($tmpItem instanceof SS_List) {
                        // It's impossible to sort on a HasManyList/ManyManyList
                        break;
                    } elseif ($tmpItem && method_exists($tmpItem, 'hasMethod') && $tmpItem->hasMethod($methodName)) {
                        // The part is a relation name, so get the object/list from it
                        $tmpItem = $tmpItem->$methodName();
                    } elseif ($tmpItem instanceof DataObject
                        && $schema->fieldSpec($tmpItem, $methodName, DataObjectSchema::DB_ONLY)
                    ) {
                        // Else, if we've found a database field at the end of the chain, we can sort on it.
                        // If a method is applied further to this field (E.g. 'Cost.Currency') then don't try to sort.
                        $allowSort = $idx === sizeof($parts ?? []) - 1;
                        break;
                    } else {
                        // If neither method nor field, then unable to sort
                        break;
                    }
                }
            }

            if ($allowSort) {
                $dir = 'asc';
                if ($state->SortColumn(null) == $columnField && $state->SortDirection('asc') == 'asc') {
                    $dir = 'desc';
                }

                $field = GridField_FormAction::create(
                    $gridField,
                    'SetOrder' . $fieldName,
                    $title,
                    "sort$dir",
                    ['SortColumn' => $columnField]
                )->addExtraClass('grid-field__sort');

                if ($state->SortColumn(null) == $columnField) {
                    $field->addExtraClass('ss-gridfield-sorted');

                    if ($state->SortDirection('asc') == 'asc') {
                        $field->addExtraClass('ss-gridfield-sorted-asc');
                    } else {
                        $field->addExtraClass('ss-gridfield-sorted-desc');
                    }
                }
            } else {
                // start <INSERT_FILTER_BUTTON>
                $sortActionFieldContents = $currentColumn == count($columns ?? []) && $filter->canFilterAnyColumns($gridField)
                    ? sprintf(
                        '<button type="button" name="showFilter" aria-label="%1$s" title="%1$s"' .
                        ' class="btn btn-secondary font-icon-search btn--no-text btn--icon-large grid-field__filter-open"></button>',
                        _t('SilverStripe\\Forms\\GridField\\GridField.OpenFilter', "Open search and filter")
                    )
                    : '<span class="non-sortable">' . $title . '</span>';
                $field = LiteralField::create($fieldName, $sortActionFieldContents);
                // end <INSERT_FILTER_BUTTON>
            }

            $forTemplate->Fields->push($field);
        }

        $template = SSViewer::get_templates_by_class($this, '_Row', GridFieldSortableHeader::class);

        return [
            'header' => $forTemplate->renderWith($template),
        ];
    }

    /**
     * Copied from parent without any change due to the method being private
     *
     * @param GridField $gridField
     * @return GridState_Data
     */
    private function getState(GridField $gridField): GridState_Data
    {
        return $gridField->State->GridFieldSortableHeader;
    }
}
