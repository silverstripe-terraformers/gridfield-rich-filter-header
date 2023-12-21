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
    /**
     * @var string
     */
    private static $table_name = 'GridFieldRichFilterHeaderTest_Team';

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
        'Cheerleader' => Cheerleader::class,
    ];
}
