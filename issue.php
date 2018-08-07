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

# add jsonArray to DB
$db->setJsonArray($jsonArray);

# create tables
$log->write(sprintf('Init DB with tag picture = %s and video = %s and gif = %s', $jsonArray['specialTags']['pictures'], $jsonArray['specialTags']['videos'], $jsonArray['specialTags']['gif']));
$db->init($jsonArray['specialTags']);


echo "<!doctype html>";
echo "<html>";
echo "<head>";
echo sprintf("<title>%s v%s</title>", $jsonArray['platformName'],  $jsonArray["version"]);

##############
# CSS embedded
##############
echo "<style>";
echo "span.all-media-link { color:green;  } ";
echo "span.ok { color:green;  } ";
echo "span.all-media-link:hover { cursor:pointer; color: black; } ";
echo "svg:hover { cursor:pointer;  } ";
echo ".tag-with-children { font-weight: bold;  } ";
echo ".tag-with-children:hover { cursor: pointer;  } ";
echo "</style>";
####################
# END : CSS embedded
####################

echo sprintf("<script type='text/javascript' src='jquery-3.2.1.min.js' ></script>");

#########################
# tagPicture management #
#########################
if (isset($_GET['tagPicture'])) {
  $log->write("Tagging pictures without tag");
  $pictures_without_tag = $db->getFilesWithoutTags(True);
  foreach ($pictures_without_tag as $picture) {
    $log->write(sprintf("Tagging %s for picture %s", $jsonArray['specialTags']['pictures'], $picture));
    $db->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $picture, slugify($jsonArray['specialTags']['pictures'])));
  }
}
###############################
# END : tagPicture management #
###############################

#########################
# tagVideo management #
#########################
if (isset($_GET['tagVideo'])) {
  $log->write("Tagging videos without tag");
  $videos_without_tag = $db->getFilesWithoutTags(False, True);
  foreach ($videos_without_tag as $video) {
    $log->write(sprintf("Tagging %s for video %s", $jsonArray['specialTags']['videos'], $video));
    $db->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $video, slugify($jsonArray['specialTags']['videos'])));
  }
}
###############################
# END : tagVideo management #
###############################


#########################
# tagGif management #
#########################
if (isset($_GET['tagGif'])) {
  $log->write("Tagging gifs without tag");
  $gifs_without_tag = $db->getFilesWithoutTags(False, False, True);
  foreach ($gifs_without_tag as $gif) {
    $log->write(sprintf("Tagging %s for gif %s", $jsonArray['specialTags']['gif'], $gif));
    $db->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $gif, slugify($jsonArray['specialTags']['gif'])));
  }
}
###############################
# END : tagGif management #
###############################





echo "</head>";
echo "<body>";

echo "<h1>Issues</h1>";

echo "<a href='index.php'>Back to homepage</a>";

echo "<h2>Videos without thumbnails</h2>";

$orphan_videos = $db->getOrphanVideos($folderPath, $jsonArray['thumbnailsDirectory'], $jsonArray['allowedExtensions']);

if (empty($orphan_videos)) {
  echo "<span class='ok' >No orphan video.</span>";
} else {
  echo "<ol>";
  foreach($orphan_videos as $video) {
    echo sprintf("<li>%s</li>", $video);
  }
  echo "</ol>";
}



echo "<h2>Thumbnails without videos</h2>";

$orphan_thumbnails = $db->getOrphanThumbnails($folderPath, $jsonArray['thumbnailsDirectory'], $jsonArray['urlRootThumbnails']);

if (empty($orphan_thumbnails)) {
  echo "<span class='ok' >No orphan thumbnail.</span>";
} else {
  foreach($orphan_thumbnails as $thumbnail) {
    echo sprintf("<img src='%s%s' height='80px' alt='%s' title='%s' />", $jsonArray['urlRootThumbnails'], $thumbnail, $thumbnail, $thumbnail);
  }
}

echo "<h2>Auto Tag pictures</h2>";

$pictures_without_tag = $db->getFilesWithoutTags(True);

if (empty($pictures_without_tag)) {
  echo "<span class='ok' >No picture without tag.</span>";
} else {
  echo sprintf("<a href='?tagPicture=%d'>Tag '%s'</a>", time(), $jsonArray['specialTags']['pictures']);
  echo "<ol>";
  foreach($pictures_without_tag as $picture) {
    echo sprintf("<li>%s</li>", $picture);
  }
  echo "</ol>";
}


echo "<h2>Auto Tag videos</h2>";

$videos_without_tag = $db->getFilesWithoutTags(False, True);

if (empty($videos_without_tag)) {
  echo "<span class='ok' >No video without tag.</span>";
} else {
  echo sprintf("<a href='?tagVideo=%d'>Tag '%s'</a>", time(), $jsonArray['specialTags']['videos']);
  echo "<ol>";
  foreach($videos_without_tag as $video) {
    echo sprintf("<li>%s</li>", $video);
  }
  echo "</ol>";
}


echo "<h2>Auto Tag GIFs</h2>";

$gifs_without_tag = $db->getFilesWithoutTags(False, False, True);

if (empty($gifs_without_tag)) {
  echo "<span class='ok' >No gif without tag.</span>";
} else {
  echo sprintf("<a href='?tagGif=%d'>Tag '%s'</a>", time(), $jsonArray['specialTags']['gif']);
  echo "<ol>";
  foreach($gifs_without_tag as $gif) {
    echo sprintf("<li>%s</li>", $gif);
  }
  echo "</ol>";
}




echo "</body>";
echo "</html>";
