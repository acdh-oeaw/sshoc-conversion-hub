<?php
// src/Util/CsvHandler.php

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
namespace App\Util;

use Psr\Log\LoggerInterface;
// Relying on external library for CSV reading (install with composer)
use League\Csv\Reader;
use League\Csv\Writer;

/**
 * Handling of CSV files.
 */
class CsvHandler
{
  private $logger;

  private $csvFile = '';
  private $hasHeader = false;
  private $processFromRow = 0;
  private $multivalSeparator = ' ; ';
  private $structure = [];
  
  private $csvRecords = [];
  
  public function __construct($csvImportFile, $csvImportFileHasHeader,
      $csvImportFileProcessFromRow, $csvImportFileMultivalSeparator,
      $csvImportFileStructure,
      LoggerInterface $logger)
  {
    $this->logger = $logger;
    $this->csvFile = $csvImportFile;
    if (!file_exists($this->csvFile)) {
      $this->logger->critical("Import file $this->csvFile is missing.");
    }

    $this->hasHeader = $csvImportFileHasHeader;
    $this->processFromRow = $csvImportFileProcessFromRow;
    $this->multivalSeparator = $csvImportFileMultivalSeparator;
    $this->structure = $csvImportFileStructure;
  }
  
  public function getCsvRecords(): array
  {
    return $this->csvRecords;
  }

  /**
   * Load the data from the csvFile defined in the constructor (csvFile).
   * 
   * @param bool $hasHeader
   * @return array
   */
  public function loadCsvData()
  {
    $csv = Reader::createFromPath($this->csvFile, 'r');
    if($this->hasHeader) {
      $csv->setHeaderOffset(0);
    }
    $csvRecords = $csv->getRecords();
    if (empty($csvRecords)) {
      $this->logger->critical('Import file '. $this->csvFile
          . ' does not have any data.');
    } else {
      // Data returned is an object, but for later processing an array is
      // necessary, therefore convert to array.
      foreach($csvRecords as $offset => $record) {
        $this->csvRecords[$offset] = $record;
      }
    }
  }
  
  function handleArrayCsv($convArray)
  {
    $conv = '';
    if (!empty($convArray)) {
      if (is_array($convArray)) {
        $conv = implode('|', $convArray); // assuming that | is not used in any of the terms
      }
    }
    return $conv;
  }

  /**
   * Writes data into $csvFileName file using $dataHeader as header and also
   * as indicator which fields to take from the $writeData.
   * 
   * @param array $dataHeader
   * @param array $importData
   * @param string $csvFileName
   */
  function writeCSVData(
      array $dataHeader,
      array $importData,
      string $csvFileName)
  {
    $writer = Writer::createFromPath($csvFileName, 'w+');
    $writeData[] = $dataHeader;
    foreach($importData as $row=>$data) {
      $line = [];
      foreach($dataHeader as $headerField) {
        if (!isset($data[$headerField])) {
          $this->logger->critical("Data in row $row does not have "
            . "a field $headerField");
          // Do not quit the process, just add an empty value in the column
          $line[$headerField] = '';
        } else {
          $line[$headerField] = (
            is_array($data[$headerField])
              ?$this->handleArrayCsv($data[$headerField])
              :$data[$headerField]
          );
        }
      }
      if (!empty($line)) {
        $writeData[] = $line;
      }
    }
    $writer->insertAll($writeData);
  }

}
