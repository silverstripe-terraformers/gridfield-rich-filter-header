<?php

namespace Terraformers\RichFilterHeader\Tests\Form\GridField\RichFilterHeaderTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;

/**
 * @property string $Name
 * @method Team Team()
 * @method HasManyList|CheerleaderHat[] Hats()
 */
class Cheerleader extends DataObject implements TestOnly
{
    private static string $table_name = 'GridFieldRichFilterHeaderTest_Cheerleader';

    private static array $db = [
        'Name' => 'Varchar',
    ];

    private static array $has_one = [
        'Team' => Team::class,
    ];

    private static array $many_many = [
        'Hats' => CheerleaderHat::class,
    ];
}
