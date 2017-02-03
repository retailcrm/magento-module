<?php
 
class Retailcrm_Retailcrm_Model_IcmlTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        Mage::reset ();
        $app = Mage::app('default');
        Mage::dispatchEvent('controller_front_init_before');
        $this->_block = new Retailcrm_Retailcrm_Model_Icml;
    }
 
    protected function tearDown()
    {
    
    }
    
    public function testFirstMethod()
    {
        $this->assertInstanceOf('Retailcrm_Retailcrm_Model_Icml',$this->_block);
    }
}