<?php
class Search {
  /**
   * @var $db DB object
   * */
  private $db = False;

  /**
   * @param $db DB object
   * */
  public function __construct($db) {
    $this->db = $db;
  }

  /**
   * @param string $search
   * @param array $basket (fileSlugs)
   * @return array (fileSlugs)
   * */
  public function go($search, $basket = False) {
    # case to get all medias without tags
    if ($search != '' && (substr($search, 0, strlen($search))  == "media-without-tags")) {
      return $this->db->getFilesWithoutTags();
    }
    # END : case to get all medias without tags



    $flagMatching = False;
    
    ###############
    # case "x or y"
    ###############
    $pattern = "/^([^ ]+) or ([^ ]+)/";
    preg_match($pattern, $search, $matches);
    if (!empty($matches) && isset($matches[1]) && isset($matches[2])) {
      $flagMatching = True;
      $firstMatch = $matches[1];
      $secondMatch = $matches[2];

      # update basket and search
      if ($basket !== False) {
        $basket = array_merge($basket, $this->db->getFilesFromSlugs($this->db->getTagAndChildren($secondMatch)));
      } else {
        $basket = array_merge($this->db->getFilesFromSlugs($this->db->getTagAndChildren($firstMatch)), $this->db->getFilesFromSlugs($this->db->getTagAndChildren($secondMatch)));
      }
      $search = substr($search, strpos($search, sprintf(' or %s', $secondMatch))  + strlen(" or ") );

      return $this->go($search, $basket);
    }

    ################
    # case "x and y"
    ################
    $pattern = "/^([^ ]+) and ([^ ]+)/";
    preg_match($pattern, $search, $matches);
    if (!empty($matches) && isset($matches[1]) && isset($matches[2])) {
      $flagMatching = True;
      $firstMatch = $matches[1];
      $secondMatch = $matches[2];

      # update basket and search
      if ($basket !== False) {
        $basket = array_intersect($basket, $this->db->getFilesFromSlugs($this->db->getTagAndChildren($secondMatch)));
      } else {
        $basket = array_intersect($this->db->getFilesFromSlugs($this->db->getTagAndChildren($firstMatch)), $this->db->getFilesFromSlugs($this->db->getTagAndChildren($secondMatch)));
      }

      $search = substr($search, strpos($search, sprintf(' and %s', $secondMatch))  + strlen(" and ") );

      return $this->go($search, $basket);
    }


    ####################
    # case "x without y"
    ####################
    $pattern = "/^([^ ]+) without ([^ ]+)/";
    preg_match($pattern, $search, $matches);
    if (!empty($matches) && isset($matches[1]) && isset($matches[2])) {
      $flagMatching = True;
      $firstMatch = $matches[1];
      $secondMatch = $matches[2];

      # update basket and search
      if ($basket !== False) {
        $basket = array_diff($basket, $this->db->getFilesFromSlugs($this->db->getTagAndChildren($secondMatch)));
      } else {
        $basket = array_diff($this->db->getFilesFromSlugs($this->db->getTagAndChildren($firstMatch)), $this->db->getFilesFromSlugs($this->db->getTagAndChildren($secondMatch)));
      }

      $search = substr($search, strpos($search, sprintf(' without %s', $secondMatch))  + strlen(" without ") );

      return $this->go($search, $basket);
    }

    if ($flagMatching == False) { 
      ###############
      # default case
      ###############
      if ($basket !== False) {
        return $basket;
      } else {
        return $this->db->getFilesFromSlugs($this->db->getTagAndChildren($search));
      }
    }
  }
}
