<?php
class DB extends SQLite3 {

  /**
   * @return void
   **/
  public function init() {
    $this->exec('CREATE TABLE if not exists myFiles (label STRING, slug STRING PRIMARY KEY)');
    $this->exec('CREATE TABLE if not exists myTags (tag STRING, slug STRING PRIMARY KEY, top_tag STRING)');
    $this->exec('CREATE TABLE if not exists tags_files (file_slug STRING, tag_slug STRING)');
  }

  /**
   * @return void
   * @param array $jsonTags
   * @param string $parent
   **/
  public function loadTags($jsonTags, $parent = False) {
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
    $this->exec("DELETE FROM tags_files where tag_slug not in (select slug from myTags)");
    $this->exec("DELETE FROM tags_files where file_slug not in (select slug from myFiles)");
  }


  /**
   * @return void
   * delete tags_files records if files are linked to non-leaf-tag
   * */
  public function cleanDb() {
    $this->exec("DELETE FROM tags_files where tag_slug in (select top_tag from myTags where top_tag is not null)");
  }

  /**
   * @return void
   **/
  public function dropTags() {
    $this->exec('DELETE FROM myTags');
  }

  /**
   * @return void
   * @param string $myTag
   * @param string $myTagParent
   **/
  public function addTag($myTag, $myTagParent = False) {
    $arrayResult = $this->querySingle(sprintf('select * from myTags where slug = "%s"', slugify($myTag)));
    if (!empty($arrayResult)) {
      die(sprintf("ERROR : tag '%s' is already present into database.", $myTag));
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
    if (!is_readable($folderPath)) {
      die(sprintf("Folder '%s' is not readable", $folderPath));
    }
    if (!is_writable($folderPath)) {
      die(sprintf("Folder '%s' is not writable", $folderPath));
    }
    $listFolder = scandir($folderPath);

    foreach ($listFolder as $content) {
      # filter hidden content
      if (substr($content, 0, 1) != ".") {
        if (!is_dir($folderPath . DIRECTORY_SEPARATOR . $content)) {
          $mySlugifiedText = slugify($content);
          $arrayResult = $this->querySingle(sprintf('select * from myFiles where slug = "%s"', $mySlugifiedText));
          if (empty($arrayResult)) {
            $this->exec(sprintf('insert into myFiles (label, slug) values ("%s", "%s")', $content, $mySlugifiedText));
          }
        }
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
    $string = "<ul>";

    if ($parent) {
      $result = $this->query(sprintf("select slug from myTags where top_tag = '%s' order by tag", $parent));
    } else {
      $result = $this->query("select slug from myTags where top_tag is null order by tag");
    } 
    while ($myResult = $result->fetchArray()){
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
          $string .= sprintf("<li class='%s' >%s</li>", $classTagWithChildren, $myResult[0]);
        } else {
          $string .= sprintf("<li class='%s' ><label for='my-input-%s'>%s</label> <input %s id='my-input-%s'  type='checkbox' value='%s' onclick='linkTagToFile(this, \"%s\")' /></li>", $classTagWithChildren, $myResult[0], $myResult[0], $checkedBoolean, $myResult[0], $myResult[0], $slugFile);
        }
      } elseif ($showSearchButton) {
        # option to add search button 
        $string .= sprintf("<li class='%s' >%s %s</li>", $classTagWithChildren, $myResult[0], $this->getSearchButtons($myResult[0]));
      } else {
        $string .= sprintf("<li class='%s' >%s</li>", $classTagWithChildren, $myResult[0]);
      }
      $string .= $this->showTagTree($myResult[0], $checkBoxFlag, $slugFile, $arraySlugTags, $showSearchButton);
    }
    $string .= "</ul>";

    return $string;
  }

  /**
   * @param $tag string
   * @return array
   * */
  public function getTagAndChildren($tag) {
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
    $result = $this->query(sprintf("select slug from myTags where top_tag = '%s'", $tag));
    return ($result->fetchArray());
  }

  /**
   * @param $tag string
   * @param $print boolean
   * @return void
   * */
  public function deleteAllFromTag($tag, $print = True){
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
    if ($arrayResult = $this->querySingle(sprintf('select * from myFiles where slug = "%s"', $slug), true)) {
      $this->query(sprintf("DELETE FROM myFiles where slug = '%s'", $slug));
      $this->query(sprintf("DELETE FROM tags_files where file_slug = '%s'", $slug));

      # delete file on FS
      @unlink($_SESSION['configDirectory'] . DIRECTORY_SEPARATOR . $arrayResult['label']);
      # delete thumbnails
      foreach ($jsonConfig['allowedExtensions'] as $allowedExtension) {
        $myExplode = explode(".", $arrayResult['label']);
        array_pop($myExplode);
        $myExplode[] = $allowedExtension;
        $filename = implode(".", $myExplode);
        @unlink($_SESSION['configDirectory'] . DIRECTORY_SEPARATOR . $jsonConfig['thumbnailsDirectory'] . DIRECTORY_SEPARATOR . $filename);
      }
    }
  }


  /**
   * @return array fileSlugs
   * @param array $tagSlugs with '' : 'tag1', 'tag2', ...
   * */
  public function getFilesFromSlugs($tagSlugs) {
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
  public function getFilesWithoutTags() {
    $sql = sprintf("SELECT slug  FROM myFiles WHERE slug not in (SELECT file_slug from tags_files )");
    $result = $this->query($sql);
    $fileSlugs = array();
    while ($myResult = $result->fetchArray()){
      $fileSlugs[$myResult[0]] = $myResult[0];
    }
    return $fileSlugs;
  }

  /**
   * @return array fileLabels
   * @param array $fileSlugs
   * */
  public function getFilesFromTheirSlugs($fileSlugs) {
    array_walk($fileSlugs, 'prepare_for_sql');
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
    $sql = sprintf("SELECT label from myFiles order by label");
    $result = $this->query($sql);
    $fileLabels = array();
    while ($myResult = $result->fetchArray()){
      $fileLabels[$myResult[0]] = $myResult[0];
    }
    return $fileLabels;
  }

  /**
   * @param string $slugTag
   * @return string displays buttons with javascript hack
   * */
  public function getSearchButtons($slugTag) {
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
}

/**
 * @param &$value string such as "file-jpg"
 * @param $key 
 * put character "'" around $value
 * */
function prepare_for_sql(&$value, $key) {
  $value = "'$value'";
}
