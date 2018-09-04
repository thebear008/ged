<?php
class DB extends SQLite3 {

  public $log = False;
  public $jsonArray = False;

  /**
   * @return void
   **/
  public function init($specialTags = false) {
    $this->log(sprintf("init table myFiles, myTags, tags_files"));
    $this->exec('CREATE TABLE if not exists myFiles (label STRING, slug STRING PRIMARY KEY)');
    $this->exec('CREATE TABLE if not exists myTags (tag STRING, slug STRING PRIMARY KEY, top_tag STRING)');
    $this->exec('CREATE TABLE if not exists tags_files (file_slug STRING, tag_slug STRING)');
    $this->exec('CREATE TABLE if not exists files_info (file_slug STRING, year STRING, month STRING, day STRING, timestamp STRING)');

    // create specialTags if don't exist
    foreach ($specialTags as $tag) {
      $this->log(sprintf("Managing special tag : %s", $tag));
      try {
        $this->addTag($tag);
      } catch (Exception $e) {
        $this->log(sprintf("%s", $e->getMessage()));
      }
    }
  }


  /**
   * @return void
   * @param string
   * write log with special prefix
   **/
  public function log($string) {
    $this->log->write($string, 'DB');
  }

  /**
   * @return void
   * @param Log object
   **/
  public function setLogger($log) {
    $this->log = $log;
  }

  /**
   * @return void
   * @param array $jsonArray
   **/
  public function setJsonArray($jsonArray) {
    $this->jsonArray = $jsonArray;
  }

  /**
   * @return Boolean
   * @param string $filename
   **/
  public function isVideo($filename) {
    if (in_array(strtolower(substr($filename, -3)), $this->jsonArray['videosExtentions'])) {
        return True;
    }
    return False;
  }

  /**
   * @return Boolean
   * @param string $filename
   **/
  public function isGif($filename) {
    if (in_array(strtolower(substr($filename, -3)), array("gif"))) {
        return True;
    }
    return False;
  }


  /**
   * @return void
   * @param array $jsonTags
   * @param string $parent
   **/
  public function loadTags($jsonTags, $parent = False) {
    $this->log(sprintf("Loading Tags (length %d)", sizeof($jsonTags)));
    foreach ($jsonTags as $key => $value) {
      if (!$parent) {
        $this->addTag($key);
        if (is_array($value)) {
          $this->loadTags($value, $key);
        }
      } else {
        $this->addTag($key, $parent);
        if (is_array($value)) {
          $this->loadTags($value, $key);
        }
      }
    }
  }

  /**
   * @return void
   **/
  public function cleanTagsFiles() {
    $this->log("Deleting links between tags and files if slug is missing");
    $this->exec("DELETE FROM tags_files where tag_slug not in (select slug from myTags)");
    $this->exec("DELETE FROM tags_files where file_slug not in (select slug from myFiles)");
  }


  /**
   * @return void
   * @param string $folderPath configDirectory
   * @param array $jsonArray
   * delete tags_files records if files are linked to non-leaf-tag
   * */
  public function cleanDb($folderPath, $jsonArray) {
    $this->log("delete tags_files records if files are linked to non-leaf-tag");
    $this->exec("DELETE FROM tags_files where tag_slug in (select top_tag from myTags where top_tag is not null)");

    # delete orphan thumbnails
    $this->log("Delete orphan thumbnails");
    $this->deleteOrphanThumbnails($folderPath, $jsonArray['thumbnailsDirectory'], $jsonArray['urlRootThumbnails']);
  }

  /**
   * @return void
   **/
  public function dropTags() {
    $this->log('Deleting all tags');
    $this->exec('DELETE FROM myTags');
  }

  /**
   * @return void
   **/
  public function dropFiles() {
    $this->log('Deleting all files from DB');
    $this->exec('DELETE FROM myFiles');
  }

  /**
   * @return void
   **/
  public function dropFilesInfo() {
    $this->log('Deleting all files info from DB');
    $this->exec('DELETE FROM files_info');
  }


