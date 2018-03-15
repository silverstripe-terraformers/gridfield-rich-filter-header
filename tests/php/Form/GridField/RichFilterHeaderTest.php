<?php

namespace Terraformers\RichFilterHeader\Tests\Form\GridField;

use Terraformers\RichFilterHeader\Form\GridField\RichFilterHeader;
use Terraformers\RichFilterHeader\Tests\Form\GridField\RichFilterHeaderTest\Cheerleader;
use Terraformers\RichFilterHeader\Tests\Form\GridField\RichFilterHeaderTest\CheerleaderHat;
use Terraformers\RichFilterHeader\Tests\Form\GridField\RichFilterHeaderTest\Team;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\SecurityToken;

/**
 * Class RichFilterHeaderTest
 * @package Terraformers\RichFilterHeader\Tests\Form\GridField
 */
class RichFilterHeaderTest extends SapphireTest
{
    /**
     * @var GridField
     */
    protected $gridField;

    /**
     * @var Form
     */
    protected $form;

    /**
     * @var string
     */
    protected static $fixture_file = 'RichFilterHeaderTest.yml';

    /**
     * @var array
     */
    protected static $extra_dataobjects = array(
        Team::class,
        Cheerleader::class,
        CheerleaderHat::class,
    );

    protected function setUp()
    {
        parent::setUp();

        $list = DataList::create(Team::class);

        $config = new GridFieldConfig_RecordEditor();
        $config->removeComponentsByType(GridFieldFilterHeader::class);
        $config->addComponent(new RichFilterHeader(), GridFieldPaginator::class);

        $this->gridField = new GridField('testfield', 'testfield', $list, $config);
        $this->form = new Form(
            null,
            'mockform',
            new FieldList([$this->gridField]),
            new FieldList()
        );
    }

    public function testCompositeFieldName()
    {
        $gridFieldName = 'test-grid-field1';
        $childFieldName = 'test-child-field1';
        $compositeFieldName = RichFilterHeader::createCompositeFieldName($gridFieldName, $childFieldName);
        $data = RichFilterHeader::parseCompositeFieldName($compositeFieldName);

        $this->assertNotEmpty($data);
        $this->assertEquals($gridFieldName, $data['grid_field']);
        $this->assertEquals($childFieldName, $data['child_field']);
    }

    public function testRenderFilteredHeaderStandard()
    {
        $gridField = $this->gridField;
        $config = $gridField->getConfig();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType(RichFilterHeader::class);
        $htmlFragment = $component->getHTMLFragments($gridField);

        $this->assertContains(
            '<input type="text" name="filter[testfield][City]"'
            . ' class="text grid-field__sort-field no-change-track form-group--no-label"'
            . ' id="Form_mockform_filter_testfield_City" placeholder="Filter by City" />',
            $htmlFragment['header']
        );
    }

    public function testRenderFilterHeaderWithCustomFields()
    {
        $gridField = $this->gridField;
        $config = $gridField->getConfig();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType(RichFilterHeader::class);
        $component->setFilterFields([
            'Name' => DropdownField::create('', '', ['Name1' => 'Name1', 'Name2' => 'Name2']),
            'City' => DropdownField::create('', '', ['City' => 'City1', 'City2' => 'City2']),
        ]);

        $htmlFragment = $component->getHTMLFragments($gridField);

        $this->assertContains(
            '<select name="filter[testfield][Name]" '
            . 'class="dropdown grid-field__sort-field no-change-track form-group--no-label"'
            . ' id="Form_mockform_filter_testfield_Name" placeholder="Filter by Name">',
            $htmlFragment['header']
        );

        $this->assertContains(
            '<select name="filter[testfield][City]" '
            . 'class="dropdown grid-field__sort-field no-change-track form-group--no-label"'
            . ' id="Form_mockform_filter_testfield_City" placeholder="Filter by City">',
            $htmlFragment['header']
        );
    }

    public function testRenderFilterHeaderWithFullConfig()
    {
        $gridField = $this->gridField;
        $config = $gridField->getConfig();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType(RichFilterHeader::class);
        $component
            ->setFilterConfig([
                'Name' => [
                    'title' => 'Name',
                    'filter' => 'ExactMatchFilter',
                ],
                'City.Initial' => [
                    'title' => 'City',
                    'filter' => 'ExactMatchFilter',
                ],
            ])
            ->setFilterFields([
                'Name' => DropdownField::create('', '', ['Name1' => 'Name1', 'Name2' => 'Name2']),
                'City' => DropdownField::create('', '', ['City' => 'City1', 'City2' => 'City2']),
            ]);

        $htmlFragment = $component->getHTMLFragments($gridField);

        $this->assertContains(
            '<select name="filter[testfield][Name]" '
            . 'class="dropdown grid-field__sort-field no-change-track form-group--no-label"'
            . ' id="Form_mockform_filter_testfield_Name" placeholder="Filter by Name">',
            $htmlFragment['header']
        );

        $this->assertContains(
            '<select name="filter[testfield][City]" '
            . 'class="dropdown grid-field__sort-field no-change-track form-group--no-label"'
            . ' id="Form_mockform_filter_testfield_City" placeholder="Filter by City">',
            $htmlFragment['header']
        );
    }

