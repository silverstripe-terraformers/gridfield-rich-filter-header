<?php

namespace Terraformers\RichFilterHeader\Tests\Form\GridField\RichFilterHeaderTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;

/**
 * @property string $Colour
 * @method ManyManyList|Cheerleader[] Cheerleaders()
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
