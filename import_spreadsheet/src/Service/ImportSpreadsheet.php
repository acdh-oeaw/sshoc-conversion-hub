<?php
// src/Service/ImportSpreadsheet.php
namespace App\Service;

use App\Util\CsvHandler;
use App\Util\DataHandler;
use App\Util\VocabularyHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class ImportSpreadsheet
{
  private $params;
  private $logger;
  private $csvHandler;
  private $dataHandler;
  
  public function __construct(
      ContainerBagInterface $params,
      LoggerInterface $logger,
      CsvHandler $csvHandler,
      DataHandler $dataHandler)
  {
    // load the configuration (use the services.yaml to define the
    // configuration in use and load from a dedicated config file in
    // the directory config/migrate
    $this->params = $params;

    $this->logger = $logger;
    $this->csvHandler = $csvHandler;
    $this->dataHandler = $dataHandler;
  }
  
  public function runIngest(): string
  {
    //$this->csvHandler->loadCsvData('test.x', true, [], 1);
    // to see this log, call it on console with -vv
    $this->logger->info('test: using parameter: '
        . $this->params->get('app.csv_import_file'));
    
    $this->logger->info('Load the csv file.');
    $this->csvHandler->loadCsvData();
    
    $this->logger->info('Process csv data.');
    $importData = $this->csvHandler->processCsv();
    
    $this->logger->info('Set data handler.');
    $this->dataHandler->setImportData($importData);

    /*$this->logger->info('Collect vocabulary communities.');
    $communityVocabulary = new VocabularyHandler($this->logger);
    $communityVocabulary->collectDataFromImportData($importData, 'community');
    //print_r($communityVocabulary->getVocabularyData());
    
    $this->logger->info('Apply vocabulary communities.');
    $this->dataHandler->setVocabularyData('community',
        $communityVocabulary->getVocabularyData());

    $this->logger->info('Collect vocabulary ingestions.');
    $ingestionVocabulary = new VocabularyHandler($this->logger);
    $ingestionVocabulary->collectDataFromImportData($importData, 'invocation');
    //print_r($ingestionVocabulary->getVocabularyData());
    
    $this->logger->info('Apply vocabulary ingestions.');
    $this->dataHandler->setVocabularyData('invocation',
        $ingestionVocabulary->getVocabularyData());*/
    
    $this->logger->info('Handle vocabularies in data.');
    $this->dataHandler->handleVocabularies(
        $this->params->get('app.csv_import_file_structure'));

    //$this->dataHandler->showPartsOfData('8');
    
    $this->logger->info('Write data.');
    $this->csvHandler->writeCSVData($this->dataHandler->getImportData(),
        $this->params->get('app.csv_export_file'));
    
    // todo: write the vocabularies
    // todo: handle the formats vocabulary!

    return "runIngest Service";
  }
}