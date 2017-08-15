<?php

function slugify($text)
{
  // replace non letter or digits by -
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);

  // transliterate
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

  // remove unwanted characters
  $text = preg_replace('~[^-\w]+~', '', $text);

  // trim
  $text = trim($text, '-');

  // remove duplicate -
  $text = preg_replace('~-+~', '-', $text);

  // lowercase
  $text = strtolower($text);

  if (empty($text)) {
    return 'n-a';
  }

  return $text;
}

# if php client
if (php_sapi_name() == "cli") {
  # options from command line
  $myOptions = getopt("p:t:f:h", array(
    "path:",
    "tag:",
    "file:",
    "addTag",
    "help",
    "tagParent:"
  ));

  # help function
  function usage() {
    echo <<<EOF
h|help    : show this message
p|path    : path directory with datas REQUIRED
t|tag     : tag to add/delete or link to file
f|file    : file to link to tag
addTag    : action to add tag to DB
tagParent : tag parent when adding new tag

examples:
  # add new data if needed
  php index.php --path /home/lonclegr/Images/ged

  # connect tag to file
  php index.php --path /home/lonclegr/Images/ged --tag woman --file woman.jpg

  # add tag without parent tag
  php index.php --path /home/lonclegr/Images/ged --addTag --tag animal

  # add tag with parent tag
  php index.php --path /home/lonclegr/Images/ged --addTag --tag tiger --tagParent animal

EOF;
  }

  if (isset($myOptions["h"]) || isset($myOptions['help'])) {
    usage();
    exit(0);
  }

  if (!isset($myOptions["p"]) && !isset($myOptions['path'])) {
    usage();
    die("Parameter p|path required");
  }
  if (isset($myOptions["p"])) { $folderPath = $myOptions['p']; }
  if (isset($myOptions["path"])) { $folderPath = $myOptions['path']; }


  if (!is_readable($folderPath)) {
    die(sprintf("Folder '%s' is not readable", $folderPath));
  }
  if (!is_writable($folderPath)) {
    die(sprintf("Folder '%s' is not writable", $folderPath));
  }
  $listFolder = scandir($folderPath);

  # SQLite3
  $db = new SQLite3(sprintf('%s%s%s', $folderPath, DIRECTORY_SEPARATOR, '.ged.db'));
  $db->exec('CREATE TABLE if not exists myFiles (label STRING, slug STRING)');

  echo sprintf("Listing folder %s\n", $folderPath);
  foreach ($listFolder as $content) {
    if (substr($content, 0, 1) != ".") {
      # filter hidden content
      if (!is_dir($folderPath . DIRECTORY_SEPARATOR . $content)) {
        echo "\t";
        echo sprintf("%s\n", $content);
				$mySlugifiedText = slugify($content);
				$arrayResult = $db->querySingle(sprintf('select * from myFiles where slug = "%s"', $mySlugifiedText));
        if (empty($arrayResult)) {
          echo sprintf("Add file '%s' to db \n", $content);
					$db->exec(sprintf('insert into myFiles (label, slug) values ("%s", "%s")', $content, $mySlugifiedText));
				}
      }
    }
  }


  if (isset($myOptions["addTag"])) {
    echo "Option add tag detected! \n";
    if ( (isset($myOptions['t']) || isset($myOptions['tag'])) ) {
      $myTag = False;
      if (isset($myOptions['t'])) { $myTag = $myOptions['t']; }
      if (isset($myOptions["tag"])) { $myTag = $myOptions["tag"]; }

      $myTagParent = False;
      if (isset($myOptions["tagParent"])) { $myTagParent = $myOptions["tagParent"]; }

      if ($myTag) {
        # test if tag exists
        $arrayResult = $db->querySingle(sprintf('select * from myTags where slug = "%s"', slugify($myTag)));
        if (!empty($arrayResult)) {
          die(sprintf("ERROR : tag '%s' is already present into database.", $myTag));
        } else {
          if ($myTagParent) {
            # create tag with parent
            $arrayResult = $db->querySingle(sprintf('select * from myTags where slug = "%s"', slugify($myTagParent)));
            if (empty($arrayResult)) {
              die(sprintf("ERROR : tagParent '%s' is not present into database.", $myTagParent));
            } else {
              $db->exec(sprintf('insert into myTags (tag, slug, top_tag) values ("%s", "%s", "%s")', $myTag, slugify($myTag), slugify($myTagParent))); 
              echo sprintf("Tag added successfully : '%s' with parent '%s'", $myTag, $myTagParent);
            }
          } else {
            # create tag without parent
            $db->exec(sprintf('insert into myTags (tag, slug, top_tag) values ("%s", "%s", null)', $myTag, slugify($myTag))); 
            echo sprintf("Tag added successfully : '%s'", $myTag);
          }
        }
      }



    }
  }


#  echo sprintf("Listing myFiles content\n");
#  $result = $db->query("select * from myFiles");
#  while ($myResult = $result->fetchArray()){
#     print_r($myResult);
#  }

  # TAGS
  $db->exec('CREATE TABLE if not exists myTags (tag STRING, slug STRING, top_tag STRING)');
  $myTags = array(
    "human" =>  array(
       "man",
       "woman"
     ),
     "car"
   );

#  $db->exec(sprintf('insert into myTags (tag, slug, top_tag) values ("%s", "%s", null)', "human", slugify("human"))); 
#  $db->exec(sprintf('insert into myTags (tag, slug, top_tag) values ("%s", "%s", null)', "car", slugify("car"))); 
#  $db->exec(sprintf('insert into myTags (tag, slug, top_tag) values ("%s", "%s", "%s")', "man", slugify("man"), slugify("human"))); 
#  $db->exec(sprintf('insert into myTags (tag, slug, top_tag) values ("%s", "%s", "%s")', "woman", slugify("woman"), slugify("human"))); 

#  echo sprintf("Listing myTags content\n");
#  $result = $db->query("select * from myTags");
#  while ($myResult = $result->fetchArray()){
#     print_r($myResult);
#  }

  # tags_files
  $db->exec('CREATE TABLE if not exists tags_files (file_slug STRING, tag_slug STRING)');

  if ( (isset($myOptions['t']) || isset($myOptions['tag'])) && (isset($myOptions['f']) || isset($myOptions['file']))) {
    $myTag = False;
    $myFile = False;
    if (isset($myOptions['t'])) { $myTag = $myOptions['t']; }
    if (isset($myOptions["tag"])) { $myTag = $myOptions["tag"]; }

    if (isset($myOptions['f'])) { $myFile = $myOptions['f']; }
    if (isset($myOptions["file"])) { $myFile = $myOptions["file"]; }


    $arrayResult = $db->querySingle(sprintf('select * from tags_files where file_slug = "%s" AND tag_slug = "%s"', slugify($myFile), slugify($myTag)));
    if (empty($arrayResult)) {
      echo sprintf("Creating link between tag '%s' and file '%s'", $myTag, $myFile);
      
      # test if file exists
      $arrayResult = $db->querySingle(sprintf('select * from myFiles where slug = "%s"', slugify($myFile)));
      if (empty($arrayResult)) {
        die(sprintf("ERROR : file '%s' is not present into database.", $myFile));
      }

      # test if tag exists
      $arrayResult = $db->querySingle(sprintf('select * from myTags where slug = "%s"', slugify($myTag)));
      if (empty($arrayResult)) {
        die(sprintf("ERROR : tag '%s' is not present into database.", $myTag));
      }

      $db->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', slugify($myFile), slugify($myTag)));
    } else {
      echo sprintf("Link between tag '%s' and file '%s' already exists", $myTag, $myFile);
    }
  }

  $db->close();
  echo "\n";
} else {
  # if php and httpd
  error_reporting(E_ALL);
  ini_set('display_errors', '1');
  echo "<!doctype html>";
  echo "<html>";
  echo "<head>";
  echo sprintf("<title>GED</title>");
  echo "</head>";
  echo "<body>";

  $folderPath = "/var/www/html/ged/";

  # searchBar
  echo "<h2>Search bar</h2>";
  echo "<form method='GET' >";
  echo sprintf("<input id='searchBar' name='searchBar' type='text' value='%s' />", ( isset($_GET['searchBar'])? $_GET['searchBar'] : ''));
  echo "</form>";

  # myFiles
  echo sprintf("<h2>%s</h2>", "Media listing");
  $db = new SQLite3(sprintf('%s%s%s', $folderPath, DIRECTORY_SEPARATOR, '.ged.db'));
  $result = $db->query("select label from myFiles order by label");

  # filter searchBar
  if (isset($_GET['searchBar'])) { $search = $_GET['searchBar']; } else {$search = '';}
  if ($search != '') {
    # detect pattern searchBar
    ##########################
    $flagMatching = False;
    
    ###############
    # case "x or y"
    ###############
    $pattern = "/([^ ]+) or ([^ ]+)/";
    preg_match($pattern, $search, $matches);
    if (!empty($matches) && isset($matches[1]) && isset($matches[2])) {
      $flagMatching = True;
      $firstMatch = $matches[1];
      $secondMatch = $matches[2];

      $arraySlugWithChildren = array("'$firstMatch'", "'$secondMatch'");
      foreach (array($firstMatch, $secondMatch) as $slug) {
        $result = $db->query(sprintf('select slug from myTags where top_tag = "%s"', slugify($slug)));
        while ($myResult = $result->fetchArray()) {
          $mySlug = $myResult[0];
          $arraySlugWithChildren[] = "'$mySlug'";
        }
      }
      $subQuery = implode(",", $arraySlugWithChildren);
      $result = $db->query(sprintf("select label from myFiles where slug in (select file_slug from tags_files where tag_slug in (%s))", $subQuery));
    }

    ################
    # case "x and y"
    ################
    $pattern = "/([^ ]+) and ([^ ]+)/";
    preg_match($pattern, $search, $matches);
    if (!empty($matches) && isset($matches[1]) && isset($matches[2])) {
      $flagMatching = True;
      $firstMatch = $matches[1];
      $secondMatch = $matches[2];

      $firstArraySlugWithChildren = array("'$firstMatch'");
      $result = $db->query(sprintf('select slug from myTags where top_tag = "%s"', slugify($firstMatch)));
      while ($myResult = $result->fetchArray()) {
        $mySlug = $myResult[0];
        $firstArraySlugWithChildren[] = "'$mySlug'";
      }
      $subQuery = implode(",", $firstArraySlugWithChildren);
      $firstQuery = sprintf("select label from myFiles where slug in (select file_slug from tags_files where tag_slug in (%s))", $subQuery);

      $secondArraySlugWithChildren = array("'$secondMatch'");
      $result = $db->query(sprintf('select slug from myTags where top_tag = "%s"', slugify($secondMatch)));
      while ($myResult = $result->fetchArray()) {
        $mySlug = $myResult[0];
        $secondArraySlugWithChildren[] = "'$mySlug'";
      }
      $subQuery = implode(",", $secondArraySlugWithChildren);
      $secondQuery = sprintf("select label from myFiles where slug in (select file_slug from tags_files where tag_slug in (%s))", $subQuery);

      $result = $db->query(sprintf("%s INTERSECT %s", $firstQuery, $secondQuery));
    }


    ####################
    # case "x without y"
    ####################
    $pattern = "/([^ ]+) without ([^ ]+)/";
    preg_match($pattern, $search, $matches);
    if (!empty($matches) && isset($matches[1]) && isset($matches[2])) {
      $flagMatching = True;
      $firstMatch = $matches[1];
      $secondMatch = $matches[2];

      $firstArraySlugWithChildren = array("'$firstMatch'");
      $result = $db->query(sprintf('select slug from myTags where top_tag = "%s"', slugify($firstMatch)));
      while ($myResult = $result->fetchArray()) {
        $mySlug = $myResult[0];
        $firstArraySlugWithChildren[] = "'$mySlug'";
      }
      $subQuery = implode(",", $firstArraySlugWithChildren);
      $firstQuery = sprintf("select label from myFiles where slug in (select file_slug from tags_files where tag_slug in (%s))", $subQuery);

      $secondArraySlugWithChildren = array("'$secondMatch'");
      $result = $db->query(sprintf('select slug from myTags where top_tag = "%s"', slugify($secondMatch)));
      while ($myResult = $result->fetchArray()) {
        $mySlug = $myResult[0];
        $secondArraySlugWithChildren[] = "'$mySlug'";
      }
      $subQuery = implode(",", $secondArraySlugWithChildren);
      $secondQuery = sprintf("select label from myFiles where slug in (select file_slug from tags_files where tag_slug in (%s))", $subQuery);

      $result = $db->query(sprintf("%s and label not in (%s)", $firstQuery, $secondQuery));
    }




    if ($flagMatching == False) { 
      ###############
      # default case
      ###############
      $arraySlugWithChildren = array("'$search'");
      # look for tag and children tags (n+1)
      $result = $db->query(sprintf('select slug from myTags where top_tag = "%s"', slugify($search)));
      while ($myResult = $result->fetchArray()) {
        $mySlug = $myResult[0];
        $arraySlugWithChildren[] = "'$mySlug'";
      }
      $subQuery = implode(",", $arraySlugWithChildren);
      $result = $db->query(sprintf("select label from myFiles where slug in (select file_slug from tags_files where tag_slug in (%s))", $subQuery));
    }

  }
  echo "<ul>";
  while ($myResult = $result->fetchArray()){
    echo sprintf("<img height='80px' src='/ged/datas/%s' />", $myResult[0]);
  }
  echo "</ul>";


  # myTags
  echo sprintf("<h2>%s</h2>", "Tag listing");
  $result = $db->query("select tag from myTags where top_tag is null order by tag");
  echo "<ul>";
  while ($myResult = $result->fetchArray()){
    $tag = $myResult[0];
    echo sprintf("<li>%s</li>", $tag);
    $subResult = $db->query(sprintf("select tag from myTags where top_tag = '%s' order by tag", $tag));
    echo "<ul>";
    while ($mySubResult = $subResult->fetchArray()) {
      $subTag = $mySubResult[0];
      echo sprintf("<li>%s</li>", $subTag);
    }
    echo "</ul>";
  }
  echo "</ul>";

  # tags_files
  echo sprintf("<h2>%s</h2>", "Links between files and tags");
  $result = $db->query("select tag_slug, file_slug from tags_files");
  echo "<table>";
  echo "<thead><tr><th>Tag</th><th>Media</th></tr></thead>";
  echo "<tbody>";
  while ($myResult = $result->fetchArray()){
    $tag = $myResult[0];
    $file = $myResult[1];
    echo sprintf("<tr><td>%s</td><td>%s</td></tr>", $tag, $file);
  }
  echo "</tbody>";
  echo "</table>";



  echo "</body>";
  echo "</html>";
}
