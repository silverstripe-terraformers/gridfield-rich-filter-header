<?php

namespace Terraformers\RichFilterHeader\Tests\Form\GridField\RichFilterHeaderTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class Cheerleader
 * @package Terraformers\RichFilterHeader\Tests\Form\GridField\RichFilterHeaderTest
 */
class Cheerleader extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'GridFieldRichFilterHeaderTest_Cheerleader';

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
        'Team' => Team::class,
    ];

    /**
     * @var array
     */
    private static $many_many = [
        'Hats' => CheerleaderHat::class,
    ];
}
