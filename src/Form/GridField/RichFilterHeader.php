<?php

namespace Terraformers\RichFilterHeader\Form\GridField;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridState_Data;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Filterable;
use SilverStripe\ORM\Filters\SearchFilter;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

/**
 * filter header with customisable filter fields and filters
 * fields that use XHR are supported
 *
 * REQUIREMENTS:
 *
 * table header component needs to be present in the GridField (for example GridFieldSortableHeader)
 * the last column of the table needs to have a vacant header cell so the filter widget could be displayed there
 * for example you can't have the last column with sorting header widget and filter widget at the same time
 *
 * If GridFieldPaginator is present, this filter component needs to be added BEFORE pagination component
 *
 * FILTER CONFIG
 *
 * Filter configuration that dictates what will filters will be displayed,
 * how GridField column names will be mapped to field names and what data filtering functionality will be used
 * if left unspecified it falls back to DataObject searchable_fields and then to summary_fields
 * note that searchable_fields and summary_fields have slightly different syntax in some cases
 * so manual setup on the GridField may be necessary
 *
 * @see DataObject::$searchable_fields
 * @see DataObject::$summary_fields
 *
 * Supported syntax variants:
 *
 * Simple whitelisting - columns that match the column name will display filters in the table header
 *
 * <code>
 *  [
 *     'field_name',
 *     'Title',
 *  ];
 * </code>
 *
 * Column name to field name mapping - this is used when column name is a getter function,
 * relation lookup or data formatting
 *
 * <code>
 *  [
 *     'column_name' => 'field_name',
 *     'getTitleSummary' => 'Title',
 *     'City.Name' => 'City',
 *     'Expires.Nice' => 'Expires',
 *  ];
 * </code>
 *
 * Complex syntax - column name wth field mapping and basic filter
 *
 * <code>
 *  [
 *    'column_name' => [
 *      'title' => 'field_name',
 *      'filter' => 'filter_type',
 *    ],
 *    'Organisation.ZipCode' => [
 *      'title' => 'Organisation ZIP',
 *      'filter' => 'ExactMatchFilter',
 *    ],
 *  ];
 * </code>
 *
 * basic filter reference:
 * https://docs.silverstripe.org/en/4/developer_guides/model/searchfilters/ *
 *
 * FILTER FIELDS
 *
 * TextField is used as a default field for filters however this may be too crude in some situations
 * This configuration allows addition of fields of any type like DropdownField or AutocompleteField
 *
 * configuration format:
 *
 * 'field_name' => 'FormField'
 *
 * for example if we want to use Dropdown field configuration below has to be used:
 *
 * ->setFilterFields([
 *  'City' => DropdownField::create('', '', $cities),
 * ])
 *
 * Note that Title and Name of the field can be left empty as they are not used (Title) or are auto-populated (Name)
 *
 * FILTER METHODS
 *
 * Partial match filter is used by default but in some situations this can't be used as other filters are required
 * if filter method is specified it always overrides basic filter
 *
 * configuration format:
 *
 * 'field_name' => 'filter_method' (Closure or string)
 *
 * filter method can be either a string which identifies one of the filter methods that are available
 * this component comes 'AllKeywordsFilter' and 'ManyManyRelationFilter', for example
 *
 * ->setFilterMethods([
 *    'Label' => RichFilterHeader::FILTER_ALL_KEYWORDS,
 * ]);
 *
 * ->setFilterMethods([
 *    'Categories' => RichFilterHeader::FILTER_MANY_MANY_RELATION,
 * ]);
 *
 *
 * alternatively a custom filter method can be specified
 * for example we may want to filter by multiple fields
 *
 * ->setFilterMethods([
 *   'Title' => function (DataList $list, $name, $value) {
 *     return $list->filterAny([
 *         'Title:PartialMatch' => $value,
 *         'Caption:PartialMatch' => $value,
 *         'Credit:PartialMatch' => $value,
 *     ]);
 *   },
 * ])
 *
 * this is a great way to cover edge cases as the implementation of the filter is completely up to the developer
 */
class RichFilterHeader extends GridFieldFilterHeader
{
    use Configurable;

    // predefined filter methods
    const FILTER_ALL_KEYWORDS = 'filter_all_keywords';
    const FILTER_MANY_MANY_RELATION = 'filter_many_many_relation';

    /**
     * @config
     * @var string
     */
    private static $field_name_encode = 'filter[%s][%s]';

    /**
     * @config
     * @var string
     */
    private static $field_name_decode = '/^filter\[([^]]+)\]\[([^]]+)\]$/';

    /**
     * @var string
     */
    protected $dataClass = '';

