<?php
namespace Retailcrm\Retailcrm\Model\Logger;

class Logger
{
    private $logPath;
    private $files;
    
    public function __construct(
    		$logPath = '/app/code/Retailcrm/Retailcrm/Log/'
    		)
    {
        $this->logPath = $logPath;
        
    }
    
    public function write($dump, $file)
    {
    	$path =$this->logPath . $file.'.txt';
    	
    	$f = fopen($_SERVER["DOCUMENT_ROOT"].$path, "a+");
    	fwrite($f, print_r(array(date('Y-m-d H:i:s'), array(
    			$dump
    	)),true));
    	fclose($f);
    	
    }

}
