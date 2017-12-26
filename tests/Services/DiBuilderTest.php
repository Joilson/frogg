<?php

use PHPUnit\Framework\TestCase;

class DiBuilderTest extends TestCase
{
    public function testAbstract()
    {
        $diBuilder = new class(null) extends \Frogg\Services\AbstractDiBuilder
        {
            protected function appServices()
            {
                return [
                    \Frogg\Services\DefaultService::class,
                ];
            }
        };

        $this->assertInstanceOf(\Frogg\Services\DefaultService::class, $diBuilder->get(\Frogg\Services\DefaultService::getName()));
    }
}