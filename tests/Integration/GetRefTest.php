<?php

namespace Nayjest\DI\Test\Integration;

use Nayjest\DI\Hub;
use PHPUnit\Framework\TestCase;


class GetRefTest extends TestCase
{
    public function test()
    {
        $hub = new Hub();
        $hub->builder()->define('item', 1);
        $ref = &$hub->get('item');
        // THIS OPERATION IS NOT ALLOWED
        // CONSIDER ITEMS EXTRACTED BY REFERENCE READONLY,
        // CHAGE VALUES ONLY VIA HUB
        $ref = 3;
        $this->assertEquals(3, $hub->get('item'));

        $hub->set('item', 2);
        $this->assertEquals(2, $ref);
    }
}