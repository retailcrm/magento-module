<?php

namespace Retailcrm\Retailcrm\Test;

// backward compatibility with phpunit < v.6
if (!class_exists('\PHPUnit\Framework\TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
}

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function createMock($originalClassName)
    {
        if (method_exists(\PHPUnit\Framework\TestCase::class, 'createMock')) {
            return parent::createMock($originalClassName);
        } elseif (method_exists(\PHPUnit\Framework\TestCase::class, 'getMock')) {
            return $this->getMockBuilder($originalClassName)
                ->disableOriginalConstructor()
                ->disableOriginalClone()
                ->disableArgumentCloning()
                ->getMock();
        } else {
            throw new \RuntimeException('Not supported phpunit version');
        }
    }
}
