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
    private static string $table_name = 'GridFieldRichFilterHeaderTest_CheerleaderHat';

    private static array $db = [
        'Colour' => 'Varchar',
    ];

    private static array $belongs_many_many = [
        'Cheerleaders' => Cheerleader::class,
    ];
}