    /**
     * Filter configuration uses syntax compatible with searchable_fields and summary_fields
     *
     * @var array
     */
    protected $filter_config = [];

    /**
     * Custom fields list - all custom fields are stored here
     *
     * configuration format:
     *
     * 'field_name' => 'FormField'
     *
     * @var array
     */
    protected $filter_fields = [];

    /**
     * Filter methods - filter callbacks can be specified per field or predefined filter methods can be chosen
     *
     * configuration format:
     *
     * 'field_name' => 'filter_method' (Closure or string)
     *
     * custom filter function can be specified to filter the list, see 'applyAllKeywordsFilter' filter function
     *
     * @var array
     */
    protected $filter_methods = [];

    /**
     * @param string $class
     */
    protected function setDataClass($class)
    {
        $this->dataClass = $class;
    }

    /**
     * @return array
     */
    protected function getFilterConfig()
    {
        // primary config
        if (!empty($this->filter_config)) {
            return $this->filter_config;
        }

        // fallback to Model configuration
        if (!empty($this->dataClass)) {
            $class = $this->dataClass;

            // fallback to searchable fields
            $data = Config::inst()->get($class, 'searchable_fields');
            if (!empty($data) && is_array($data)) {
                return $data;
            }

            // fallback to summary fields
            $data = Config::inst()->get($class, 'summary_fields');
            if (!empty($data) && is_array($data)) {
                return $data;
            }
        }

        return [];
    }

    /**
     * @param string $name
     * @param string|array $config
     * @return array
     */
    protected function parseFieldConfig($name, $config)
    {
        $data = [];
        if (is_array($config)) {
            // composite config
            $data['title'] = (array_key_exists('title', $config)) ? $config['title'] : $name;
            $data['filter'] = (array_key_exists('filter', $config)) ? $config['filter'] : 'PartialMatchFilter';
        } else {
            // simple config
            $data['title'] = $config;
            $data['filter'] = 'PartialMatchFilter';
        }

        return $data;
    }

