<?php

/**
 * Class GridFieldRichFilterHeaderRequestExtension
 *
 * This extension allows to process FormField actions of fields that are contained within the RichFilterHeader
 * and are identified by a composite field name
 *
 * @package Terraformers\RichFilterHeader\Extension
 */
class GridFieldRichFilterHeaderRequestExtension extends Extension
{
    /**
     * @config
     * @var array
     */
    private static $allowed_actions = [
        'handleFieldComposite',
    ];

    /**
     * @config
     * @var array
     */
    private static $url_handlers = [
        'field/$FieldName!' => 'handleFieldComposite',
    ];

    /**
     * @param FieldList $fields
     * @param string    $fieldName
     * @return null|FormField
     */
    protected function findField(FieldList $fields, $fieldName)
    {
        // try to find the field by name first
        $field = $fields->dataFieldByName($fieldName);
        if (!empty($field)) {
            return $field;
        }

        // falling back to fieldByName, e.g. for getting tabs
        $field = $fields->fieldByName($fieldName);
        if (!empty($field)) {
            return $field;
        }

        return null;
    }

    /**
     * @param $request
     * @return FormField
     */
    public function handleFieldComposite($request)
    {
        $owner = $this->owner;

        // perform standard field lookup
        $field = $owner->handleField($request);
        if (!empty($field)) {
            return $field;
        }

        $fields = $owner->form->Fields();
        $fieldName = $request->param('FieldName');

        // try to find a field contained within the RichFilterHeader component
        $gridFieldData = RichFilterHeader::parseCompositeFieldName($fieldName);
        if (empty($gridFieldData)) {
            return null;
        }

        $gridField = $this->findField($fields, $gridFieldData['grid_field']);
        if (empty($gridField) || !($gridField instanceof GridField)) {
            return null;
        }

        $filterHeader = $gridField->getConfig()->getComponentByType(RichFilterHeader::class);
        if (empty($filterHeader)) {
            return null;
        }

        /** @var RichFilterHeader $filterHeader */
        $field = $filterHeader->getFilterField($gridFieldData['child_field']);
        if (empty($field)) {
            return null;
        }

        return $field;
    }
}
