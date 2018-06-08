<?php

/**
 * Class Team
 * @package RichFilterHeaderTest
 */
class TestTeam extends DataObject implements TestOnly
{

    /**
     * @var array
     */
    private static $summary_fields = [
        'Name' => 'Name',
        'City.Initial' => 'City',
    ];

    /**
     * @var array
     */
    private static $db = [
        'Name' => 'Varchar',
        'City' => 'Varchar',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Cheerleader' => 'TestCheerleader',
    ];
}
