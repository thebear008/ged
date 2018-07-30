<?php
session_start();

include('class.DB.php');
include('class.Log.php');

# choose media directory from json
$jsonFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . "config.json";
$fileHandle = file_get_contents($jsonFile);
$jsonArray = json_decode($fileHandle, true);

$folderPath = $_SESSION['configDirectory'];

# detect SESSION ended
if (!isset($folderPath)) {
  die('Please choose your data directory');
}

# log if debug == true
$log = new Log((isset($jsonArray['log_file']) ? $jsonArray['log_file'] : 'application.log'), ($jsonArray["debug"] == "true"));
$log->write("Log object created.", "AJAX");

$db = new DB(sprintf('%s%s%s', $folderPath, DIRECTORY_SEPARATOR, '.ged.db'));
# add logger to DB
$db->setLogger($log);

# populateThirdColumn
if (isset($_POST['slug'])) {
  $log->write(sprintf("Populating third column with %s", $_POST['slug']), "AJAX");
  $arrayTags = $db->getTagsFromFile($_POST['slug']);
  echo "<div class='tag-tree-third-column'>";
  echo $db->showTagTree(False, True, $_POST['slug'], $arrayTags);
  echo "</div>";
}

# linkTagToFile
if (isset($_POST['slugFile']) && isset($_POST['checked']) && isset($_POST['slugTag'])) {
  if ($_POST['checked'] == "true") {
    # add link
    $log->write(sprintf("Linking file %s with tag %s", $_POST['slugFile'], $_POST['slugTag']), "AJAX");
    $db->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $_POST['slugFile'], $_POST['slugTag']));
    # check if this tag has siblings
    header('Content-Type: application/json');
    if (isset($jsonArray["tags-of-tags"][$_POST['slugTag']])) {
        echo json_encode($jsonArray["tags-of-tags"][$_POST['slugTag']]);
    }
  } else {
    # remove link
    $log->write(sprintf("Unlinking file %s with tag %s", $_POST['slugFile'], $_POST['slugTag']), "AJAX");
    $db->exec(sprintf('delete from tags_files where file_slug = "%s" and tag_slug = "%s"', $_POST['slugFile'], $_POST['slugTag']));
  }
}

# deleteMedia
if (isset($_POST['myDeleteSlug']) && $_POST['myDeleteSlug'] != '') {
  $myFile = $db->getFile($_POST['myDeleteSlug']);
  if ($myFile) {
    $log->write(sprintf("Deleting media %s", $_POST['myDeleteSlug']), "AJAX");
    $db->deleteFile($_POST['myDeleteSlug'], $jsonArray);
    echo "Media deleted successfully !";
  } else {
    echo "Error : unable to find Media !";
  }
}
