<?php
class DB extends SQLite3 {

  /**
   * @return void
   **/
  public function init() {
    $this->exec('CREATE TABLE if not exists myFiles (label STRING, slug STRING)');
    $this->exec('CREATE TABLE if not exists myTags (tag STRING, slug STRING, top_tag STRING)');
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
   * @param array $arraySlugTags
   * */
  public function showTagTree($parent = False, $checkBoxFlag = False, $slugFile = False, $arraySlugTags = False) {
    $string = "<ul>";

    if ($parent) {
      $result = $this->query(sprintf("select slug from myTags where top_tag = '%s' order by tag", $parent));
    } else {
      $result = $this->query("select slug from myTags where top_tag is null order by tag");
    } 
    while ($myResult = $result->fetchArray()){
      if ($checkBoxFlag) {
        $checkedBoolean = '';
        if (in_array($myResult[0], $arraySlugTags)) { $checkedBoolean = 'checked'; }
        $string .= sprintf("<li>%s <input %s type='checkbox' value='%s' onclick='linkTagToFile(this, \"%s\")' /></li>", $myResult[0], $checkedBoolean, $myResult[0], $slugFile);
      } else {
        $string .= sprintf("<li>%s</li>", $myResult[0]);
      }
      $string .= $this->showTagTree($myResult[0], $checkBoxFlag, $slugFile, $arraySlugTags);
    }
    $string .= "</ul>";

    return $string;
  }

  /**
   * @param $tag string
   * @return array
   * */
  public function getTagAndChildren($tag) {
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
}
