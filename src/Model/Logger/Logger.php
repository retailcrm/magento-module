<?php

namespace Retailcrm\Retailcrm\Model\Logger;

class Logger
{
    private $logDir;

    public function __construct(
        \Magento\Framework\Filesystem\DirectoryList $directory
    ) {
        $this->logDir = $directory->getPath('log');
    }

    /**
     * Write data in log file
     *
     * @param array $data
     * @param string $fileName
     *
     * @return void
     */
    public function writeDump($data, $fileName)
    {
        $filePath = $this->logDir . '/' . $fileName . '.log';

        if (!$this->checkSize($filePath)) {
            $this->clear($filePath);
        }

        $logData = [
            'date' => date('Y-m-d H:i:s'),
            'data' => $data
        ];

        $file = fopen($filePath, "a+");
        fwrite($file, print_r($logData, true));
        fclose($file);
    }

    /**
     * Write data in log file
     *
     * @param string $data
     * @param string $fileName
     *
     * @return void
     */
    public function writeRow($data, $fileName = 'retailcrm')
    {
        $filePath = $this->logDir . '/' . $fileName . '.log';

        if (!$this->checkSize($filePath)) {
            $this->clear($filePath);
        }

        $nowDate = date('Y-m-d H:i:s');
        $logData = "[$nowDate] @ " . $data . "\n";

        $file = fopen($filePath, "a+");
        fwrite($file, $logData);
        fclose($file);
    }

    /**
     * Clear file
     *
     * @param string $file
     *
     * @return void
     */
    private function clear($file)
    {
        file_put_contents($file, '');
    }

    /**
     * Check file size
     *
     * @param string $file
     *
     * @return boolean
     */
    private function checkSize($file)
    {
        if (!file_exists($file)) {
            return true;
        } elseif (filesize($file) > 10485760) {
            return false;
        }

        return true;
    }
}
