<?php
/**
 * @Author: Klaus Illmayer, klaus.illmayer@oeaw.ac.at
 * 
 * MIT License
 * 
 * Copyright (c) 2021 ACDH-CH OEAW, Klaus Illmayer
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

/*
 * Installation notice:
 * 
 * - Use the CSV library from here: https://csv.thephpleague.com/
 *   Installation information: https://csv.thephpleague.com/9.0/installation/
 *   It is installed by calling "composer install"
 * - It is expected that sourcedata.csv already exists. This is a specific csv
 *   export from a gSpreadsheet created in SSHOC WP6, that holds a list of source
 *   to look at.
 */
require_once __DIR__.'/vendor/autoload.php';

use League\Csv\Reader;
use League\Csv\Writer;

class PrepareCSVForDrupal
{
  
  private $sourceFileImport = 'data/import/conversion_services.csv';
  private $servicesFileExport = 'data/convert/services.csv';
  
  private $sourceFileImportColumns = [
    'title' => 3,
    'url' => 5,
    'description' => 6,
    'community' => 7,
    'invocation' => 8,
  ];
  
  private $sourceFileImportStartRow = 8;
  
  /**
   * Collects all sources.
   * 
   * @var array
   */
  public $servicesList = [];
  
  /**
   * URLs can be more than one in the spreadsheet
   */
  function extractUrls($urls) {
    // Currently there is semicolon as delimiter.
    $extrUrls = explode(';', $urls);
    // check if the urls are valid
    $checkedUrls = [];
    foreach($extrUrls as $url) {
      // there is the possiblity of a title for the url, using > as delimiter
      $urlTitle = strstr($url, '>');
      if ($urlTitle !== false) {
        $url = substr($urlTitle, 1);
      }
      $url = trim($url);
      // could be that there are more than one spaces in between - ignore such cases
      if (!empty($url)) {
        if (filter_var($url, FILTER_VALIDATE_URL === FALSE)) {
          die("ERROR: $url is not a valid URL<br />");
        } else {
          $checkedUrls[] = $url;
        }
      }
    }
    return $checkedUrls;
  }

  private function analyzeExportRow($id, $row, $sourceFileColumns) {
    // running id: there is no unique id in the data itself, therefore take the linenumber
    // @todo: be aware that changes in the document may change the id: could be complicated if there are updateds planned
    $convdata = [
      'id' => $id,
    ];

    foreach($row as $column=>$item) {
      switch ($column) {
        case $sourceFileColumns['title']:
          // title of the service
          $convdata['title'] = $item;
          break;
        case $sourceFileColumns['url']:
          // link to the source (there could be more than one) - we need to harvest the title of it
          $convdata['accesspoints'] = $this->extractUrls($item);
          // we need at least one url - give an error, if this is not true
          if (empty($convdata['accesspoints'])) {
            print("ERROR: row $id does not have an (valid) accesspoint<br />");
            return [];
          }
          break;
        case $sourceFileColumns['description']:
          // description
          $convdata['description'] = $item;
          break;
      }
    }
    return $convdata;
  }

  private function readCSVExport(
      $sourceFile, $sourceFileColumns, $sourceFileStartRow)
  {
    $reader = Reader::createFromPath($sourceFile, 'r');
    
    $records = $reader->getRecords();
    foreach($records as $offset => $record) {
      if ($offset >= $sourceFileStartRow) {
        $service = $this->analyzeExportRow($offset, $record, $sourceFileColumns);
        if (!empty($service)) {
          $this->servicesList[] = $service;
        }
      }
    }
  }
  
  function handleArrayCsv($convArray) {
    $conv = '';
    if (!empty($convArray)) {
      if (is_array($convArray)) {
        $conv = implode('|', $convArray); // assuming that | is not used in any of the terms
      }
    }
    return $conv;
  }

  function writeCSVServices($servicesList, $csvFileName) {
    if (is_array($servicesList)) {
      $records = [];
      $records[] = [
        'Id',
        'Title',
        'Description',
        'Accesspoints',
      ];
      foreach($servicesList as $service) {
        // map now the service entry to the record
        $records[] = [
          $service['id'],
          $service['title'],
          $service['description'],
          (!empty($service['accesspoints']) && is_array($service['accesspoints'])
              ? $this->handleArrayCsv($service['accesspoints'])
              : ''),
        ];
      }
      $writer = Writer::createFromPath($csvFileName, 'w+');
      $writer->insertAll($records);
      return true;
    }
    return false;
  }

  public function createImport()
  {
    $this->readCSVExport(
        $this->sourceFileImport,
        $this->sourceFileImportColumns,
        $this->sourceFileImportStartRow
    );
    $this->writeCSVServices($this->servicesList, $this->servicesFileExport);
  }
  
}

$createImport = new PrepareCSVForDrupal();
$createImport->createImport();
