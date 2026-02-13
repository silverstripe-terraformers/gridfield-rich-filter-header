<?php

namespace Terraformers\RichFilterHeader\Extension;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\FormRequestHandler;
use SilverStripe\Forms\GridField\GridField;
use Terraformers\RichFilterHeader\Form\GridField\RichFilterHeader;

/**
 * This extension allows to process FormField actions of fields that are contained within the RichFilterHeader
 * and are identified by a composite field name
 *
 * @extends Extension<FormRequestHandler>
 */
class GridFieldRichFilterHeaderRequestExtension extends Extension
{
    private static array $allowed_actions = [
        'handleFieldComposite',
    ];

    /**
     * @param FieldList $fields
     * @param string $fieldName
     * @return FormField|null
     */
    protected function findField(FieldList $fields, string $fieldName): ?FormField
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
     * @param HTTPRequest $request
     * @return FormField|null
     */
    public function handleFieldComposite(HTTPRequest $request): ?FormField
    {
        $owner = $this->getOwner();

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
