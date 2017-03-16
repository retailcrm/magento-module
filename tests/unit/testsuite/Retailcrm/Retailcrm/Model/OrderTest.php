<?php
 
class Retailcrm_Retailcrm_Model_OrderTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        Mage::reset ();
		Mage::app ('default');
		Mage::dispatchEvent('controller_front_init_before');
        $this->_block = new Retailcrm_Retailcrm_Model_Order;
    }

    protected function tearDown()
    {
    
    }
    
    public function testFirstMethod()
    {
    	$this->assertInstanceOf('Retailcrm_Retailcrm_Model_Order',$this->_block);
    }
    
    public function testSecondMethod()
    {
        
    }
}