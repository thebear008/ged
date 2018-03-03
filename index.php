<?php
session_start();

include('class.DB.php');
include('class.Search.php');
include('class.Log.php');


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

# set time limit 
set_time_limit((isset($jsonArray['set_time_limit']) ? $jsonArray['set_time_limit']: 0));

# log if debug == true
$log = new Log((isset($jsonArray['log_file']) ? $jsonArray['log_file'] : 'application.log'), ($jsonArray["debug"] == "true"));
$log->write("Log object created.");

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
  $log->write("configDirectory loaded from SESSION.");
  $folderPath = $_SESSION['configDirectory'];
}

if (!isset($folderPath)) {
  die('Please choose your data directory');
}

$log->write(sprintf('Loading DB : %s%s%s', $folderPath, DIRECTORY_SEPARATOR, '.ged.db'));
$db = new DB(sprintf('%s%s%s', $folderPath, DIRECTORY_SEPARATOR, '.ged.db'));
# if not $db then error
if (!$db) {
  echo sprintf("Unable to create DB : %s%s%s",  $folderPath, DIRECTORY_SEPARATOR, '.ged.db');
  exit(1);
}
# add logger to DB
$db->setLogger($log);


# create tables
$log->write(sprintf('Init DB with tag picture = %s and video = %s', $jsonArray['specialTags']['pictures'], $jsonArray['specialTags']['videos']));
$db->init($jsonArray['specialTags']);

# ##########################################
# refreshDb only if $_GET['refreshDb']

if (isset($_GET['refreshDb'])) { 
  $log->write("Refreshing DB");
  # drop all tags
  $db->dropTags();
  $log->write("All tags deleted");


  # loadTags
  if (!isset($jsonArray['tags'])) {
    echo "Key tags is missing";
    exit(1);
  }
  $log->write("Loading tags from file");
  $db->loadTags($jsonArray['tags']);

  # loadFiles
  $log->write(sprintf("Loading files from folder : %s", $folderPath));
  $db->loadFiles($folderPath);

  # cleanTagsFiles
  $log->write('Clean links between tags and files');
  $db->cleanTagsFiles();

  #########################
  # tagPicture management #
  #########################
  $pictures_without_tag = $db->getFilesWithoutTags(True);
  foreach ($pictures_without_tag as $picture) {
    $log->write(sprintf("Auto-tag picture %s with %s", $picture, $jsonArray['specialTags']['pictures']));
    $db->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $picture, $jsonArray['specialTags']['pictures']));
  }
  ###############################
  # END : tagPicture management #
  ###############################

  #########################
  # tagVideo management #
  #########################
  $videos_without_tag = $db->getFilesWithoutTags(False, True);
  foreach ($videos_without_tag as $video) {
    $log->write(sprintf("Auto-tag video %s with %s", $video, $jsonArray['specialTags']['videos']));
    $db->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $video, $jsonArray['specialTags']['videos']));
  }
  ###############################
  # END : tagVideo management #
  ###############################

}
# END : refreshDb only if $_GET['refreshDb']
# ##########################################

# ################################
# cleanDb only if $_GET['cleanDb']
if (isset($_GET['cleanDb'])) {
  $log->write("Cleaning DB");
  $db->cleanDb();
}
# END : cleanDb only if $_GET['cleanDb']
# #######################################

echo "<!doctype html>";
echo "<html>";
echo "<head>";
echo sprintf("<title>%s v%s</title>", $jsonArray['platformName'],  $jsonArray["version"]);

