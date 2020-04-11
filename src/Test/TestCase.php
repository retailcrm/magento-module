<?php

namespace Retailcrm\Retailcrm\Test;

// backward compatibility with phpunit < v.6
if (!class_exists('\PHPUnit\Framework\TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');

    abstract class TestCase extends \PHPUnit\Framework\TestCase
    {
        public function createMock($originalClassName)
        {
            if (method_exists(\PHPUnit\Framework\TestCase::class, 'createMock')) {
                return parent::createMock($originalClassName);
            } else {
                return $this->getMockBuilder($originalClassName)
                    ->disableOriginalConstructor()
                    ->disableOriginalClone()
                    ->disableArgumentCloning()
                    ->getMock();
            }
        }

        protected function createPartialMock($originalClassName, $methods)
        {
            if (method_exists(\PHPUnit\Framework\TestCase::class, 'createPartialMock')) {
                return parent::createMock($originalClassName);
            } else {
                return $this->getMockBuilder($originalClassName)
                    ->disableOriginalConstructor()
                    ->disableOriginalClone()
                    ->disableArgumentCloning()
//            ->disallowMockingUnknownTypes()
                    ->setMethods(empty($methods) ? null : $methods)
                    ->getMock();
            }
        }
    }
} else {

    abstract class TestCase extends \PHPUnit\Framework\TestCase
    {
        public function createMock($originalClassName)
        {
            if (method_exists(\PHPUnit\Framework\TestCase::class, 'createMock')) {
                return parent::createMock($originalClassName);
            } else {
                return $this->getMockBuilder($originalClassName)
                    ->disableOriginalConstructor()
                    ->disableOriginalClone()
                    ->disableArgumentCloning()
                    ->getMock();
            }
        }
    }
}


