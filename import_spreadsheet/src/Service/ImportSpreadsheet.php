<?php
// src/Service/ImportSpreadsheet.php
namespace App\Service;

use App\Util\CsvHandler;
use Psr\Log\LoggerInterface;

class ImportSpreadsheet
{
  private $logger;
  private $csvHandler;
  
  public function __construct(LoggerInterface $logger, CsvHandler $csvHandler)
  {
    $this->logger = $logger;
    $this->csvHandler = $csvHandler;
  }
  
  public function runIngest(): string
  {
    //$this->csvHandler->loadCsvData('test.x', true, [], 1);
    // to see this log, call it on console with -vv
    $this->logger->info('test');
    
    return "runIngest Service";
  }
}