##############
# CSS embedded
##############
echo "<style>";
echo sprintf("div.firstColumn {width:%s; padding:0; margin: 0; display:inline;} ", $jsonArray["width"]["left"]);
echo sprintf("div.secondColumn {width:%s; padding:0; margin: 0; display:inline;}", $jsonArray["width"]["center"]);
echo sprintf("div.thirdColumn {width:%s; padding:0; margin: 0; display:inline;}", $jsonArray["width"]["right"]);
echo "div.Column {float:left;}";
echo "div.secondColumn img { padding-right:4px;  }";
echo "div.thirdColumn button {display:block; }";
echo "span.all-media-link { color:green;  } ";
echo "span.all-media-link:hover { cursor:pointer; color: black; } ";
echo "svg:hover { cursor:pointer;  } ";
echo "body, html { height:100%; overflow: hidden; margin:0 ; padding:0; } ";
echo "div.Column { height: 95%; overflow-y: auto; overflow-x:hidden;  }";
echo sprintf("div.tag-tree-first-column { height: %s; overflow-y: auto; overflow-x:hidden; padding : 0; margin:0;} ", $jsonArray['height']['tagTreeFirstColumn']);
echo sprintf("div.tag-tree-third-column { height: %s; overflow-y: auto; overflow-x:hidden; padding : 0; margin:0;} ", $jsonArray['height']['tagTreeThirdColumn']);
echo ".tag-with-children { font-weight: bold;  } ";
echo ".tag-with-children:hover { cursor: pointer;  } ";
echo ".open-close-tree:hover { cursor: pointer;   }";
echo ".open-close-tree { color: green;   }";
echo "</style>";
####################
# END : CSS embedded
####################

echo sprintf("<script type='text/javascript' src='jquery-3.2.1.min.js' ></script>");

