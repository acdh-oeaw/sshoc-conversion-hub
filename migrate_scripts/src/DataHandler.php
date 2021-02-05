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

/**
 * Handling of the imported data: cleaning, preparing and enrichen it.
 */

class DataHandler
{
  private $configClass = null;
  private $vocabularyHandlerFormatsExtern = null;
  
  private $vocabularyHandlerCommunities = null;
  private $vocabularyHandlerInvocations = null;

  private $importData;
  
  public function __construct($configClass, $vocabularyHandlerFormats)
  {
    $this->configClass = $configClass;
    $this->vocabularyHandlerFormatsExtern = $vocabularyHandlerFormats;

    $this->vocabularyHandlerCommunities = new VocabularyHandler('communities');
    $this->vocabularyHandlerInvocations = new VocabularyHandler('invocations');
  }
  
  /**
   * Take the import data and check it if it is well formed. Do also for
   * some checks an automatic adaption 
   * There are some mandatory fields that are necessary to recognize.
   * Take this as a hint for doing adaptions on the spreadsheet file.
   */
  public function checkImportData($importData)
  {
    // @todo: create dedicated methods for loading importData
    $this->importData = $importData;
    // @todo: check if importData is empty

    foreach($this->importData as $line=>$data) {
      // Check the name/label settings
      if (!isset($data['title'])) {
        // Title should be set.
        Logger::log("Title not set ($line)", __CLASS__.'.'.__FUNCTION__, true);
      }
      
      // Check if an url is valid
      if (!empty($data['url'])) {
        // it can happen, that url does have more than one value,
        // so convert all values to an array - this is only to check the values
        // so no changes are necessary
        if (!is_array($data['url'])) {
          $data['url'] = [$data['url']];
        }
        foreach($data['url'] as $accessibleAt) {
          if (!filter_var($accessibleAt, FILTER_VALIDATE_URL)) {
            Logger::log("accessibleAt not a valid URL ($line): "
                . $accessibleAt, __CLASS__.'.'.__FUNCTION__);
          }
        }
      }
    }
  }

  private function addTermsToVocabulary(VocabularyHandler $vocabulary, $terms) {
    if (!is_array($terms)) {
      $terms = [$terms];
    }
    foreach($terms as $term) {
      $vocabulary->addTerm($term);
    }
  }

  /**
   * Harmonize the data for the migrate import. In the end it should be a csv.
   * Some adaptions are necessary, e.g. creating array structures
   */
  public function harmonizeData() {
    foreach($this->importData as $line=>$data) {
      // Create the vocabularies for community and for invocation.
      if (!empty($data['community'])) {
        $this->addTermsToVocabulary($this->vocabularyHandlerCommunities,
            $data['community']);
      }
      if (!empty($data['invocation'])) {
        $this->addTermsToVocabulary($this->vocabularyHandlerInvocations,
            $data['invocation']);
      }
    }
    //print_r($this->importData);
  }
  
  public function showVocabularyData()
  {
    $this->vocabularyHandlerCommunities->showVocabularyTerms();
    $this->vocabularyHandlerInvocations->showVocabularyTerms();
  }
  
  private function saveDataLocal() {
    Logger::log("Saving json data to import_data.json");
    $fh = fopen("import_data.json", "w") or die("Error writing json file");
    fwrite($fh, json_encode($this->importData, JSON_UNESCAPED_UNICODE));
    fclose($fh);
  }
  
}
