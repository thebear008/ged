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
  if (!isset($argv[1])) {
    die("Need one argument : pathFolder");
  }
  $folderPath = $argv[1];
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
					$db->exec(sprintf('insert into myFiles (label, slug) values ("%s", "%s")', $content, $mySlugifiedText));
				}
      }
    }
  }

  echo sprintf("Listing DB content\n");
  $result = $db->query("select * from myFiles");
  while ($myResult = $result->fetchArray()){
     print_r($myResult);
  }

  $db->close();
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

  # SQLite3
  echo sprintf("<h2>%s</h2>", "Media listing");
  $folderPath = "/var/www/html/ged/";
  $db = new SQLite3(sprintf('%s%s%s', $folderPath, DIRECTORY_SEPARATOR, '.ged.db'));
  $result = $db->query("select label from myFiles order by label");
  echo "<ul>";
  while ($myResult = $result->fetchArray()){
    echo sprintf("<li>%s</li>", $myResult[0]);
  }
  echo "</ul>";

  echo "</body>";
  echo "</html>";
}
