<?php
// src/Service/ImportSpreadsheet.php
namespace App\Service;

use App\Util\CsvHandler;
use App\Util\DataHandler;
use App\Util\TransformHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class ImportSpreadsheet
{
  private $params;
  private $logger;
  private $csvHandler;
  private $dataHandler;
  private $transformHandler;
  
  public function __construct(
      ContainerBagInterface $params,
      LoggerInterface $logger,
      CsvHandler $csvHandler,
      DataHandler $dataHandler,
      TransformHandler $transformHandler)
  {
    // load the configuration (use the services.yaml to define the
    // configuration in use and load from a dedicated config file in
    // the directory config/migrate
    $this->params = $params;

    $this->logger = $logger;
    $this->csvHandler = $csvHandler;
    $this->dataHandler = $dataHandler;
    $this->transformHandler = $transformHandler;
  }
  
  public function runIngest(): string
  {
    // @todo: It could be useful to test the configuration file
    // on validity, e.g.:
    //   * are the vocbularies correct set (does the vocabulary claimed
    //     for a data field exists in the vocabulary section)

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
    
    // @todo: how to handle external vocabularies in this foreach (using
    //   dedicated configuration settings) - formats as an example
    $this->logger->info('Write vocabulary files.');
    $vocabularies = $this->params->get('app.vocabularies');
    if (empty($vocabularies)) {
      $this->logger->info('No vocabularies set.');
    } else {
      foreach($vocabularies as $vocabularyName=>$vocabularyData) {
        $this->logger->info("Prepare and write vocabulary $vocabularyName.");
        $vocabularyValues = $this->transformHandler->transformData(
            $this->dataHandler->getVocabulary($vocabularyName),
            0, // always 0 as it comes directly from the already prepared data structure
            $vocabularyData['csv_file_structure'],
            $vocabularyData['csv_file_multival_separator']
        );
        $this->csvHandler->writeCSVData(
            array_keys($vocabularyData['csv_file_structure']),
            $vocabularyValues,
            $vocabularyData['csv_export_file']);
        }
    }

    //$this->dataHandler->showPartsOfData('8');
    
    $this->logger->info('Write data.');
    $this->csvHandler->writeCSVData(
        array_keys($this->params->get('app.csv_import_file_structure')),
        $this->dataHandler->getImportData(),
        $this->params->get('app.csv_export_file'));
    
    return "runIngest Service";
  }
}