    public function testRenderFilterHeaderBasicFilter()
    {
        $gridField = $this->gridField;
        $config = $gridField->getConfig();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType(RichFilterHeader::class);
        $component->setFilterConfig([
            'City.Initial' => [
                'title' => 'City',
                'filter' => 'ExactMatchFilter',
            ],
        ]);

        $city = 'Auckland';

        $stateID = 'testGridStateActionField';
        $session = Controller::curr()->getRequest()->getSession();
        $session->set(
            $stateID,
            [
                'grid' => '',
                'actionName' => 'filter',
                'args' => [],
            ]
        );

        $token = SecurityToken::inst();
        $request = new HTTPRequest(
            'POST',
            'url',
            [],
            [
                'action_gridFieldAlterAction?StateID='.$stateID=>true,
                $token->getName() => $token->getValue(),
                'filter' => [
                    $gridField->getName() => [
                        'City' => $city,
                    ],
                ],
            ]
        );

        $request->setSession($session);
        $gridField->gridFieldAlterAction(['StateID' => $stateID], $this->form, $request);
        $list = $component->getManipulatedData($gridField, $gridField->getList());

        $this->assertEquals(1, (int) $list->count());
        $this->assertEquals($city, $list->first()->City);
    }

    public function testRenderFilterHeaderAdvancedFilterAllKeywords()
    {
        $gridField = $this->gridField;
        $config = $gridField->getConfig();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType(RichFilterHeader::class);
        $component
            ->setFilterConfig([
                'Name',
            ])
            ->setFilterMethods([
                'Name' => RichFilterHeader::FILTER_ALL_KEYWORDS,
            ]);

        $keywords = 'Team 1';

        $stateID = 'testGridStateActionField';
        $session = Controller::curr()->getRequest()->getSession();
        $session->set(
            $stateID,
            [
                'grid' => '',
                'actionName' => 'filter',
                'args' => [],
            ]
        );

        $token = SecurityToken::inst();
        $request = new HTTPRequest(
            'POST',
            'url',
            [],
            [
                'action_gridFieldAlterAction?StateID='.$stateID=>true,
                $token->getName() => $token->getValue(),
                'filter' => [
                    $gridField->getName() => [
                        'Name' => $keywords,
                    ],
                ],
            ]
        );

        $request->setSession($session);
        $gridField->gridFieldAlterAction(['StateID' => $stateID], $this->form, $request);
        $list = $component->getManipulatedData($gridField, $gridField->getList());

        $this->assertEquals(1, (int) $list->count());
        $this->assertEquals($keywords, $list->first()->Name);
    }

    public function testRenderFilterHeaderAdvancedFilterManyManyRelation()
    {
        $gridField = $this->gridField;
        $gridField->setList(DataList::create(Cheerleader::class));
        $config = $gridField->getConfig();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType(RichFilterHeader::class);
        $component
            ->setFilterConfig([
                'Name' => 'Hats',
            ])
            ->setFilterMethods([
                'Hats' => RichFilterHeader::FILTER_MANY_MANY_RELATION,
            ]);

        $hat = CheerleaderHat::get()
            ->filter(['Colour' => 'Blue'])
            ->first();

        $stateID = 'testGridStateActionField';
        $session = Controller::curr()->getRequest()->getSession();
        $session->set(
            $stateID,
            [
                'grid' => '',
                'actionName' => 'filter',
                'args' => [],
            ]
        );

        $token = SecurityToken::inst();
        $request = new HTTPRequest(
            'POST',
            'url',
            [],
            [
                'action_gridFieldAlterAction?StateID='.$stateID=>true,
                $token->getName() => $token->getValue(),
                'filter' => [
                    $gridField->getName() => [
                        'Hats' => $hat->ID,
                    ],
                ],
            ]
        );

        $request->setSession($session);
        $gridField->gridFieldAlterAction(['StateID' => $stateID], $this->form, $request);
        $list = $component->getManipulatedData($gridField, $gridField->getList());

        $this->assertEquals(1, (int) $list->count());
        $this->assertEquals($hat->ID, $list->first()->Hats()->first()->ID);
    }

    public function testRenderFilterHeaderAdvancedFilterCustomCallback()
    {
        $gridField = $this->gridField;
        $config = $gridField->getConfig();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType(RichFilterHeader::class);
        $component
            ->setFilterConfig([
                'City',
            ])
            ->setFilterMethods([
                'City' => function (DataList $list, $name, $value) {
                    return $list->filterAny([
                        'City:StartsWith' => $value,
                        'City:EndsWith' => $value,
                    ]);
                },
            ]);

        $stateID = 'testGridStateActionField';
        $session = Controller::curr()->getRequest()->getSession();
        $session->set(
            $stateID,
            [
                'grid' => '',
                'actionName' => 'filter',
                'args' => [],
            ]
        );

        $token = SecurityToken::inst();
        $request = new HTTPRequest(
            'POST',
            'url',
            [],
            [
                'action_gridFieldAlterAction?StateID='.$stateID=>true,
                $token->getName() => $token->getValue(),
                'filter' => [
                    $gridField->getName() => [
                        'City' => 'n',
                    ],
                ],
            ]
        );

        $request->setSession($session);
        $gridField->gridFieldAlterAction(['StateID' => $stateID], $this->form, $request);

        /** @var DataList $list */
        $list = $component->getManipulatedData($gridField, $gridField->getList());

        $this->assertEquals(2, (int) $list->count());
        $cities = $list->sort('City', 'ASC')->column('City');
        $this->assertEquals(['newton', 'Wellington'], $cities);
    }
}
