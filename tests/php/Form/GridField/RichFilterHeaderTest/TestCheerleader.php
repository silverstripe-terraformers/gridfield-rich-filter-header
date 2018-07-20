<?php

/**
 * Class Cheerleader
 * @package RichFilterHeaderTest
 */
class TestCheerleader extends DataObject implements TestOnly
{

    /**
     * @var array
     */
    private static $db = [
        'Name' => 'Varchar',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Team' => TestTeam::class,
    ];

    /**
     * @var array
     */
    private static $many_many = [
        'Hats' => TestCheerleaderHat::class,
    ];
}
