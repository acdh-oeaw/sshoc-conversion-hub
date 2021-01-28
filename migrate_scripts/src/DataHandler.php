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
  private $apiClass = null;
  private $vocabularyHandler = null;

  private $importData;
  
  public function __construct($configClass, $apiClass, $vocabularyHandler)
  {
    $this->configClass = $configClass;
    $this->apiClass = $apiClass;
    $this->vocabularyHandler = $vocabularyHandler;
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
      if (!isset($data['label'])) {
        // Label should be set.
        Logger::log("Label not set ($line)", __CLASS__.'.'.__FUNCTION__, true);
      }
      // It is possible that there is no name available in the spreadsheet
      // then ignore all the name relevant stuff
      if (isset($data['name'])) {
        if (empty($data['name'])) {
          // As this can happen from time to time, don't show it anymore (only
          // for debug) - name is not mandatory
          Logger::log("Name is empty ($line)", __CLASS__.'.'.__FUNCTION__);
        } elseif ($data['name'] != $data['label']) {
          // Label and name should be the same, otherwise necessary to check
          Logger::log("Name and Label not the same ($line): "
              . $data['name']." | " . $data['label'],
              __CLASS__.'.'.__FUNCTION__);
        }
      }
      
      // Check the category
      if (empty($data['category'])) {
        Logger::log("Category not set ($line)", __CLASS__.'.'.__FUNCTION__);
      } else {
        if (is_array($data['category'])) {
          Logger::log("Type is an array, currently not allowed, "
              . "taking only the first item ($line)",
              __CLASS__.'.'.__FUNCTION__);
          // Doing an adaption to the type: convert from array to single value
          // @todo: how to handle an array in a better way
          $data['category'] = $data['category'][0];
        }
        // For the curated items the object-type was used, which is now
        // deprecated, therefore map to the correct category and use further on
        $commonCategory = $this->configClass
          ->getConfig('registered_categories', true)[$data['category']];
        if (empty($commonCategory)) {
          Logger::log('Category '.$data['category']." not registerd ($line)",
              __CLASS__.'.'.__FUNCTION__, true);
        } else {
          $this->importData[$line]['category'] = $commonCategory;
        }
      }
      
      // check the description - it is mandatory
      if (empty($data['description'])) {
        Logger::log("Description not set ($line)",
            __CLASS__.'.'.__FUNCTION__);
      }
      
      // check informationContributor - as it is not mandatory only for debug
      // and it is not correct in the way how it is put into the spreadsheet
      // as informationContributor is the one who is ingesting the data
      /*if (empty($data['informationContributor'])) {
        Logger::log("No informationContributor ($line)",
            __CLASS__.'.'.__FUNCTION__);
      }*/
      
      // test if is accessibleat an url
      if (!empty($data['accessibleAt'])) {
        // it can happen, that accessibleAt does have more than one value,
        // so convert all values to an array - this is only to check the values
        // so no changes are necessary
        if (!is_array($data['accessibleAt'])) {
          $data['accessibleAt'] = [$data['accessibleAt']];
        }
        foreach($data['accessibleAt'] as $accessibleAt) {
          if (!filter_var($accessibleAt, FILTER_VALIDATE_URL)) {
            Logger::log("accessibleAt not a valid URL ($line): "
                . $accessibleAt, __CLASS__.'.'.__FUNCTION__);
          }
        }
      }
    }
  }

  /**
   * Creates a JSON compatible data structure
   * 
   * @param string $code
   *   code part of JSON
   * @param string $value
   *   value part of JSON
   * @return array
   *   JSON structure
   */
  private function createPropertyData($code, $value) {
    $propertyJson = [];
    if (!empty($code)) {
      if (!empty($value)) {
        $propertyJson = [
          'type' => [
            'code' => $code,
          ],
          'value' => $value,
        ];
      }
      else {
        Logger::log("No value set for code $code",
          __CLASS__.'.'.__FUNCTION__);
      }
    }
    else {
      if (!empty($value)) {
        Logger::log("No code set for value $value",
          __CLASS__.'.'.__FUNCTION__);
      }
      else {
        Logger::log("No code and no value set",
          __CLASS__.'.'.__FUNCTION__);
      }
    }
    return $propertyJson;
  }

  /**
   * Generic function to create a property entry in the importData-array.
   * The possible property types are defined at /api/property-types
   * @todo: Take from there the data type (currently this is manual in propertyType).
   * 
   * @param string $propertyKey
   *   The name of the property type (should be in line with the definitions in $this->csvStructure
   *   and it must be valid as an accepted property-type in the API.
   * @param type $dataKey
   *   Which line of the csv should be taken.
   */
  private function createProperty($propertyKey, $dataKey, $propertyType) {
    $createdStatus = false;
    if (empty($dataKey)) {
      Logger::log("[$propertyKey] No data key set",
          __CLASS__.'.'.__FUNCTION__);
    } elseif (empty($this->importData[$dataKey])) {
      Logger::log("[$propertyKey] Data key $dataKey not found in importData",
          __CLASS__.'.'.__FUNCTION__);
    } elseif (!isset($this->importData[$dataKey][$propertyKey])) {
      Logger::log("[$propertyKey] Property of $dataKey not set in importData",
          __CLASS__.'.'.__FUNCTION__);
    } else {
      if (!empty($this->importData[$dataKey][$propertyKey])) {
        // All checks are valid, use a local copy of the data to do the calculations.
        $data = $this->importData[$dataKey];

        // Handle single values: Transform them to an array, so that we always
        // have arrays.
        if (!is_array($data[$propertyKey])) {
          $data[$propertyKey] = [$data[$propertyKey]];
        }

        // Create the json to be delivered to the API
        $properties = [];
        foreach($data[$propertyKey] as $key => $property) {
          $property = trim($property);
          if (!empty($property)) {
            if ($propertyType == 'url'
                && (!filter_var($property, FILTER_VALIDATE_URL))) {
              Logger::log("[$propertyKey / $key] $property is not a valid URL",
                __CLASS__.'.'.__FUNCTION__);
            } else {
              // All checks worked, so add the property.
              $properties[] = $this->createPropertyData(
                  $propertyKey, $property);
            }
          } else {
            Logger::log("[$propertyKey / $key] Empty property",
              __CLASS__.'.'.__FUNCTION__);
          }
        }

        if (!empty($properties)) {
          // Only here the property is created and put into the data to import.
          $this->importData[$dataKey]['properties'] = array_merge(
              $this->importData[$dataKey]['properties'], $properties);
          $createdStatus = true;
        } else {
          Logger::log("[$propertyKey] Found no value for properties",
            __CLASS__.'.'.__FUNCTION__);
        }
      }
      // Delete the data out of the main data as it is now part of the properties.
      // So to have less noise in the json that is send to the API and to not
      // provoke errors due to same labels. 
      unset($this->importData[$dataKey][$propertyKey]);
    }
    return $createdStatus;
  }

  /**
   * Creates a JSON compatible data array for an actor
   * 
   * @param type $actor
   * @return array
   *   JSON compatible format
   */
  private function createActorData($actor) {
    $actorJson = [];
    // If there is a bracket, a website/email should be in the brackets.
    if (!empty($actor)) {
      if (($braceStart = strpos($actor, '(')) !== FALSE) {
        if (($braceEnd = strpos($actor, ')')) !== FALSE) {
          $url_email = trim(
              substr($actor, $braceStart + 1, $braceEnd - $braceStart - 1)
          );
          if (filter_var($url_email, FILTER_VALIDATE_URL)) {
            $actorJson['website'] = $url_email;
          }
          elseif (filter_var($url_email, FILTER_VALIDATE_EMAIL)) {
            $actorJson['email'] = $url_email;
          }
          else {
            Logger::log("$actor has neither url nor email in brackets",
            __CLASS__.'.'.__FUNCTION__);
          }

          $actor = trim(substr($actor, 0, $braceStart - 1)
              . substr($actor, $braceEnd + 1));
        }
        else {
          Logger::log("$actor misses end bracket",
            __CLASS__.'.'.__FUNCTION__);
          
          $actor = trim(substr($actor, 0, $braceStart - 1));
        }
      }
      $actorJson['name'] = $actor;
    }
    return $actorJson;
  }

  /**
   * Harmonize the data for the API input. As the input for API is JSON
   * some adaptions are necessary, e.g. creating array structures
   */
  public function harmonizeData() {
    foreach($this->importData as $line=>$data) {

      // Root field: hasContributor - create the contributors
      if (!empty($data['hasContributor'])) {
        // If there is only one contributor, there is no array, therefore 
        // create an array here, so that everything can be handled the same way.
        if (!is_array($data['hasContributor'])) {
          $data['hasContributor'] = [$data['hasContributor']];
        }
        // Then we need to create the json to be delivered to the API
        $contributors = [];
        foreach($data['hasContributor'] as $contributor) {
          $contributors[] = $this->createActorData($contributor);
        }
        $this->importData[$line]['hasContributor'] = $contributors;
      }
      
      // Create the properties.
      $this->importData[$line]['properties'] = [];

      // Property: keywords
      // @todo find a solution for userStory
      // workaround: inject userStory
      if (!empty($data['userStory'])) {
        if (empty($data['keyword'])) {
          $this->importData[$line]['keyword'] = [];
        }
        elseif (!is_array($data['keyword'])) {
          $this->importData[$line]['keyword'] =
              [$this->importData[$line]['keyword']];
        }
        $this->importData[$line]['keyword'][] = $data['userStory'];
        // Don't unset userStory, it is needed for relating items together
        //unset($this->importData[$line]['userStory']);
      }
      $this->createProperty('keyword', $line, 'string');
      
      // Property: repository-url
      $this->createProperty('repository-url', $line, 'url');

      // Property: wikidata-id
      $this->createProperty('wikidata-id', $line, 'url');

      // Property: thumbnail
      $this->createProperty('thumbnail', $line, 'url');

      // Property: media
      $this->createProperty('media', $line, 'url');
      
      // Property: media-caption
      $this->createProperty('media-caption', $line, 'string');

      // Property: see-also
      $this->createProperty('see-also', $line, 'url');

      // Property: web-usable
      $this->createProperty('web-usable', $line, 'string');

      // Property: terms-of-use
      $this->createProperty('terms-of-use', $line, 'string');

      // Property: tadirah-activity
      if (isset($data['tadirah-activity'])) {
        if (!empty($data['tadirah-activity'])) {
          $ta = $this->vocabularyHandler->searchVocabularyConcept(
              $data['tadirah-activity'], 'tadirah2'); // 'tadirah-activity');
          if (!empty($ta)) {
            $this->importData[$line]['properties'] =
                array_merge($this->importData[$line]['properties'], $ta);
          }
        }
        unset($this->importData[$line]['tadirah-activity']);
      }
      
      // Property: nemo-activity-type
      if (isset($data['nemo-activity-type'])) {
        if (!empty($data['nemo-activity-type'])) {
          $ta = $this->vocabularyHandler->searchVocabularyConcept(
              $data['nemo-activity-type'], 'nemo-activity-type');
          if (!empty($ta)) {
            $this->importData[$line]['properties'] =
                array_merge($this->importData[$line]['properties'], $ta);
          }
        }
        unset($this->importData[$line]['nemo-activity-type']);
      }

      // Property: language
      if (isset($data['language'])) {
        if (!empty($data['language'])) {
          $ta = $this->vocabularyHandler->searchVocabularyConcept(
              $data['language'], 'iso-639-3');
          if (!empty($ta)) {
            $this->importData[$line]['properties'] =
                array_merge($this->importData[$line]['properties'], $ta);
          }
        }
        unset($this->importData[$line]['language']);
      }
      
      // Attribute relatedItems
      /*if (isset($data['relatedItems'])) {
        $relatedItems = [];
        if (!is_array($data['relatedItems'])) {
          $data['relatedItems'] = [$data['relatedItems']];
        }
        foreach($data['relatedItems'] as $relatedItem) {
          $relatedItems[] = [
            'objectId' => $relatedItem,
            'relation' => ['code' => 'is-related-to']
          ];
        }
        $this->importData[$line]['relatedItems'] = $relatedItems;
      }*/

      // Property: license
      // @todo: why is license not in the vocabs concepts?
      // and why there are so many licenses missing?
      // We do have vocab licenses, that is connected to root.licenses;
      // vocab licenses-types however is connected to properties.license-type
      // but it can be any value in properties.license-type
      // therefore try to find a license from vocabulary licenses.
      // if not found go with license-type
      // and if also not found, write what is there.
      if (isset($data['license'])) {
        if (!empty($data['license'])) {
          $foundLicense = false;
          // @todo: There can be also multiple licenses
          // (is this allowed by the API, I don't think so)
          $ta = $this->vocabularyHandler->handleVocabularyConcepts(
            'licenses', false, $data['license'], 'license'
          );
          if (!empty($ta)) {
            // add the result in root/license-type
            //$this->importData[$line]['licenses'] = $ta;
            // new: add it into property terms-of-use (but only the name)
            // workaround as license does not work correctly
            foreach($ta as $t) {
              $this->importData[$line]['licenses'][] = [
                  'code' => $t['code']
              ];
              /*$this->importData[$line]['properties'][] = [
                  'type' => ['code' => 'terms-of-use'],
                  'value' => $t['label']
              ];*/
            }
            $foundLicense = true;
          }
          
          if (!$foundLicense) {
            $ta = $this->vocabularyHandler->handleVocabularyConcepts(
              'license-types', FALSE, $data['license'], 'license-type'
            );
            if (!empty($ta)) {
              $this->importData[$line]['properties'] =
                  array_merge($this->importData[$line]['properties'], $ta);
            } else {
              Logger::log("Didn't found a license for: ".(string)$data['license'],
                  __CLASS__.'.'.__FUNCTION__);
              //print_r($data['license']);
            }
          }
        }
        unset($this->importData[$line]['license']);
      }
      
      if (empty($data['dateLastUpdated'])) {
        unset($this->importData[$line]['dateLastUpdated']);
      }
      
      if (empty($data['dateCreated'])) {
        unset($this->importData[$line]['dateCreated']);
      }

      $this->createProperty('conference', $line, 'string');
      
      $this->createProperty('pages', $line, 'string');

      $this->createProperty('publication-type', $line, 'string');

      $this->createProperty('year', $line, 'string');
      
      $this->createProperty('publisher', $line, 'string');
    }
  }
  
  private function saveDataLocal() {
    Logger::log("Saving json data to import_data.json");
    $fh = fopen("import_data.json", "w") or die("Error writing json file");
    fwrite($fh, json_encode($this->importData, JSON_UNESCAPED_UNICODE));
    fclose($fh);
  }
  
  private function getCleanUrl($url)
  {
    // problem with url compare: if there is a difference, which version
    // to take? the one from api or the new one?
    // probably the best to use the cleanUrl: so if it is the same, do the cleanurl
    // or let there be a difference and clean it up in the spreadsheet - is better!
    /*$url = trim($url);
    if ($url[strlen($url)-1] == '/') {
      return substr($url, 0, -1);
    }*/
    return $url;
  }
  
  private function compareAccessibleAt($apiAccessibleAt, $dataAccessibleAt)
  {
    // accessibleAt can be more than one url, it is fine if any
    // of the urls fit.
    if (!is_array($apiAccessibleAt)) {
      $apiAccessibleAt = [$apiAccessibleAt];
    }
    foreach($apiAccessibleAt as $apiLink) {
      if (!is_array($dataAccessibleAt)) {
        $dataAccessibleAt = [$dataAccessibleAt];
      }
      foreach($dataAccessibleAt as $dataLink) {
        // Now compare the accessibleAt-urls
        // It could be that there is a whitespace or a slash at one
        // of the urls but not on the other - that means the url
        // is still the same and should be respected that way.
        Logger::log("urls: $apiLink / $dataLink");
        if ($this->getCleanUrl($apiLink) == $this->getCleanUrl($dataLink)) {
          return true;
        }
      }
    }
    return false;
  }
  
  /**
   * Looks if either the persistentId of an item is valid or if no
   * persistentId set in the spreadsheet, look if a search finds an item
   * that fits = (nearly) same label and same accessibleAt
   * If the update is fine, then persistentId is set and can be used to
   * distinguish to an insert.
   */
  public function checkForExistingData() {
    $needsCuration = false;
    foreach($this->importData as $line=>$data) {
      // need to find out, if it is an update or an insert
      $isUpdate = false;
      Logger::log("[$line] Item to search: " . $data['label']);
      // is a persistentId claimed?
      if (!empty($data['persistentId'])) {
        if (!empty($data['category'])) {
          // get the item
          $itemData = $this->apiClass->getItem(
              $data['persistentId'], $data['category']);
          if (empty($itemData)) {
            Logger::log("[$line] PersistentId " . $data['persistentId']
                . " not found", __CLASS__.'.'.__FUNCTION__, true);
          }
          // If found, check if the label / accessibleAt is the same
          // They can be changed so an exit would be wrong, but there
          // need to be another way of checking if this is not wrong.
          // @todo Maybe by a configuration setting?
          $strictCompare = $this->configClass
              ->getConfig('csv_strict_compare', false, true);
          if ($strictCompare && $itemData['label'] != $data['label']) {
            Logger::log("[$line Curation] PersistentId " . $data['persistentId']
                . ": label not the same (" . $itemData['label'] . "/"
                . $data['label'] . ")");
            $needsCuration = true;
          } elseif ($strictCompare
                && !$this->compareAccessibleAt($itemData['accessibleAt'],
                    $data['accessibleAt'])) {
            Logger::log("[$line Curation] PersistentId " . $data['persistentId']
                . ": accessibleAt not the same ("
                . print_r($itemData['accessibleAt'], true)
                . "/" . $data['accessibleAt'] . ")");
            $needsCuration = true;
          } else {
            Logger::log("[$line] Equal to persistentId "
                . $data['persistentId']);
            $isUpdate = true;
          }
          // @todo: using persistentId further on - what if nothing found,
          // always exit or allow to delete persistentId? Currently it means
          // that if a persistentId does not work there needs to be adaptions
          // on the spreadsheet and a new export.
        } else {
          Logger::log("[$line Curation] No category claimed for persistentId "
            . $data['persistentId']);
          $needsCuration = true;
        }
      } elseif (!empty($data['sourceId']) || !empty($data['sourceItemId'])) {
        Logger::log("[$line] persistentId not set "
            . " but sourceId " . $data['sourceId']
            . " and/or sourceItemId " . $data['sourceItemId']);
        $needsCuration = true;
      }
      
      if (!$isUpdate) {
        // Either no persistentId is there or we didn't found an item to
        // a persistentId (or something else happend)
        // Use the search by label function to find items that may fit to
        // the current items.
        if (!empty($data['label'])) {
          $searchResults = $this->apiClass->searchForItem($data['label']);
          if (!empty($searchResults['hits'])) {
            // Found something, go through the list (limited to 20 items)
            // and look if there is something that fits.
            $hits = $searchResults['hits'];
            Logger::log("[$line] $hits search result(s) for items with label: "
              . $data['label']);
            // now it depends: only take items with the same accessibleAt
            // delete possible slashs on the end of the urls, just to be sure
            foreach($searchResults['items'] as $item) {
              // @todo: currently the result is without accessibleAt,
              // therefore it is necessary to load every item
              $itemData = $this->apiClass->getItem(
                  $item['persistentId'], $item['category']);

              if (empty($itemData['accessibleAt'])) {
                Logger::log("[$line] itemData has no accessibleAt");
              } elseif (empty($data['accessibleAt'])) {
                Logger::log("[$line] data has no accessibleAt");
              } else {
                // accessibleAt can be more than one url, it is fine if any
                // of the urls fit.
                if ($this->compareAccessibleAt($itemData['accessibleAt'],
                    $data['accessibleAt'])) {
                  Logger::log("[$line] Found an item that has the same "
                    . "accessibleAt, persistentId: " . $item['persistentId']);
                  $isUpdate = true;
                  $this->importData[$line]['persistentId'] =
                      $item['persistentId'];
                  $this->importData[$line]['category'] =
                      $item['category'];
                  break;
                }
              }
            }
            if (!$isUpdate) {
              Logger::log("[$line Curation] none of the search results fit");
              $needsCuration = true;
            }
          }
        }
      }
      if ($isUpdate) {
        Logger::log("[$line] is an Update");
      } else {
        Logger::log("[$line] is an Insert");
      }
    }
    if ($needsCuration) {
      Logger::log("Curation is needed", "Curation", true);
      // @todo some of the curations should be possible to override
      // use a configuration setting for this
    }
  }
  
  /**
   * Add the keyword 'recommended' to all elements
   * This will push the items into the recommended section
   */
  public function addRecommended()
  {
    if ($this->configClass->getConfig('add_recommended', false, false)) {
      foreach($this->importData as $line=>$data) {
        // check if properties is already created
        if (!isset($data['properties'])) {
          $this->importData[$line]['properties'] = $data['properties'] = [];
        }
        // check if recommended is alreay present as keyword
        $found = false;
        foreach($data['properties'] as $dataProperty) {
          if ($dataProperty['type']['code'] == 'keyword'
              && $dataProperty['value'] == 'recommended') {
            $found = true;
            break;
          }
        }
        // only add recommended if it is not already set
        if (!$found) {
          $this->importData[$line]['properties'][] = [
                'type' => ['code' => 'keyword'],
                'value' => 'recommended'];
          Logger::log('added recommended');
        } else { Logger::log('not added recommended'); }
      }
    } else {
      Logger::log('recommended not set, no recommendations made');
    }
  }
  
  public function mergeUpdates()
  {
    $curationNeeded = false;
    foreach($this->importData as $line=>$data) {
      if (!empty($data['persistentId'])) {
        Logger::log("[$line] update found for " . $data['persistentId']);
        // now get the current data
        $apiData = $this->apiClass->getItem($data['persistentId'],
            $data['category']);
        // now compare
        // we need to do this manually, as there are are some specifica
        // label and description goes as it is in the spreadsheet
        // version and licenses is to be ignored
        // accessibleAt is checked: give an information if there is a difference
        foreach($apiData['accessibleAt'] as $apiAccessibleAt) {
          if (!in_array($apiAccessibleAt, $data['accessibleAt'])) {
            Logger::log("[$line] API accessibleAt $apiAccessibleAt not present");
            $curationNeeded = true;
          }
        }
        // source is checked and sourceItemId if there is a difference
        if (empty($apiData['source']['id'])
            || ($apiData['source']['id'] != $data['sourceId'])) {
          Logger::log("[$line] Source ID is different between API ("
              . $apiData['source']['id'] . ") and spreadsheet ("
              . $data['sourceId'] . ")");
          $curationNeeded = true;
        }
        if ($apiData['sourceItemId'] != $data['sourceItemId']) {
          Logger::log("[$line] SourceItemId is different between API ("
              . $apiData['sourceItemId'] . ") and spreadsheet ("
              . $data['sourceItemId'] . ")");
          $curationNeeded = true;
        }
        // check contributors if someone is missing
        foreach($apiData['contributors'] as $apiContributor) {
          $contributorName = $apiContributor['actor']['name'];
          $found = false;
          foreach($data['hasContributor'] as $dataContributor) {
            if ($dataContributor['name'] == $contributorName) {
              $found = true;
              break;
            }
          }
          if (!$found) {
            Logger::log("[$line] Contributor $contributorName "
              . "not in spreadsheet");
            $curationNeeded = true;
          }
        }
        
        // check properties if something is missing
        foreach($apiData['properties'] as $apiProperty) {
          $typeCode = $apiProperty['type']['code'];
          $typeType = $apiProperty['type']['type'];
          $typeValue = $apiProperty['value'];
          if ($typeType == 'concept') {
            $typeValue = $apiProperty['concept']['code'];
          }
          $found = false;
          foreach($data['properties'] as $dataProperty) {
            //print_r($dataProperty);
            if ($dataProperty['type']['code'] == $typeCode) {
              if ($typeType == 'concept'
                  && ($dataProperty['concept']['code'] == $typeValue)) {
                $found = true;
                break;
              } elseif (isset($dataProperty['value'])
                  && ($dataProperty['value'] == $typeValue)) {
                $found = true;
                break;
              }
            }
          }
          if (!$found) {
            Logger::log("[$line] Property $typeCode ($typeType) "
              . "with value $typeValue not in spreadsheet");
            $curationNeeded = true;
            // todo: add the properties (depending from a setting in log)
          }
        }
        // check relatedItems if something is missing
        foreach($apiData['relatedItems'] as $relatedItems) {
          Logger::log("[$line] There is a relatedItem "
            . $relatedItems['persistentId']);
          $curationNeeded = true;
        }
        /*print "data: ";
        print_r($data);
        print "apiData: ";
        print_r($apiData);*/
      } else {
        Logger::log("[line] insert found");
      }
    }
    if ($curationNeeded) {
      $strictMerge = $this->configClass
          ->getConfig('merge_strict_compare', false, true);
      if ($strictMerge) {
        Logger::log("Curation needed for merge", 'Curation', true);
      }
    }
  }

  public function prepareDataForCompare()
  {
    // there is some stuff to do before we can send it to the api
    foreach($this->importData as $line=>$data) {
      // accessibleAt should be an array
      if (!is_array($data['accessibleAt'])) {
        $this->importData[$line]['accessibleAt'] = [$data['accessibleAt']];
      }
    }
  }

  public function prepareDataForIngest()
  {
    // there is some stuff to do before we can send it to the api
    $sourceLabel = $this->configClass->getConfig('source_label', true);
    $sourceUrl = $this->configClass->getConfig('source_url', true);
    $sourceTemplate = $this->configClass->getConfig('source_template', true);
    foreach($this->importData as $line=>$data) {
      if (!empty($data['sourceId'])) {
        // reuse the source but put it in the correct format
        $this->importData[$line]['source'] = ['id' => $data['sourceId']];
      } else {
        // no sourceId set, take the one from the configuration
        $sourceId = $this->apiClass->getSourceId($sourceLabel);
        if ($sourceId === false) {
          // source does not exist, create it
          $sourceId = $this->apiClass->createSource($sourceLabel, $sourceUrl,
              $sourceTemplate);
        }
        if ($sourceId !== false) {
          // add the source to the item
          $this->importData[$line]['source'] = ['id' => $sourceId];
          $this->importData[$line]['sourceItemId'] = $line;
          Logger::log("using sourceId: $sourceId for insert item");
        }
        else {
          Logger::log("Couldn't detect or create a source for insert item",
              __CLASS__.'.'.__FUNCTION__, true);
        }
      }
      unset($this->importData[$line]['sourceId']);
      
      // create the contributors
      if (!empty($data['hasContributor'])) {
        $this->importData[$line]['contributors'] = [];
        foreach($data['hasContributor'] as $contributor) {
          // get the id of an actor
          if (!empty($contributor['name'])) {
            $actorId = $this->apiClass->getActorId($contributor['name']);
            if (!empty($actorId)) {
              $this->importData[$line]['contributors'][] = [
                  'actor' => [
                    'id' => $actorId,
                  ],
                  'role' => [
                    'code' => 'contributor',
                  ]
              ];
            } else {
              // found no actorid, create a new actor
              Logger::log("[$line] create new actor for ".$contributor['name']);
              $newActor =
                  $this->apiClass->createActor(['name' => $contributor['name']]);
              print_r($newActor);
              if (!empty($newActor['id'])) {
                $this->importData[$line]['contributors'][] = [
                    'actor' => [
                      'id' => $newActor['id'],
                    ],
                    'role' => [
                      'code' => 'contributor',
                    ]
                ];
              } else {
                Logger::log("[$line] Creation of actor name "
                    . $contributor['name'] . " did not work",
                    __CLASS__.'.'.__FUNCTION__, true);
              }
            }
          } else {
            Logger::log("[$line] No actor name set",
                __CLASS__.'.'.__FUNCTION__, true);
          }
        }
      }
      unset($this->importData[$line]['hasContributor']);
    }
    $this->saveDataLocal();
  }

  public function ingestData()
  {
    foreach($this->importData as $line=>$data) {
      if (!empty($data['category'])) {
        $relatedItems = [];
        if (!empty($data['relatedItems'])) {
          if (!is_array($data['relatedItems'])) {
            $relatedItems = [$data['relatedItems']];
          } else {
            $relatedItems = $data['relatedItems'];
          }
        }
        unset($data['relatedItems']);
        $ret = $this->apiClass->ingestItem($data['category'], $data);
        print_r($ret);
        foreach($relatedItems as $relateItem) {
          // get the item to get the id
          $this->apiClass->relateItems($data['persistentId'], $relateItem);
        }
      } else {
        Logger::log("[$line] No category set",
            __CLASS__.'.'.__FUNCTION__, true);
      }
    }
  }
}
