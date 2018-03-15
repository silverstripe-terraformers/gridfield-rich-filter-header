<?php

namespace Terraformers\RichFilterHeader\Tests\Form\GridField\RichFilterHeaderTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class CheerleaderHat
 * @package Terraformers\RichFilterHeader\Tests\Form\GridField\RichFilterHeaderTest
 */
class CheerleaderHat extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'GridFieldRichFilterHeaderTest_CheerleaderHat';

    /**
     * @var array
     */
    private static $db = [
        'Colour' => 'Varchar',
    ];

    /**
     * @var array
     */
    private static $belongs_many_many = [
        'Cheerleaders' => Cheerleader::class,
    ];
}
