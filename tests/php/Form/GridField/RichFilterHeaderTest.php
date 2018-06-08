<?php

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
    protected $usesDatabase = true;
    /**
     * @var array
     */
    protected $extraDataObjects = array(
        'TestTeam',
        'TestCheerleader',
        'TestCheerleaderHat',
    );

    public function setUp()
    {
        parent::setUp();

        $list = DataList::create('TestTeam');

        $config = new GridFieldConfig_RecordEditor();
        $config->removeComponentsByType('GridFieldFilterHeader');
        $config->addComponent(new RichFilterHeader(), 'GridFieldPaginator');

        $this->gridField = new GridField('testfield', 'testfield', $list, $config);
        $this->form = new Form(
            Controller::curr(),
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
        $component = $config->getComponentByType('RichFilterHeader');
        $htmlFragment = $component->getHTMLFragments($gridField);

        $this->assertContains(
            '<input type="text" name="filter[testfield][City]"'
            . ' class="text grid-field__sort-field no-change-track nolabel"'
            . ' id="Form_mockform_filter_testfield_City" placeholder="Filter by City" />',
            $htmlFragment['header']
        );
    }

    public function testRenderFilterHeaderWithCustomFields()
    {
        $gridField = $this->gridField;
        $config = $gridField->getConfig();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType('RichFilterHeader');
        $component->setFilterFields([
            'Name' => DropdownField::create('', '', ['Name1' => 'Name1', 'Name2' => 'Name2']),
            'City' => DropdownField::create('', '', ['City' => 'City1', 'City2' => 'City2']),
        ]);

        $htmlFragment = $component->getHTMLFragments($gridField);

        $this->assertContains(
            '<select name="filter[testfield][Name]" '
            . 'class="dropdown grid-field__sort-field no-change-track nolabel"'
            . ' id="Form_mockform_filter_testfield_Name" placeholder="Filter by Name">',
            $htmlFragment['header']
        );

        $this->assertContains(
            '<select name="filter[testfield][City]" '
            . 'class="dropdown grid-field__sort-field no-change-track nolabel"'
            . ' id="Form_mockform_filter_testfield_City" placeholder="Filter by City">',
            $htmlFragment['header']
        );
    }

    public function testRenderFilterHeaderWithFullConfig()
    {
        $gridField = $this->gridField;
        $config = $gridField->getConfig();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType('RichFilterHeader');
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
            . 'class="dropdown grid-field__sort-field no-change-track nolabel"'
            . ' id="Form_mockform_filter_testfield_Name" placeholder="Filter by Name">',
            $htmlFragment['header']
        );

        $this->assertContains(
            '<select name="filter[testfield][City]" '
            . 'class="dropdown grid-field__sort-field no-change-track nolabel"'
            . ' id="Form_mockform_filter_testfield_City" placeholder="Filter by City">',
            $htmlFragment['header']
        );
    }

    public function testRenderFilterHeaderBasicFilter()
    {
        $gridField = $this->gridField;
        $config = $gridField->getConfig();
        $controller = Controller::curr();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType('RichFilterHeader');
        $component->setFilterConfig([
            'City.Initial' => [
                'title' => 'City',
                'filter' => 'ExactMatchFilter',
            ],
        ]);

        $city = 'Auckland';

        $stateID = 'testGridStateActionField';
        $session = $controller->getSession();
        $session->set(
            $stateID,
            [
                'grid' => '',
                'actionName' => 'filter',
                'args' => [],
            ]
        );

        $token = SecurityToken::inst();
        $request = new SS_HTTPRequest(
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

        $controller->setSession($session);
        $controller->setRequest($request);
        $gridField->gridFieldAlterAction(['StateID' => $stateID], $this->form, $request);
        $list = $component->getManipulatedData($gridField, $gridField->getList());

        $this->assertEquals(1, (int) $list->count());
        $this->assertEquals($city, $list->first()->City);
    }

    public function testRenderFilterHeaderAdvancedFilterAllKeywords()
    {
        $gridField = $this->gridField;
        $config = $gridField->getConfig();
        $controller = Controller::curr();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType('RichFilterHeader');
        $component
            ->setFilterConfig([
                'Name',
            ])
            ->setFilterMethods([
                'Name' => RichFilterHeader::FILTER_ALL_KEYWORDS,
            ]);

        $keywords = 'Team 1';

        $stateID = 'testGridStateActionField';
        $session = $controller->getSession();
        $session->set(
            $stateID,
            [
                'grid' => '',
                'actionName' => 'filter',
                'args' => [],
            ]
        );

        $token = SecurityToken::inst();
        $request = new SS_HTTPRequest(
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

        $controller->setSession($session);
        $controller->setRequest($request);
        $gridField->gridFieldAlterAction(['StateID' => $stateID], $this->form, $request);
        $list = $component->getManipulatedData($gridField, $gridField->getList());

        $this->assertEquals(1, (int) $list->count());
        $this->assertEquals($keywords, $list->first()->Name);
    }

    public function testRenderFilterHeaderAdvancedFilterManyManyRelation()
    {
        $gridField = $this->gridField;
        $gridField->setList(DataList::create('TestCheerleader'));
        $config = $gridField->getConfig();
        $controller = Controller::curr();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType('RichFilterHeader');
        $component
            ->setFilterConfig([
                'Name' => 'Hats',
            ])
            ->setFilterMethods([
                'Hats' => RichFilterHeader::FILTER_MANY_MANY_RELATION,
            ]);

        $hat = TestCheerleaderHat::get()
            ->filter(['Colour' => 'Blue'])
            ->first();

        $stateID = 'testGridStateActionField';
        $session = $controller->getSession();
        $session->set(
            $stateID,
            [
                'grid' => '',
                'actionName' => 'filter',
                'args' => [],
            ]
        );

        $token = SecurityToken::inst();
        $request = new SS_HTTPRequest(
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

        $controller->setSession($session);
        $controller->setRequest($request);
        $gridField->gridFieldAlterAction(['StateID' => $stateID], $this->form, $request);
        $list = $component->getManipulatedData($gridField, $gridField->getList());

        $this->assertEquals(1, (int) $list->count());
        $this->assertEquals($hat->ID, $list->first()->Hats()->first()->ID);
    }

    public function testRenderFilterHeaderAdvancedFilterCustomCallback()
    {
        $gridField = $this->gridField;
        $config = $gridField->getConfig();
        $controller = Controller::curr();

        /** @var $component RichFilterHeader */
        $component = $config->getComponentByType('RichFilterHeader');
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
        $session = $controller->getSession();
        $session->set(
            $stateID,
            [
                'grid' => '',
                'actionName' => 'filter',
                'args' => [],
            ]
        );

        $token = SecurityToken::inst();
        $request = new SS_HTTPRequest(
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

        $controller->setSession($session);
        $controller->setRequest($request);
        $gridField->gridFieldAlterAction(['StateID' => $stateID], $this->form, $request);

        /** @var DataList $list */
        $list = $component->getManipulatedData($gridField, $gridField->getList());

        $this->assertEquals(2, (int) $list->count());
        $cities = $list->sort('City', 'ASC')->column('City');
        $this->assertEquals(['newton', 'Wellington'], $cities);
    }
}
