<?php

namespace Terraformers\RichFilterHeader\Tests\Form\GridField\RichFilterHeaderTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * @property string $Name
 * @property string $City
 * @method Cheerleader Cheerleader()
 */
class Team extends DataObject implements TestOnly
{
    private static string $table_name = 'GridFieldRichFilterHeaderTest_Team';

    private static array $summary_fields = [
        'Name' => 'Name',
        'City.Initial' => 'City',
    ];

    private static array $db = [
        'Name' => 'Varchar',
        'City' => 'Varchar',
    ];

    private static array $has_one = [
        'Cheerleader' => Cheerleader::class,
    ];
}