echo sprintf("<script type='text/javascript'  >

jQuery(document).ready(function() {
  // hide second level of tree tags
  jQuery('li.tag-with-children').siblings('ul').toggle();

  // onclick function
  jQuery('li.tag-with-children').click(function() {
     jQuery(this).next('ul').toggle();
  })
});

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


              <!-- toggle tree -->
              jQuery('#myContent li.tag-with-children').siblings('ul').toggle();
              // onclick function
              jQuery('#myContent li.tag-with-children').click(function() {
                 jQuery(this).next('ul').toggle();
              })
              <!-- END toggle tree -->

              <!-- open/close tree -->
              jQuery('.open-close-tree').clone().insertBefore('.tag-tree-third-column')
              jQuery('#myContent .open-close-tree').prop('onclick',null).off('click')
              jQuery('#myContent .open-close-tree').on('click', function() { openCloseTree('tag-tree-third-column')  } )
              <!-- END open/close tree -->
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
    myNewImg.style.maxWidth = '%s';
    myNewImg.style.maxHeight = '%s';
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

            <!-- toggle tree -->
            jQuery('#myContent li.tag-with-children').siblings('ul').toggle();
            // onclick function
            jQuery('#myContent li.tag-with-children').click(function() {
               jQuery(this).next('ul').toggle();
            })
            <!-- END toggle tree -->


              <!-- open/close tree -->
              jQuery('.open-close-tree').clone().insertBefore('.tag-tree-third-column')
              jQuery('#myContent .open-close-tree').prop('onclick',null).off('click')
              jQuery('#myContent .open-close-tree').on('click', function() { openCloseTree('tag-tree-third-column')  } )
              <!-- END open/close tree -->
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

<!-- openCloseTree -->
<!-- we don't want to detect if tree is open or closed so we use one variable to get memory and toggle action -->
var memoryTree = 1;
function openCloseTree(classOfColumn) {
  if (memoryTree == 1) {
    jQuery('div.'+ classOfColumn  +' ul ul').show()
    memoryTree = 0;
  } else {
    jQuery('div.'+ classOfColumn  +' ul ul').hide()
    memoryTree = 1;
  }
}
<!-- END openCloseTree -->

</script>", $jsonArray['urlRoot'], $jsonArray['video']['width'], $jsonArray['video']['height'],$jsonArray['pictureRight']['maxWidth'], $jsonArray['pictureRight']['maxHeight'], $jsonArray['urlRoot'], $jsonArray['urlRoot']);
echo "</head>";
echo "<body>";

# firstColumn
echo "<div class='firstColumn Column'>";
echo sprintf("<header>%s v%s &nbsp;  
  <a href='?refreshDb=%d'>Refresh DB</a>
  <a href='?cleanDb=%d'>Clean DB</a>
  <a href='issue.php'>Issues</a>
</header>", $jsonArray['platformName'], $jsonArray["version"], time(), time());


$hideTitles = "";
if (isset($jsonArray['hideTitles']) && $jsonArray['hideTitles'] == "true" ) {
  $hideTitles = "display:none;";
}
# searchBar
echo "<h2 style='$hideTitles' >Search bar</h2>";
echo "<form method='GET' id='myForm' >";
echo sprintf("<input id='searchBar' name='searchBar' type='text' value='%s' onkeypress='launchForm(event)'  />", ( isset($_GET['searchBar'])? $_GET['searchBar'] : ''));
echo "</form>";
echo "<button onclick='resetForm();' >reset</button>";


# search Datas without tags
echo sprintf("<span class='all-media-link' onclick='addToSearchInputText(\"and\", \"media-without-tags\")' >Media without tags</span>");

echo "<br/>";

# myTags
echo "<h2 style='$hideTitles' >Tag listing</h2>";
echo "<span class='all-media-link' onclick='addToSearchInputText(\"and\", \"all-files\")' >All medias</span>";

echo "<div class='tag-tree-first-column'>";
# link to open/close tags tree
echo "<span class='open-close-tree' onclick='openCloseTree(\"tag-tree-first-column\")'  >Open/Close tree</span>";
echo $db->showTagTree(False, False, False, False, $showSearchButton = True);
echo "</div>";

# tags_files
if ($jsonArray["debug"] == "true") {
  $log->write("Debug true : show links between files and tags");
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
echo "<h2 style='$hideTitles' >Media listing</h2>";
$result = $db->query("select label from myFiles order by label");

# filter searchBar
if (isset($_GET['searchBar'])) { $search = $_GET['searchBar']; } else {$search = '';}
if ($search != '') {
  $log->write(sprintf("Searching with %s", $search));
  $mySearch = new Search($db, $log);
  $myFileSlugs = $mySearch->go($search);
  $myFileLabels = $db->getFilesFromTheirSlugs($myFileSlugs);
} else {
  $log->write("Get all files, no search detected.");
  $myFileLabels = $db->getAllFileLabels();
}
foreach ($myFileLabels as $pictureMedia) {

  # detect if is not mp4 file
  if (substr($pictureMedia,-3) != "mp4") {
    $log->write(sprintf("Consider %s as picture", $pictureMedia));
    echo sprintf("<img id='show-%s' onclick='window.scrollTo(0,0);  populateThirdColumn(\"%s\", this, false, false)' height='%s' src='%s%s' />", slugify($pictureMedia), slugify($pictureMedia), $jsonArray['pictureMiddle']['height'], $jsonArray['urlRootDatas'], $pictureMedia);
  } else {
    $log->write(sprintf("Consider %s as MP4 video", $pictureMedia));
    $mp4File = $pictureMedia;
    foreach ($jsonArray['allowedExtensions'] as $allowedExtension) {
      $log->write(sprintf("Looking for thumbnails with extension %s", $allowedExtension));
      $myExplode = explode(".", $mp4File);
      array_pop($myExplode);
      $myExplode[] = $allowedExtension;
      $filename = implode(".", $myExplode);
      if (file_exists($_SESSION['configDirectory'] . DIRECTORY_SEPARATOR . $jsonArray['thumbnailsDirectory'] . DIRECTORY_SEPARATOR . $filename)) {
        $log->write(sprintf("Thumbnail found : %s", $filename));
        $pictureMedia = $filename;
        break;
      } else {
        $log->write(sprintf("Thumbnail not found : %s", $filename));
      }
    }
    echo sprintf("<img id='show-%s' onclick='window.scrollTo(0,0); populateThirdColumn(\"%s\", this, \"%s%s\", \"%s\")' height='%s' src='%s%s' />", slugify($pictureMedia), slugify($mp4File), $jsonArray['urlRootDatas'],  $mp4File,  slugify($mp4File), $jsonArray['pictureMiddle']['height'], $jsonArray['urlRootThumbnails'], $pictureMedia);
  }
}


# END : secondColumn
echo "</div>";

# thirdColumn
echo "<div class='thirdColumn Column'>";
echo "<h2 style='$hideTitles' >Show data</h2>";
echo "<div id='myContent'>&nbsp;</div>";
# END : thirdColumn
echo "</div>";



echo "</body>";
echo "</html>";
