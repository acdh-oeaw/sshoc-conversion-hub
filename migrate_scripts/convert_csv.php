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
  private $servicesFileConvert = 'data/convert/services.csv';
  private $communitiesFileConvert = 'data/convert/communities.csv';
  private $invocationsFileConvert = 'data/convert/invocations.csv';
  private $inputformatsFileConvert = 'data/convert/inputformats.csv';
  private $outputformatsFileConvert = 'data/convert/outputformats.csv';
  
  private $sourceFileImportColumns = [
    'title' => 3,
    'url' => 5,
    'description' => 6,
    'community' => 7,
    'invocation' => 8,
    'inputformats' => 13,
    'outputformats' => 15,
  ];
  
  private $sourceFileImportStartRow = 8;
  
  /**
   * Collects all services.
   * 
   * @var array
   */
  public $servicesList = [];
  
  /**
   * Collects all mentioned communities in the srevices.
   * 
   * @var array
   */
  public $communitiesList = [];
  
  /**
   * Collects all mentioned invocations in the srevices.
   * 
   * @var array
   */
  public $invocationsList = [];

  /**
   * Collects all mentioned inputformats in the srevices.
   * 
   * @var array
   */
  public $inputformatsList = [];

  /**
   * Collects all mentioned outputformats in the srevices.
   * 
   * @var array
   */
  public $outputformatsList = [];

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

  function getTaxonomy($terms, &$taxonomy, $separator = ';') {
    // currently semicolon as delimiter
    $extractedTerms = explode($separator, $terms);
    // we like to have a list of terms to create the taxonomy for it
    // also use this to find duplicates
    $checkedTerms = [];
    foreach($extractedTerms as $term) {
      $term = trim($term);
      if (!empty($term)) {
        $checkedTerms[] = $this->addTermInTaxonomy($term, $taxonomy);
      }
    }
    return $checkedTerms;
  }

  /**
   * taxonomies have this structure:
   *   name,description,count
   * 
   * @param type $addTerm
   *   adding this term to the taxonomy
   * @param type &$taxonomy
   *   which taxonomy to use
   *   write into it further data
   * @return id
   *   the id of the term used in the taxonomy
   */
  function addTermInTaxonomy($addTerm, &$taxonomy) {
    // check if there is a description (if there is a colon then use the text behind
    // the colon as description (but only the first colon)
    $addTerm = trim($addTerm);
    $description = strstr($addTerm, ':');
    if (!empty($description)) {
      // delete the colon and make a trim
      $description = trim(substr($description, 1));
      // alter the term name
      $addTerm = trim(substr($addTerm, 0, strpos($addTerm, ':')));
    }
    // harmonizing terms
    // terms should be no longer than 255 characters (maximum for name in drupal - todo: but i didn't check it, so possibly it is less, eg. 128)
    // here the limit is 127 characters, otherwise the term name is too long
    // if it is longer than 127 take the first four words and put the rest into the description
    if (strlen($addTerm) > 127) {
      print "WARNING: Limit of 127 for $addTerm <br />\n";
      $newTermName = '';
      $newTermDesc = $addTerm;
      $words = 0;
      while($word = strpos($newTermDesc, ' ')) {
        if ($words == 4 || strlen($newTermName) > 100) {
          break;
        }
        $newTermName .= substr($newTermDesc, 0, $word+1);
        $newTermDesc = substr($newTermDesc, $word+1);
        $words++;
      }
      if (!empty($newTermName)) {
        $addTerm = $newTermName;
        if (!empty($description)) {
          // simulate the colon that was there before
          $description = ': '. $description;
        }
        $description = $newTermDesc . $description;
      }
    }
    
    // if there is a point at the end of a term, kick the point out
    if ($addTerm[strlen($addTerm)-1] == '.') {
      $addTerm = trim(substr($addTerm, 0, strlen($addTerm)-1));
    }
    //if there is a ` at the beginning of a term, delete it (comes form gSpreadsheet)
    if ($addTerm[0] == '`') {
      $addTerm = trim(substr($addTerm, 1));
    }

    if (!empty($taxonomy)) {
      foreach($taxonomy as $offset => $termTaxonomy) {
        // todo: some more fancy checks on similarity
        if (strtolower($termTaxonomy['name']) == strtolower($addTerm)) {
          $taxonomy[$offset]['count']++;
          if (!empty($description)) {
            // if a description is already set, add a newline otherwise add the
            // description text
            if (!empty($taxonomy[$offset]['description']) && $taxonomy[$offset]['description'] != $description) {
              $taxonomy[$offset]['description'] .= "\n" . $description;
            } else {
              $taxonomy[$offset]['description'] = $description;
            }
          }
          // in the array we only like to have the referrer
          return $offset+1;
        }
      }
    }

    // if running until here, no term was found - add this one as a new term
    $newItem = [
      'name' => $addTerm,
      'description' => $description,
      'count' => 1
    ];
    $taxonomy[] = $newItem;
    
    // in the array we only like to have the referrer
    return count($taxonomy);
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
        case $sourceFileColumns['community']:
          $convdata['communities'] = $this->getTaxonomy($item, $this->communitiesList);
          break;
        case $sourceFileColumns['invocation']:
          $convdata['invocations'] = $this->getTaxonomy($item, $this->invocationsList);
          break;
        case $sourceFileColumns['inputformats']:
          $convdata['inputformats'] = $this->getTaxonomy($item, $this->inputformatsList, ',');
          break;
        case $sourceFileColumns['outputformats']:
          $convdata['outputformats'] = $this->getTaxonomy($item, $this->outputformatsList, ',');
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

  /**
   * Takes a taxonomy array and writes it into csv.
   * The taxonomy is limited to a specific array structure:
   *   [term-id => term-name]
   * 
   * @param array $taxonomy
   * @param string $csvFileName
   *   The name for the csv file to be written in the root directory
   */
  function writeTaxonomy($taxonomy, $csvFileName) {
    if (is_array($taxonomy)) {
      $records = [];
      $records[] = ['Id', 'Name', 'Description']; // title fields
      foreach($taxonomy as $term_id=>$term) {
        $records[] = [
          $term_id,
          $term['name'],
          $term['description'],
        ];
      }
      $writer = Writer::createFromPath($csvFileName, 'w+');
      /*print "<pre>";
      print_r($records);
      print "</pre>";*/
      $writer->insertAll($records);
      return true;
    }
    return false;
  }

  private function writeCSVTaxonomy($taxonomyTitle, $taxonomyList, $taxonomyFile) {
    print "===== $taxonomyTitle taxonomy list =====<br />\n";
    $csvList = [];
    foreach($taxonomyList as $termid=>$term) {
      print $term['name'] . ' | '
          . $term['description'] . ' | '
          . $term['count'] . "<br />\n";
      $csvList[$termid+1] = [
        'name' => $term['name'],
        'description' => $term['description']
      ];
    }
    $this->writeTaxonomy($csvList, $taxonomyFile);
    //var_dump($disciplineList);
  }

  function writeCSVServices($servicesList, $csvFileName) {
    if (is_array($servicesList)) {
      $records = [];
      $records[] = [
        'Id',
        'Title',
        'Description',
        'Accesspoints',
        'Communities',
        'Invocations',
        'Inputformats',
        'Outputformats',
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
          (!empty($service['communities']) && is_array($service['communities'])
              ? $this->handleArrayCsv($service['communities'])
              : ''),
          (!empty($service['invocations']) && is_array($service['invocations'])
              ? $this->handleArrayCsv($service['invocations'])
              : ''),
          (!empty($service['inputformats']) && is_array($service['inputformats'])
              ? $this->handleArrayCsv($service['inputformats'])
              : ''),
          (!empty($service['outputformats']) && is_array($service['outputformats'])
              ? $this->handleArrayCsv($service['outputformats'])
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
    $this->writeCSVServices($this->servicesList, $this->servicesFileConvert);
    $this->writeCSVTaxonomy('communities', $this->communitiesList, $this->communitiesFileConvert);
    $this->writeCSVTaxonomy('invocations', $this->invocationsList, $this->invocationsFileConvert);
    $this->writeCSVTaxonomy('inputformats', $this->inputformatsList, $this->inputformatsFileConvert);
    $this->writeCSVTaxonomy('outputformats', $this->outputformatsList, $this->outputformatsFileConvert);
  }
  
}

$createImport = new PrepareCSVForDrupal();
$createImport->createImport();
