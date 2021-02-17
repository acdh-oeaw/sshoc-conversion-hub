<?php
// src/Service/ImportSpreadsheet.php
namespace App\Service;

use App\Util\CsvHandler;
use App\Util\DataHandler;
use App\Util\VocabularyHandler;
use App\Util\TransformHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class ImportSpreadsheet
{
  private $params;
  private $logger;
  private $csvHandler;
  private $dataHandler;
  private $vocabularyHandler;
  private $transformHandler;
  
  public function __construct(
      ContainerBagInterface $params,
      LoggerInterface $logger,
      CsvHandler $csvHandler,
      DataHandler $dataHandler,
      VocabularyHandler $vocabularyHandler,
      TransformHandler $transformHandler)
  {
    // load the configuration (use the services.yaml to define the
    // configuration in use and load from a dedicated config file in
    // the directory config/migrate
    $this->params = $params;

    $this->logger = $logger;
    $this->csvHandler = $csvHandler;
    $this->dataHandler = $dataHandler;
    $this->vocabularyHandler = $vocabularyHandler;
    $this->transformHandler = $transformHandler;
  }
  
  public function runIngest(): string
  {
    // to see this log, call it on console with -vv
    /*$this->logger->info('test: using parameter: '
        . $this->params->get('app.csv_import_file'));*/
    
    $this->logger->info('Load the main csv file.');
    $csvRecords = $this->csvHandler->loadCsvData(
        $this->params->get('app.csv_import_file'),
        $this->params->get('app.csv_import_file_has_header')
    );
    
    $this->logger->info('Transform csv data.');
    $importData = $this->transformHandler->transformData(
        $csvRecords,
        $this->params->get('app.csv_import_file_process_from_row'),
        $this->params->get('app.csv_import_file_structure'),
        $this->params->get('app.csv_import_file_multival_separator')
    );
    
    $this->logger->info('Set data handler.');
    $this->dataHandler->setImportData($importData);

    $this->logger->info('Load the formats vocabulary csv file.');
    $csvFormats = $this->csvHandler->loadCsvData(
        $this->params->get('app.csv_vocab_formats_file'),
        $this->params->get('app.csv_vocab_formats_file_has_header')
    );

    $this->logger->info('Transform the formats vocabulary csv data.');
    $formatsData = $this->transformHandler->transformData(
        $csvFormats,
        $this->params->get('app.csv_vocab_formats_file_process_from_row'),
        $this->params->get('app.csv_vocab_formats_file_structure'),
        $this->params->get('app.csv_vocab_formats_file_multival_separator')
    );
    
    $this->logger->info('Write vocabulary formats.');
    $this->csvHandler->writeCSVData(
        array_keys($this->params->get('app.csv_vocab_formats_file_structure')),
        $formatsData,
        $this->params->get('app.csv_vocab_formats_file_export_file'));

    $this->logger->info('Set the external vocabulary formats.');
    $this->dataHandler->setExternalVocabulary('formats', $formatsData);

    $this->logger->info('Handle vocabularies in data.');
    $this->dataHandler->handleVocabularies(
        $this->params->get('app.csv_import_file_structure'));
    
    $this->logger->info('Prepare and write vocabulary invocations.');
    $invocations = $this->transformHandler->transformData(
        $this->dataHandler->getVocabulary('Invocations'),
        $this->params->get('app.csv_vocab_invocations_process_from_row'),
        $this->params->get('app.csv_vocab_invocations_structure'),
        $this->params->get('app.csv_vocab_invocations_multival_separator')
    );
    $this->csvHandler->writeCSVData(
        array_keys($this->params->get('app.csv_vocab_invocations_structure')),
        $invocations,
        $this->params->get('app.csv_vocab_invocations_export_file'));

    $this->logger->info('Prepare and write vocabulary communities.');
    $communities = $this->transformHandler->transformData(
        $this->dataHandler->getVocabulary('Communities'),
        $this->params->get('app.csv_vocab_communities_process_from_row'),
        $this->params->get('app.csv_vocab_communities_structure'),
        $this->params->get('app.csv_vocab_communities_multival_separator')
    );
    $this->csvHandler->writeCSVData(
        array_keys($this->params->get('app.csv_vocab_communities_structure')),
        $communities,
        $this->params->get('app.csv_vocab_communities_export_file'));

    // @todo: vocabularies handling could be done via the configuration!
    $this->logger->info('Prepare and write vocabulary statuses.');
    $statuses = $this->transformHandler->transformData(
        $this->dataHandler->getVocabulary('Statuses'),
        $this->params->get('app.csv_vocab_statuses_process_from_row'),
        $this->params->get('app.csv_vocab_statuses_structure'),
        $this->params->get('app.csv_vocab_statuses_multival_separator')
    );
    $this->csvHandler->writeCSVData(
        array_keys($this->params->get('app.csv_vocab_statuses_structure')),
        $statuses,
        $this->params->get('app.csv_vocab_statuses_export_file'));

    //$this->dataHandler->showPartsOfData('8');
    
    $this->logger->info('Write data.');
    $this->csvHandler->writeCSVData(
        array_keys($this->params->get('app.csv_import_file_structure')),
        $this->dataHandler->getImportData(),
        $this->params->get('app.csv_export_file'));
    
    return "runIngest Service";
  }
}