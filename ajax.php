<?php
session_start();

include('class.DB.php');

# choose media directory from json
$jsonFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . "config.json";
$fileHandle = file_get_contents($jsonFile);
$jsonArray = json_decode($fileHandle, true);

$folderPath = $_SESSION['configDirectory'];

$db = new DB(sprintf('%s%s%s', $folderPath, DIRECTORY_SEPARATOR, '.ged.db'));

# populateThirdColumn
if (isset($_POST['slug'])) {
  $arrayTags = $db->getTagsFromFile($_POST['slug']);
  echo $db->showTagTree(False, True, $_POST['slug'], $arrayTags);
}

# linkTagToFile
if (isset($_POST['slugFile']) && isset($_POST['checked']) && isset($_POST['slugTag'])) {
  if ($_POST['checked'] == "true") {
    # add link
    $db->exec(sprintf('insert into tags_files(file_slug, tag_slug) values ("%s", "%s")', $_POST['slugFile'], $_POST['slugTag']));
  } else {
    # remove link
    $db->exec(sprintf('delete from tags_files where file_slug = "%s" and tag_slug = "%s"', $_POST['slugFile'], $_POST['slugTag']));
  }
}

# deleteMedia
if (isset($_POST['myDeleteSlug']) && $_POST['myDeleteSlug'] != '') {
  echo "Media deleted successfully !";
}
