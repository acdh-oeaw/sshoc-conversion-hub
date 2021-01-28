<?php
namespace SSHOC\Conversion;

/**
 * @Author: Klaus Illmayer, klaus.illmayer@oeaw.ac.at
 * 
 * MIT License
 * 
 * Copyright (c) 2021 OEAW ACDH-CH, Klaus Illmayer
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */

class IngestSpreadsheet
{
  private $configClass = null;
  private $csvHandler = null;
  private $dataHandler = null;

  public function __construct($configClass)
  {
    $this->configClass = $configClass;

    $csvFile = $this->configClass->getConfig('csv_import_file', true);
    $csvFileHeader = $this->configClass->getConfig(
        'csv_import_file_has_header', true);
    $this->csvHandler = new CsvHandler(
        $this->configClass, $csvFile, $csvFileHeader);

    $this->dataHandler = new DataHandler(
        $this->configClass, $this->apiHandler, $this->vocabularyHandler);
  }
  
  private function connectToApi()
  {
    // Get the vocabularies that are necessary for the harmonizing
    $this->vocabularyHandler->setVocabulary(
        'licenses', $this->apiHandler->getVocabulary('licenses'));
    $this->vocabularyHandler->setVocabulary(
        'license-types', $this->apiHandler->getVocabulary('license-types'));
  }

  public function runIngest()
  {
    Logger::log('load csv data');
    $this->csvHandler->loadCsvData();
    
    Logger::log('process csv data');
    $importData = $this->csvHandler->processCsv();

    Logger::log('validate csv data');
    $this->dataHandler->checkImportData($importData);
    
    Logger::log('harmonize data for API');
    $this->dataHandler->harmonizeData();
    
    Logger::log('find updates');
    $this->dataHandler->checkForExistingData();
    
    Logger::log('add recommended');
    $this->dataHandler->addRecommended();
    
    Logger::log('prepare data for compare');
    $this->dataHandler->prepareDataForCompare();

    Logger::log('merge differences');
    $this->dataHandler->mergeUpdates();

    Logger::log('prepare data for ingest');
    $this->dataHandler->prepareDataForIngest();

    Logger::log('ingest data');
    $this->dataHandler->ingestData();
  }
  
}