    /**
     * @param string $name
     * @return array
     */
    protected function findFieldConfig($name)
    {
        $fields = $this->getFilterConfig();

        // match field by key
        if (array_key_exists($name, $fields)) {
            return $this->parseFieldConfig($name, $fields[$name]);
        }

        // match field by value
        if (in_array($name, $fields)) {
            return $this->parseFieldConfig($name, $name);
        }

        // match field by title
        foreach ($fields as $data) {
            if (is_array($data) && array_key_exists('title', $data) && $data['title'] === $name) {
                return $this->parseFieldConfig($name, $data);
            }
        }

        return [];
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function hasFilterMethod($field)
    {
        return array_key_exists($field, $this->filter_methods);
    }

    /**
     * @param GridField $gridField
     * @param string $name
     * @param string $value
     * @return FormField
     */
    protected function createField(GridField $gridField, $name, $value)
    {
        $fieldName = static::createCompositeFieldName($gridField->getName(), $name);
        if ($this->hasFilterField($name)) {
            // custom field
            $field = $this->getFilterField($name);
            $field->setName($fieldName);
            $field->setValue($value);
        } else {
            // default field
            $field = TextField::create($fieldName, '', $value);
        }

        // form needs to be set manually as this is not done by default
        // this is useful for fields that have actions and need to know their url
        $field->setForm($gridField->getForm());

        return $field;
    }

    /**
     * Search for items that contain all keywords
     *
     * @param Filterable $list
     * @param string $fieldName
     * @param string $value
     * @return Filterable
     */
    protected function applyAllKeywordsFilter(Filterable $list, $fieldName, $value)
    {
        $keywords = preg_split('/[\s,]+/', $value);
        foreach ($keywords as $keyword) {
            $list = $list->filter(["{$fieldName}:PartialMatch" => $keyword]);
        }

        return $list;
    }

    /**
     * Search for items via a many many relation
     *
     * @param DataList $list
     * @param string $relationName
     * @param string $value
     * @return DataList
     */
    protected function applyManyManyRelationFilter(DataList $list, $relationName, $value)
    {
        $columnName = null;
        $list = $list->applyRelation($relationName . '.ID', $columnName);

        return $list->where([$columnName => $value]);
    }

    /**
     * @param string $gridFieldName
     * @param string $childFieldName
     * @return string
     */
    public static function createCompositeFieldName($gridFieldName, $childFieldName)
    {
        $format = static::config()->get('field_name_encode');

        return sprintf($format, $gridFieldName, $childFieldName);
    }

    /**
     * @param string $name
     * @return array
     */
    public static function parseCompositeFieldName($name)
    {
        $format = static::config()->get('field_name_decode');

        $matches= [];
        preg_match($format, $name, $matches);

        if (isset($matches[1]) && $matches[2]) {
            return [
                'grid_field' => $matches[1],
                'child_field' => $matches[2],
            ];
        }

        return [];
    }

    /**
     * 'searchable_fields' and 'summary_fields' configuration formats are supported
     *
     * @see DataObject::$searchable_fields
     * @see DataObject::$summary_fields
     *
     * @param array $fields
     * @return $this
     */
    public function setFilterConfig(array $fields)
    {
        $this->filter_config = $fields;

        return $this;
    }

    /**
     * configuration format:
     *
     * 'field_name' => 'FormField'
     *
     * @param array $fields
     * @return $this
     */
    public function setFilterFields(array $fields)
    {
        $this->filter_fields = $fields;

        return $this;
    }

    /**
     * configuration format:
     *
     * 'field_name' => 'filter_specification' (Closure or string)
     *
     * @param array $fields
     * @return $this
     */
    public function setFilterMethods(array $fields)
    {
        $this->filter_methods = $fields;

        return $this;
    }

    /**
     * @param string $field
     * @return FormField|null
     */
    public function getFilterField($field)
    {
        if ($this->hasFilterField($field)) {
            return $this->filter_fields[$field];
        }

        return null;
    }

    /**
     * Returns whether this {@link GridField} has any columns to sort on at all.
     *
     * @param GridField $gridField
     * @return boolean
     */
    public function canFilterAnyColumns($gridField)
    {
        $list = $gridField->getList();

        if (!$this->checkDataType($list)) {
            return false;
        }

        $columns = $gridField->getColumns();
        foreach ($columns as $name) {
            $metadata = $gridField->getColumnMetadata($name);
            $title = $metadata['title'];

            $fieldConfig = $this->findFieldConfig($name);
            $name = (!empty($fieldConfig['title'])) ? $fieldConfig['title'] : $name;

            if ($title && !empty($fieldConfig) && ($list->canFilterBy($name) || $this->hasFilterMethod($name))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param GridField $gridField
     * @param SS_List $dataList
     * @return SS_List
     */
    public function getManipulatedData(GridField $gridField, SS_List $dataList)
    {
        if (!$this->checkDataType($dataList)) {
            return $dataList;
        }

        /** @var DataList|Filterable $dataList */
        $this->setDataClass($dataList->dataClass());

        /** @var GridState_Data $columns */
        $columns = $gridField->State->GridFieldFilterHeader->Columns(null);
        if (empty($columns)) {
            return $dataList;
        }

        $filterArguments = $columns->toArray();

        /** @var $dataListClone DataList */
        $dataListClone = clone($dataList);
        foreach ($filterArguments as $name => $value) {
            $fieldConfig = $this->findFieldConfig($name);
            if (empty($fieldConfig)) {
                continue;
            }

            $name = $fieldConfig['title'];

            if (($dataList->canFilterBy($name) || $this->hasFilterMethod($name)) && $value) {
                if ($this->hasFilterMethod($name)) {
                    // filter method configuration is available
                    $filter = $this->filter_methods[$name];

                    if ($filter instanceof \Closure) {
                        // custom filter method
                        $dataListClone = $filter($dataListClone, $name, $value);
                    } elseif ($filter === static::FILTER_ALL_KEYWORDS) {
                        $dataListClone = $this->applyAllKeywordsFilter($dataListClone, $name, $value);
                    } elseif ($filter === static::FILTER_MANY_MANY_RELATION) {
                        $dataListClone = $this->applyManyManyRelationFilter($dataListClone, $name, $value);
                    }
                } else {
                    // basic filter
                    /** @var SearchFilter $filter */
                    $filter = Injector::inst()->create($fieldConfig['filter'], $name);
                    if (empty($filter)) {
                        continue;
                    }

                    $filter->setModel($dataListClone->dataClass());
                    $filter->setValue($value);
                    $dataListClone = $dataListClone->alterDataQuery([$filter, 'apply']);
                }
            }
        }

        return $dataListClone;
    }

    /**
     * @param GridField $gridField
     * @param string $actionName
     * @param mixed $arguments
     * @param mixed $data
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if (!$this->checkDataType($gridField->getList())) {
            return;
        }

        /** @var DataList|Filterable $list */
        $list = $gridField->getList();
        $this->setDataClass($list->dataClass());

        $state = $gridField->State->GridFieldFilterHeader;
        if ($actionName === 'filter') {
            if (isset($data['filter'][$gridField->getName()])) {
                foreach ($data['filter'][$gridField->getName()] as $name => $value) {
                    /** @var $filterField FormField */
                    $filterField = $this->getFilterField($name);

                    // custom field
                    if (!is_null($filterField)) {
                        $filterField->setValue($value);
                    }

                    $state->Columns->{$name} = $value;
                }
            }
        } elseif ($actionName === 'reset') {
            $state->Columns = $state->Column instanceof GridState_Data
                // This is required since silverstripe/framework 4.8
                ? new GridState_Data()
                // Legacy compatibility case
                : null;

            // reset all custom fields
            foreach ($this->filter_fields as $field) {
                /** @var $field FormField */
                $field->setValue('');
            }
        }
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function hasFilterField($field)
    {
        return array_key_exists($field, $this->filter_fields);
    }

    /**
     * @param GridField $gridField
     * @return array|null
     */
    public function getHTMLFragments(mixed $gridField): mixed
    {
        $list = $gridField->getList();
        if (!$this->checkDataType($list)) {
            return null;
        }

        /** @var DataList|Filterable $list */
        $this->setDataClass($list->dataClass());

        $forTemplate = ArrayData::create([]);
        $forTemplate->Fields = ArrayList::create();

        $columns = $gridField->getColumns();
        $filterArguments = $gridField->State->GridFieldFilterHeader->Columns->toArray();
        $currentColumn = 0;
        $canFilter = false;

        foreach ($columns as $name) {
            $currentColumn++;
            $metadata = $gridField->getColumnMetadata($name);
            $title = $metadata['title'];
            $fields = new FieldGroup();

            $fieldConfig = $this->findFieldConfig($name);
            $name = (!empty($fieldConfig['title'])) ? $fieldConfig['title'] : $name;

            if ($title && !empty($fieldConfig) && ($list->canFilterBy($name) || $this->hasFilterMethod($name))) {
                $canFilter = true;

                $value = '';
                if (isset($filterArguments[$name])) {
                    $value = $filterArguments[$name];
                }
                $field = $this->createField($gridField, $name, $value);
                $field->addExtraClass('grid-field__sort-field');
                $field->addExtraClass('no-change-track');

                // add placeholder attribute only if it's not provided already
                if (empty($field->getAttribute('placeholder'))) {
                    $field->setAttribute(
                        'placeholder',
                        _t('SilverStripe\\Forms\\GridField\\GridField.FilterBy', 'Filter by ')
                        . _t('SilverStripe\\Forms\\GridField\\GridField.'.$metadata['title'], $metadata['title'])
                    );
                }

                $fields->push($field);
                $fields->push(
                    GridField_FormAction::create($gridField, 'reset', false, 'reset', null)
                        ->addExtraClass(
                            'btn font-icon-cancel btn-secondary btn--no-text ss-gridfield-button-reset'
                        )
                        ->setAttribute(
                            'title',
                            _t('SilverStripe\\Forms\\GridField\\GridField.ResetFilter', 'Reset')
                        )
                        ->setAttribute('id', 'action_reset_' . $gridField->getModelClass() . '_' . $name)
                );
            }

            if ($currentColumn == count($columns)) {
                $fields->push(
                    GridField_FormAction::create($gridField, 'filter', false, 'filter', null)
                        ->addExtraClass(
                            'btn font-icon-search btn--no-text btn--icon-large grid-field__filter-submit ss-gridfield-button-filter'
                        )
                        ->setAttribute(
                            'title',
                            _t('SilverStripe\\Forms\\GridField\\GridField.Filter', 'Filter')
                        )
                        ->setAttribute('id', 'action_filter_' . $gridField->getModelClass() . '_' . $name)
                );
                $fields->push(
                    GridField_FormAction::create($gridField, 'reset', false, 'reset', null)
                        ->addExtraClass(
                            'btn font-icon-cancel btn--no-text grid-field__filter-clear btn--icon-md ss-gridfield-button-close'
                        )
                        ->setAttribute(
                            'title',
                            _t('SilverStripe\\Forms\\GridField\\GridField.ResetFilter', 'Reset')
                        )
                        ->setAttribute('id', 'action_reset_' . $gridField->getModelClass() . '_' . $name)
                );
                $fields->addExtraClass('grid-field__filter-buttons');
                $fields->addExtraClass('no-change-track');
            }

            $forTemplate->Fields->push($fields);
        }

        if (!$canFilter) {
            return null;
        }

        $templates = SSViewer::get_templates_by_class($this, '_Row', parent::class);

        return [
            'header' => $forTemplate->renderWith($templates),
        ];
    }
}