  /**
   * @return void
   * @param string $myTag
   * @param string $myTagParent
   **/
  public function addTag($myTag, $myTagParent = False) {
    $this->log(sprintf('Adding tag %s with parent %s',$myTag, $myTagParent));
    $arrayResult = $this->querySingle(sprintf('select * from myTags where slug = "%s"', slugify($myTag)));
    if (!empty($arrayResult)) {
      throw new Exception(sprintf("ERROR : tag '%s' is already present into database.", $myTag));
    } else {
      if ($myTagParent) {
        # create tag with parent
        $arrayResult = $this->querySingle(sprintf('select * from myTags where slug = "%s"', slugify($myTagParent)));
        if (empty($arrayResult)) {
          die(sprintf("ERROR : tagParent '%s' is not present into database.", $myTagParent));
        } else {
          $this->exec(sprintf('insert into myTags (tag, slug, top_tag) values ("%s", "%s", "%s")', $myTag, slugify($myTag), slugify($myTagParent))); 
        }
      } else {
        # create tag without parent
        $this->exec(sprintf('insert into myTags (tag, slug, top_tag) values ("%s", "%s", null)', $myTag, slugify($myTag))); 
      }
    }
  }


  /**
   * @return void
   * @param string $folderPath
   **/
  public function loadFiles($folderPath) {
    $this->log(sprintf("Loading Files from folder : %s", $folderPath));
    if (!is_readable($folderPath)) {
      die(sprintf("Folder '%s' is not readable", $folderPath));
    }
    if (!is_writable($folderPath)) {
      die(sprintf("Folder '%s' is not writable", $folderPath));
    }
    $listFolder = scandir($folderPath);

    foreach ($listFolder as $content) {
      $this->log(sprintf("Managing file %s", $content));
      # filter hidden content
      if (substr($content, 0, 1) != ".") {
        if (!is_dir($folderPath . DIRECTORY_SEPARATOR . $content)) {
          $mySlugifiedText = slugify($content);
          $this->log(sprintf("Integrating file %s with slug %s", $content, $mySlugifiedText));
          $arrayResult = $this->querySingle(sprintf('select * from myFiles where slug = "%s"', $mySlugifiedText));
          if (empty($arrayResult)) {
            $this->exec(sprintf('insert into myFiles (label, slug) values ("%s", "%s")', $content, $mySlugifiedText));
          }
          #############
          # files_info
          $this->log(sprintf("Inserting files_info for file %s", $content));
          $creation_timestamp = filectime($folderPath . DIRECTORY_SEPARATOR . $content);
          $year = date("Y", $creation_timestamp);
          $month = date("m", $creation_timestamp);
          $day = date("d", $creation_timestamp);
          $this->exec(sprintf('insert into files_info (file_slug, year, month, day, timestamp) values ("%s", "%s", "%s", "%s", "%s")',
            $mySlugifiedText,
            $year,
            $month,
            $day,
            $creation_timestamp));
          # END files_info
          ################

          ############################
          # detect_tampermonkey_files
          $tamper_monkey_values = $this->detect_tampermonkey_files($content);
          if ($tamper_monkey_values) {
            $this->log(sprintf("Tampermonkey file detected with values %s", print_r($tamper_monkey_values, true)));
            $year = slugify($tamper_monkey_values['year']);
            $author = slugify($tamper_monkey_values['author']);

            try {
              # create $year tag
              $this->addTag($year, $this->jsonArray['specialTags']['date']);
            } catch (Exception $e) {
              # tag already present
              $this->log(sprint("%s", $e->getMessage()));
            }

            try {
              # create $author tag
              $this->addTag($author, $this->jsonArray['specialTags']['authors']);
            } catch (Exception $e) {
              # tag already present
              $this->log(sprint("%s", $e->getMessage()));
            }

            $this->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $mySlugifiedText, $year));
            $this->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $mySlugifiedText, $author));

          } else {
            $this->log(sprintf("Tampermonkey file pattern did not match for %s", $content));
          }
          # END detect_tampermonkey_files
          ################################
        } else {
          $this->log(sprintf("Ignoring folder %s", $content));
        }
      } else {
        $this->log(sprintf("Ignoring file %s", $content));
      }
    }
  }

  /**
   * @return string
   * @param string $parent
   * @param boolean $checkBoxFlag
   * @param string $slugFile
   * @param array $arraySlugTags (selected Tags for file)
   * @param boolean $showSearchButton
   * */
  public function showTagTree($parent = False, $checkBoxFlag = False, $slugFile = False, $arraySlugTags = False, $showSearchButton = False) {
    $this->log("Showing Tag Tree");
    $string = "";
    $string .= "<ul>";

    if ($parent) {
      $result = $this->query(sprintf("select slug from myTags where top_tag = '%s' order by tag", $parent));
    } else {
      $result = $this->query("select slug from myTags where top_tag is null order by tag");
    } 
    while ($myResult = $result->fetchArray()){

        if ($parent == False && $checkBoxFlag != False) {
            $string .= "<div class='tag_tree_3rd_col'>";
        }


      # add CSS class to tag with children
      $classTagWithChildren = "";
      $hasChild = False;
      if ($this->hasChild($myResult[0])) {
        $classTagWithChildren = "tag-with-children";
        $hasChild = True;
      }


      if ($checkBoxFlag) {
        # option to add checkBox to tag the current file
        $checkedBoolean = '';
        if (in_array($myResult[0], $arraySlugTags)) { $checkedBoolean = 'checked'; }

        # tags with children cannot be linked to media
        if ($hasChild) {
          $string .= sprintf(
                "<li class='no_link'>
                    <label for='my-input-%s'>
                    <input class='no_link' id='my-input-%s'  type='checkbox' value='' />
                    <span class='no_link'>%s</span>
                    </label>
                </li>",
                $myResult[0],
                $myResult[0],
                $myResult[0]
                );

        } else {
          $string .= sprintf(
                "<li class='%s' >
                    <label for='my-input-%s'>
                    <input class='new_third_col' %s id='my-input-%s'  type='checkbox' value='%s' onclick='linkTagToFile(this, \"%s\")' />
                    <span class='new_third_col'>%s</span>
                    </label>
                </li>",
                $classTagWithChildren,
                $myResult[0],
                $checkedBoolean,
                $myResult[0],
                $myResult[0],
                $slugFile,
                $myResult[0]
                );
        }
      } elseif ($showSearchButton) {
        # option to add search button 
        $string .= sprintf("<li class='%s' >%s %s</li>", $classTagWithChildren, $myResult[0], $this->getSearchButtons($myResult[0]));
      } else {
        $string .= sprintf("<li class='%s' >%s</li>", $classTagWithChildren, $myResult[0]);
      }
      $string .= $this->showTagTree($myResult[0], $checkBoxFlag, $slugFile, $arraySlugTags, $showSearchButton);

        if ($parent == False && $checkBoxFlag != False) {
            $string .= "</div>";
        }
    }
    $string .= "</ul>";

    return $string;
  }

  /**
   * @param $tag string
   * @return array
   * */
  public function getTagAndChildren($tag) {
    $this->log("Getting Tag and Children");
    # detect all-files
    # ################
    if ($tag == "all-files") {
      return $tag;
    }
    $return = array();
    $arrayResult = $this->querySingle(sprintf('select slug from myTags where slug = "%s"', slugify($tag)));
    if (empty($arrayResult)) {
      throw new Exception(sprintf("Tag '%s' unknown", $tag));
    }

    $return[$arrayResult] = "'$arrayResult'";
    $flag = True;
    while ($flag) {
      $flag = False;
      $total = count($return);
      $result = $this->query(sprintf("select slug from myTags where top_tag IN (%s) order by slug ", implode(",", $return)));
      while ($myResult = $result->fetchArray()){
        $return[$myResult[0]] = "'$myResult[0]'";
      }
      if ($total < count($return)) { $flag = True; }
    }

    return $return;
  }

  /**
   * @param $tag string
   * @return boolean
   * */
  public function hasChild($tag) {
    $this->log(sprintf("Test if tag %s has child", $tag));
    $result = $this->query(sprintf("select slug from myTags where top_tag = '%s'", $tag));
    return ($result->fetchArray());
  }

  /**
   * @param $tag string
   * @param $print boolean
   * @return void
   * */
  public function deleteAllFromTag($tag, $print = True){
    $this->log(sprintf("Deleting everything about tag %s", $tag));
    # delete records from tags_files table
    $this->query(sprintf("DELETE FROM tags_files where tag_slug = '%s'", slugify($tag)));

    if ($print) {
      echo sprintf("%d records erased from tags_files with tag '%s' \n", $this->changes(), $tag);
    }

    # delete record from myTags table
    $this->query(sprintf("DELETE FROM myTags where slug = '%s'", slugify($tag)));

    if ($print) {
      echo sprintf("%d records erased from myTags with tag '%s' \n", $this->changes(), $tag);
    }
  }

  /**
   * @return array
   * @param string $slug
   **/
  public function getTagsFromFile($slug) {
    $this->log(sprintf("Getting tags for file %s", $slug));
    $result = $this->query(sprintf("select tag_slug from tags_files where file_slug = '%s'", $slug));
    $array = array();
    while ($myResult = $result->fetchArray()){
      $array[$myResult[0]] = $myResult[0];
    }
    return $array;
  }

  /**
   * @return array
   * @param string $slug
   * */
  public function getFile($slug) {
    $this->log(sprintf("Getting info file from its slug %s", $slug));
    $arrayResult = $this->querySingle(sprintf('select * from myFiles where slug = "%s"', $slug), true);
    if (!empty($arrayResult)) {
      return $arrayResult;
    } else {
      return False;
    }
  }

  /**
   * @return void
   * @param string $slug
   * @param array $jsonConfig
   * */
  public function deleteFile($slug, $jsonConfig) {
    $this->log(sprintf("Deleting file and thumbnail from slug %s", $slug));
    if ($arrayResult = $this->querySingle(sprintf('select * from myFiles where slug = "%s"', $slug), true)) {
      $this->query(sprintf("DELETE FROM myFiles where slug = '%s'", $slug));
      $this->query(sprintf("DELETE FROM tags_files where file_slug = '%s'", $slug));

      # delete file on FS
      $this->log(sprintf("Trying to delete %s", $_SESSION['configDirectory'] . DIRECTORY_SEPARATOR . $arrayResult['label']));
      @unlink($_SESSION['configDirectory'] . DIRECTORY_SEPARATOR . $arrayResult['label']);
      # delete thumbnails
      foreach ($jsonConfig['allowedExtensions'] as $allowedExtension) {
        $myExplode = explode(".", $arrayResult['label']);
        array_pop($myExplode);
        $myExplode[] = $allowedExtension;
        $filename = implode(".", $myExplode);
        $this->log(sprintf("Trying to delete thumbnail %s", $_SESSION['configDirectory'] . DIRECTORY_SEPARATOR . $jsonConfig['thumbnailsDirectory'] . DIRECTORY_SEPARATOR . $filename));
        @unlink($_SESSION['configDirectory'] . DIRECTORY_SEPARATOR . $jsonConfig['thumbnailsDirectory'] . DIRECTORY_SEPARATOR . $filename);
      }
    }
  }


  /**
   * @return array fileSlugs
   * @param array $tagSlugs with '' : 'tag1', 'tag2', ...
   * */
  public function getFilesFromSlugs($tagSlugs) {
    $this->log(sprintf("Getting files from tags slugs %s", (is_array($tagSlugs) ? implode(",", $tagSlugs) : $tagSlugs)));
    # detect all-files
    # ################
    if ($tagSlugs == "all-files") {
      $sql = sprintf("SELECT slug from myFiles");
    } else {
      $sql = sprintf("SELECT file_slug from tags_files where tag_slug in (%s)", implode(",", $tagSlugs));
    }
    $result = $this->query($sql);
    $fileSlugs = array();
    while ($myResult = $result->fetchArray()){
      $fileSlugs[$myResult[0]] = $myResult[0];
    }
    return $fileSlugs;
  }

  /**
   * @return array fileSlugs
   * */
  public function getFilesWithoutTags($pictures_only = '', $videos_only = '', $gif_only='') {
    $this->log("Getting files without tags");
    $sql = sprintf("SELECT slug  FROM myFiles WHERE slug not in (SELECT file_slug from tags_files )");
    $result = $this->query($sql);
    $fileSlugs = array();
    while ($myResult = $result->fetchArray()){
      if ($pictures_only === True ) {
        if (!$this->isVideo($myResult[0])) {
          $fileSlugs[$myResult[0]] = $myResult[0];
        }
      } else {
        if ($videos_only === True) {
          if ($this->isVideo($myResult[0]) && !$this->isGif($myResult[0])) {
            $fileSlugs[$myResult[0]] = $myResult[0];
          }
        } else {
            if ($gif_only === True) {
                if ($this->isGif($myResult[0])) {
                  $fileSlugs[$myResult[0]] = $myResult[0];
                }
            } else {
              $fileSlugs[$myResult[0]] = $myResult[0];
            }
        }
      }
    }
    return $fileSlugs;
  }


  /**
   * @return void
   * @param string $tag
   * @param string $filter
   **/
  public function linkAllFilesToTag($tag, $filter=False) {
    $this->log(sprintf("Linking all files (filter %s) to tag %s", $filter, $tag));
    $sql = sprintf("SELECT slug  FROM myFiles WHERE slug not in (SELECT file_slug from tags_files WHERE tag_slug = '%s' )", $tag);
    $result = $this->query($sql);
    while ($myResult = $result->fetchArray()){
      if ($filter == "pictures" ) {
        if (!$this->isVideo($myResult[0])) {
          $this->log(sprintf("Linking %s and %s because only pictures and extension is not in %s", $myResult[0], $tag, implode(',', $this->jsonArray['videosExtentions'])));
          $this->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $myResult[0], $tag));
        }
      } elseif ($filter == "videos") {
        if ($this->isVideo($myResult[0]) && !$this->isGif($myResult[0])) {
          $this->log(sprintf("Linking %s and %s because only videos and extension is in %s and not gif", $myResult[0], $tag, implode(',', $this->jsonArray['videosExtentions'])));
          $this->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $myResult[0], $tag));
        }
      } elseif ($filter == "gif") {
        if ($this->isGif($myResult[0])) {
          $this->log(sprintf("Linking %s and %s because extension is gif", $myResult[0], $tag));
          $this->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $myResult[0], $tag));
        }
      } else {
          $this->log(sprintf("Linking %s and %s because no filter", $myResult[0], $tag));
          $this->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $myResult[0], $tag));
      }
    }
  }

  /**
   * @return array fileLabels
   * @param array $fileSlugs
   * */
  public function getFilesFromTheirSlugs($fileSlugs) {
    array_walk($fileSlugs, 'prepare_for_sql');
    $this->log(sprintf("Getting files from files slugs : %s", implode(",", $fileSlugs)));
    $sql = sprintf("SELECT label from myFiles  where slug in (%s) order by label", implode(",", $fileSlugs));
    $result = $this->query($sql);
    $fileLabels = array();
    while ($myResult = $result->fetchArray()){
      $fileLabels[$myResult[0]] = $myResult[0];
    }
    return $fileLabels;
  }

  /**
   * @return array fileLabels
   * */
  public function getAllFileLabels() {
    $this->log("Getting all file labels ordered by label ASC");
    $sql = sprintf("SELECT label from myFiles order by label");
    $result = $this->query($sql);
    $fileLabels = array();
    while ($myResult = $result->fetchArray()){
      $fileLabels[$myResult[0]] = $myResult[0];
    }
    return $fileLabels;
  }

  /**
   * @return array("slug" => array("year" => 2017, "month" => 12, "day" => "23"))
   **/
  public function get_files_info() {
    $this->log("Getting all files info");
    $sql = sprintf("SELECT * from files_info");
    $result = $this->query($sql);
    $files_info = array();
    while ($myResult = $result->fetchArray()){
      $files_info[slugify($myResult[0])] = array(
        "year" => $myResult[1],
        "month" => $myResult[2],
        "day" => $myResult[3]
      );
    }
    return $files_info;
  }

  /**
   * @param string $slugTag
   * @return string displays buttons with javascript hack
   * */
  public function getSearchButtons($slugTag) {
    $this->log("Getting search buttons");
    $myAndButton = '<svg id="%s" onclick="%s" version="1.1" baseProfile="full" width="20" height="20" xmlns="http://www.w3.org/2000/svg">
      <circle cx="10" cy="10" r="10" fill="green" />
      <text x="10" y="12" font-size="10" text-anchor="middle" fill="white">+</text>
    </svg>';
    $myWithoutButton = '<svg id="%s" onclick="%s" version="1.1" baseProfile="full" width="20" height="20" xmlns="http://www.w3.org/2000/svg">
      <circle cx="10" cy="10" r="10" fill="red" />
      <text x="10" y="12" font-size="10" text-anchor="middle" fill="white">-</text>
    </svg>';
    $myOrButton = '<svg id="%s" onclick="%s" version="1.1" baseProfile="full" width="20" height="20" xmlns="http://www.w3.org/2000/svg">
      <circle cx="10" cy="10" r="10" fill="blue" />
      <text x="10" y="12" font-size="10" text-anchor="middle" fill="white">|</text>
    </svg>';

    $customAndButton = sprintf($myAndButton, "$slugTag-add-button", "addToSearchInputText('and', '$slugTag')");
    $customWithoutButton = sprintf($myWithoutButton, "$slugTag-without-button", "addToSearchInputText('without', '$slugTag')");
    $customOrButton = sprintf($myOrButton, "$slugTag-or-button", "addToSearchInputText('or', '$slugTag')");

    return $customAndButton . $customWithoutButton . $customOrButton;
  }

  /**
   * @return array of orphan videos (no thumbnails)
   **/
  public function getOrphanVideos($folderPath, $thumbnailsDirectory, $allowedExtensions) {
    $this->log("Looking for orphan videos");
    $listFolder = scandir($folderPath);

    $result = array();
    foreach ($listFolder as $content) {
      # filter hidden content
      if (substr($content, 0, 1) != ".") {
        if (!is_dir($folderPath . DIRECTORY_SEPARATOR . $content)) {
          $my_explode = explode('.', $content);
          if ($this->isVideo($content)) {
            # test if thumbnail exists
            $proof_of_existence = false;
            foreach ($allowedExtensions as $allowedExtension) {
              $myExplode = explode(".", $content);
              array_pop($myExplode);
              $myExplode[] = $allowedExtension;
              $filename = implode(".", $myExplode);
              if (file_exists($folderPath . DIRECTORY_SEPARATOR . $thumbnailsDirectory . DIRECTORY_SEPARATOR . $filename)) {
                $proof_of_existence = true;
              }
            }

            if ($proof_of_existence == false) {
              $result[$content] = $content;
            }
          }
        }
      }
    }
    return $result;
  }


  /**
   * @return array of orphan thumbnails (no videos)
   **/
  public function getOrphanThumbnails($folderPath, $thumbnailsDirectory, $urlRootThumbnails) {
    $this->log("Looking for orphan thumbnails");
    $listFolder = scandir($folderPath . DIRECTORY_SEPARATOR . $thumbnailsDirectory);

    $result = array();
    foreach ($listFolder as $content) {
      # filter hidden content
      if (substr($content, 0, 1) != ".") {
        if (!is_dir($folderPath . DIRECTORY_SEPARATOR . $content)) {
          $my_explode = explode('.', $content);
          # test if video exists
          $proof_of_existence = false;
          foreach ($this->jsonArray['videosExtentions'] as $ext){
              $myExplode = explode(".", $content);
              array_pop($myExplode);
              $myExplode[] = $ext;
              $filename = implode(".", $myExplode);
              if (file_exists($folderPath . DIRECTORY_SEPARATOR . $filename)) {
                $proof_of_existence = true;
              }
          }
          if ($proof_of_existence == false) {
            $result[$content] = $content;
          }
        }
      }
    }
    return $result;
  }


  /**
   * @return void
   * delete orphan thumbnails
   **/
  public function deleteOrphanThumbnails($folderPath, $thumbnailsDirectory, $urlRootThumbnails) {
    $this->log("Deleting orphan thumbnails");
    $listFolder = scandir($folderPath . DIRECTORY_SEPARATOR . $thumbnailsDirectory);

    $result = array();
    foreach ($listFolder as $content) {
      # filter hidden content
      if (substr($content, 0, 1) != ".") {
        if (!is_dir($folderPath . DIRECTORY_SEPARATOR . $content)) {
          $my_explode = explode('.', $content);
          # test if video exists
          $proof_of_existence = false;
          foreach($this->jsonArray['videosExtentions'] as $ext) {
              $myExplode = explode(".", $content);
              array_pop($myExplode);
              $myExplode[] = $ext;
              $filename = implode(".", $myExplode);
              if (file_exists($folderPath . DIRECTORY_SEPARATOR . $filename)) {
                $proof_of_existence = true;
              }
          }

          if ($proof_of_existence == false) {
            $this->log(sprintf("Delete orphan thumbnail %s", $content));
            @unlink($folderPath . DIRECTORY_SEPARATOR . $thumbnailsDirectory . DIRECTORY_SEPARATOR . $content);
          }
        }
      }
    }
  }

  /**
   * detect files names matching tampermonkey script convention
   * @param string filename
   * @return array("uri" => "21321321", "year" => 2017, "author" => "toto le retour") or False
   * @example array(5) {
  [0]=>
  string(24) "author=24214224=2017.mp4"
  [1]=>
  string(6) "author"
  [2]=>
  string(8) "24214224"
  [3]=>
  string(4) "2017"
  [4]=>
  string(3) "mp4"
}
   **/
  public function detect_tampermonkey_files($filename) {
    $PATTERN = "/^([^=]+)=([^=]+)=([^=]+)\.(.+)$/";
    preg_match($PATTERN, $filename, $res);

    if (!empty($res)) {
      return array(
        "year" => $res[3],
        "uri" => $res[2],
        "author" => $res[1],
      );
    }
    return False;
  }

}

/**
 * @param &$value string such as "file-jpg"
 * @param $key 
 * put character "'" around $value
 * */
function prepare_for_sql(&$value, $key) {
  $value = "'$value'";
}
