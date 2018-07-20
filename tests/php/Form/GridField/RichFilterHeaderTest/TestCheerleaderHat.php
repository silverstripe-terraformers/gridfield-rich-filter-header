<?php

/**
 * Class CheerleaderHat
 * @package RichFilterHeaderTest
 */
class TestCheerleaderHat extends DataObject implements TestOnly
{
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
        'Cheerleaders' => TestCheerleader::class,
    ];
}
