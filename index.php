<?php
session_start();

include('class.DB.php');
include('class.Search.php');

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
    "delTag",
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
delTag    : action to delete tag to DB
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
  
  # add tag without parent tag
  php index.php --path /home/lonclegr/Images/ged --delTag --tag animal

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
  $db = new DB(sprintf('%s%s%s', $folderPath, DIRECTORY_SEPARATOR, '.ged.db'));
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



  if (isset($myOptions["delTag"])) {
    echo "Option delete tag detected! \n";
    if ( (isset($myOptions['t']) || isset($myOptions['tag'])) ) {
      $myTag = False;
      if (isset($myOptions['t'])) { $myTag = $myOptions['t']; }
      if (isset($myOptions["tag"])) { $myTag = $myOptions["tag"]; }

      if ($myTag) {
        # test if tag exists
        $arrayResult = $db->querySingle(sprintf('select * from myTags where slug = "%s"', slugify($myTag)));
        if (empty($arrayResult)) {
          die(sprintf("ERROR : tag '%s' is not present into database.", $myTag));
        } else {
          # del tag
          # look for tag and children
          foreach ($db->getTagAndChildren($myTag) as $tagToDelete) {
            $db->deleteAllFromTag($tagToDelete);
          }
        }
      }
    }
  }

  # TAGS
  $db->exec('CREATE TABLE if not exists myTags (tag STRING, slug STRING, top_tag STRING)');

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

  # choose media directory from json
  $jsonFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . "config.json";
  if (!is_readable($jsonFile)) {
    echo sprintf("Config file is not readable : %s", $jsonFile);
    exit(1);
  }
  $fileHandle = file_get_contents($jsonFile);
  $jsonArray = json_decode($fileHandle, true);
  if (JSON_ERROR_NONE != json_last_error()) {
    echo sprintf("Error reading JSON : %s", json_last_error_msg());
    exit(1);
  }

  # set mediaDirectory if into GET
  if (isset($_GET['mediaDirectory'])) {
    $_SESSION['configDirectory'] = $_GET['mediaDirectory'];
  }

  if (!isset($_SESSION['configDirectory'])) {
    echo "<h1>Choose data directory</h1>";
    echo "<ul>";
    if (isset($jsonArray['mediaDirectories'])) {
      if (is_array($jsonArray['mediaDirectories'])) {
        foreach ($jsonArray['mediaDirectories'] as $mediaDirectory) {
          echo sprintf("<li><a href='?mediaDirectory=%s'>%s</a></li>", $mediaDirectory, $mediaDirectory);
        }
      } else {
        echo sprintf("<li><a href='?mediaDirectory=%s'>%s</a></li>", $jsonArray['mediaDirectories'], $jsonArray['mediaDirectories']);
      }
    } else {
      echo "<li>Key mediaDirectories is missing</li>";
    }
    echo "</ul>";
  } else {
    $folderPath = $_SESSION['configDirectory'];
  }

  if (!isset($folderPath)) {
    die('Please choose your data directory');
  }

  $db = new DB(sprintf('%s%s%s', $folderPath, DIRECTORY_SEPARATOR, '.ged.db'));
  # if not $db then error
  if (!$db) {
    echo sprintf("Unable to create DB : %s%s%s",  $folderPath, DIRECTORY_SEPARATOR, '.ged.db');
    exit(1);
  }
  
  # create tables
  $db->init();

  # ##########################################
  # refreshDb only if $_GET['refreshDb']

  if (isset($_GET['refreshDb'])) { 
    # drop all tags
    $db->dropTags();


    # loadTags
    if (!isset($jsonArray['tags'])) {
      echo "Key tags is missing";
      exit(1);
    }
    $db->loadTags($jsonArray['tags']);


    # loadFiles
    $db->loadFiles($folderPath);

    # cleanTagsFiles
    $db->cleanTagsFiles();
  }
  # END : refreshDb only if $_GET['refreshDb']
  # ##########################################

  echo "<!doctype html>";
  echo "<html>";
  echo "<head>";
  echo sprintf("<title>%s v%s</title>", $jsonArray['platformName'],  $jsonArray["version"]);
  echo "<style>";
  echo sprintf("div.firstColumn {width:%s; padding:0; margin: 0; display:inline;} ", $jsonArray["width"]["left"]);
  echo sprintf("div.secondColumn {width:%s; padding:0; margin: 0; display:inline;}", $jsonArray["width"]["center"]);
  echo sprintf("div.thirdColumn {width:%s; padding:0; margin: 0; display:inline;}", $jsonArray["width"]["right"]);
  echo "div.Column {float:left;}";
  echo "div.thirdColumn button {display:block; }";
  echo "span.all-media-link { color:green;  } ";
  echo "span.all-media-link:hover { cursor:pointer; color: black; } ";
  echo "svg:hover { cursor:pointer;  } ";
  echo "body, html { height:100%; overflow: hidden; margin:0 ; padding:0; } ";
  echo "div.Column { height: 95%; overflow-y: auto; overflow-x:hidden;  }";
  echo "</style>";
  echo sprintf("<script type='text/javascript'  >

  function populateThirdColumn(mySlug, myImgObject, mp4Name, mySlugMp4) {
    // reset third column
    document.getElementById(\"myContent\").innerHTML = '';

    // delete button
    var myDeleteButton = document.createElement('button');
    myDeleteButton.innerHTML = 'Delete';
    myDeleteButton.type = 'button';
    myDeleteButton.onclick = function() { if (confirm('Confirm delete ' + mySlug + ' ?')) { 
      myImgObject.parentElement.removeChild(myImgObject);   
      document.getElementById(\"myContent\").innerHTML = '';

      req = new XMLHttpRequest();
      req.onreadystatechange = function(event) {
          // XMLHttpRequest.DONE === 4
          if (this.readyState === XMLHttpRequest.DONE) {
              if (this.status === 200) {
                var myDiv = document.createElement('div');
                myDiv.innerHTML = this.responseText;
                document.getElementById(\"myContent\").appendChild(myDiv);
              }
          }
      };

      req.open('POST', '%sajax.php', true);
      req.setRequestHeader(\"Content-type\", \"application/x-www-form-urlencoded\");
      if (mySlugMp4) {
        req.send('myDeleteSlug=' + mySlugMp4);
      } else {
        req.send('myDeleteSlug=' + mySlug);
      }


    } else { alert('Action cancelled.')  }   } ;
    document.getElementById(\"myContent\").appendChild(myDeleteButton);
    

    if (mp4Name) {
      var myNewVideo = document.createElement('video');
      myNewVideo.width = '%d';
      myNewVideo.height = '%d';
      myNewVideo.controls = true;
      var sourceMP4 = document.createElement('source');
      sourceMP4.src = mp4Name;
      sourceMP4.type = 'video/mp4';
      myNewVideo.appendChild(sourceMP4);
      document.getElementById(\"myContent\").appendChild(myNewVideo);
    } else {
      var myNewImg = new Image();
      myNewImg.src = myImgObject.src;
      myNewImg.style.width = '100%%';
      document.getElementById(\"myContent\").appendChild(myNewImg);
    }
		req = new XMLHttpRequest();

		req.onreadystatechange = function(event) {
				// XMLHttpRequest.DONE === 4
				if (this.readyState === XMLHttpRequest.DONE) {
						if (this.status === 200) {
							var myDiv = document.createElement('div');
							//myParagraph.innerHTML = JSON.parse(this.responseText);
							myDiv.innerHTML = this.responseText;
							document.getElementById(\"myContent\").appendChild(myDiv);
						}
				}
		};

		req.open('POST', '%sajax.php', true);
		req.setRequestHeader(\"Content-type\", \"application/x-www-form-urlencoded\");
		req.send('slug=' + mySlug);
	}


	function linkTagToFile(myCheckbox, mySlugFile) {
		req = new XMLHttpRequest();

		req.onreadystatechange = function(event) {
				// XMLHttpRequest.DONE === 4
				if (this.readyState === XMLHttpRequest.DONE) {
						if (this.status === 200) {
							console.log('Ok linkTagToFile');
						}
				}
		};

		req.open('POST', '%sajax.php', true);
		req.setRequestHeader(\"Content-type\", \"application/x-www-form-urlencoded\");
		req.send('slugFile=' + mySlugFile + '&checked=' + myCheckbox.checked + '&slugTag=' + myCheckbox.value);
	}

  <!-- addToSearchInputText -->
  function addToSearchInputText(myAction, mySlugTag) {
    if (document.getElementById('searchBar').value != '') {
      document.getElementById('searchBar').value += ' ' + myAction + ' ' + mySlugTag;
    } else {
      document.getElementById('searchBar').value = mySlugTag;
    }
    document.getElementById('myForm').submit();
  }
  <!-- END addToSearchInputText -->

  <!-- resetForm -->
  function resetForm() {
    document.getElementById('searchBar').value = '';
    document.getElementById('myForm').submit();
  }
  <!-- END resetForm -->

  <!-- launchForm -->
  function launchForm(myEvent) {
    if (myEvent.keyCode == 13) {
      document.getElementById('myForm').submit();
    }
    return false;
  }
  <!-- END launchForm -->

	</script>", $jsonArray['urlRoot'], $jsonArray['video']['width'], $jsonArray['video']['height'], $jsonArray['urlRoot'], $jsonArray['urlRoot']);
  echo "</head>";
  echo "<body>";
  echo sprintf("<header>%s v%s &nbsp;  <a href='?refreshDb=%d'>Refresh DB</a>   </header>", $jsonArray['platformName'], $jsonArray["version"], time());

  # firstColumn
  echo "<div class='firstColumn Column'>";


  # searchBar
  echo "<h2>Search bar</h2>";
  echo "<form method='GET' id='myForm' >";
  echo sprintf("<input id='searchBar' name='searchBar' type='text' value='%s' onkeypress='launchForm(event)'  />", ( isset($_GET['searchBar'])? $_GET['searchBar'] : ''));
  echo "</form>";
  echo "<button onclick='resetForm();' >reset</button>";


  # search Datas without tags
  echo sprintf("<span class='all-media-link' onclick='addToSearchInputText(\"and\", \"media-without-tags\")' >Media without tags</span>");

  # myTags
  echo sprintf("<h2>%s</h2>", "Tag listing");
  echo "<span class='all-media-link' onclick='addToSearchInputText(\"and\", \"all-files\")' >All medias</span>";
  echo $db->showTagTree(False, False, False, False, $showSearchButton = True);

  # tags_files
  if ($jsonArray["debug"] == "true") {
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
  } # only if debug == "true"




  # END : firstColumn
  echo "</div>";


  # secondColumn
  echo "<div class='secondColumn Column'>";

  # myFiles
  echo sprintf("<h2>%s</h2>", "Media listing");
  $result = $db->query("select label from myFiles order by label");

  # filter searchBar
  if (isset($_GET['searchBar'])) { $search = $_GET['searchBar']; } else {$search = '';}
  if ($search != '') {
    $mySearch = new Search($db);
    $myFileSlugs = $mySearch->go($search);
    $myFileLabels = $db->getFilesFromTheirSlugs($myFileSlugs);
  } else {
    $myFileLabels = $db->getAllFileLabels();
  }
  echo "<ul>";
  foreach ($myFileLabels as $pictureMedia) {

    # detect if is not mp4 file
    if (substr($pictureMedia,-3) != "mp4") {
      echo sprintf("<img id='show-%s' onclick='window.scrollTo(0,0);  populateThirdColumn(\"%s\", this, false, false)' height='80px' src='%s%s' />", slugify($pictureMedia), slugify($pictureMedia), $jsonArray['urlRootDatas'], $pictureMedia);
    } else {
      $mp4File = $pictureMedia;
      foreach ($jsonArray['allowedExtensions'] as $allowedExtension) {
        $myExplode = explode(".", $mp4File);
        array_pop($myExplode);
        $myExplode[] = $allowedExtension;
        $filename = implode(".", $myExplode);
        if (file_exists($_SESSION['configDirectory'] . DIRECTORY_SEPARATOR . $jsonArray['thumbnailsDirectory'] . DIRECTORY_SEPARATOR . $filename)) {
          $pictureMedia = $filename;
          break;
        }
      }
      echo sprintf("<img id='show-%s' onclick='window.scrollTo(0,0); populateThirdColumn(\"%s\", this, \"%s%s\", \"%s\")' height='80px' src='%s%s' />", slugify($pictureMedia), slugify($mp4File), $jsonArray['urlRootDatas'],  $mp4File,  slugify($mp4File), $jsonArray['urlRootThumbnails'], $pictureMedia);
    }
  }
  echo "</ul>";


  # END : secondColumn
  echo "</div>";

  # thirdColumn
  echo "<div class='thirdColumn Column'>";
  echo "<h2>Show data</h2>";
  echo "<div id='myContent'>&nbsp;</div>";
  # END : thirdColumn
  echo "</div>";



  echo "</body>";
  echo "</html>";
}
