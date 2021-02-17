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

  public function __construct(LoggerInterface $logger)
  {
    $this->logger = $logger;
  }
  
  /**
   * Load the data from the csvFile. It can have a hasHeader with the names
   * of the columns and it can be processed from a specific row on (due to
   * having explanations in the first rows of the spreadsheet).
   * 
   * @return array
   */
  public function loadCsvData(string $csvFile, bool $hasHeader = true): array
  {
    $csvRecords = [];
    if (!file_exists($csvFile)) {
      throw new \Exception("Import file $csvFile is missing.");
    }
    $csv = Reader::createFromPath($csvFile, 'r');
    if($hasHeader) {
      $csv->setHeaderOffset(0);
    }
    $csvData = $csv->getRecords();
    if (empty($csvData)) {
      throw new \Exception("Import file $csvFile has no data.");
    } else {
      // Data returned is an object, but for later processing an array is
      // necessary, therefore convert to array.
      // @todo: csvData may not be necessary.
      foreach($csvData as $offset => $record) {
        $csvRecords[$offset] = $record;
      }
    }
    return $csvRecords;
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
