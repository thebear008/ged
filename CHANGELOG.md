# TODO

- [ ] manage txt files into datas
- [ ] metadata on tags

# 1.1.11

- [X] Add config field `width_diff_px_element_third_column`

# 1.1.10

- [X] auto-size third column picture/video

# 1.1.9

- [X] resizable columns (CSS)

# 1.1.8

- [X] clean myFiles table when refreshing DB
- [X] tag "pictures" pictures even if other tags linked when refreshing DB (config file value autoTagPicture)
- [X] tag "videos" videos even if other tags linked when refreshing DB (config file value autoTagVideo)

# 1.1.7

- [X] handle non slugified tag for pictures or videos
- [X] detect SESSION ended into ajax.php

# 1.1.5

- [X] remove blocking CSS from issue.php

# 1.1.4

- [X] manage set time limit execution into config.json
- [X] create class.Log.php, enabled/disabled with debug value into config.json
- [X] remove php cli part

# 1.1.3

- [X] add right padding to second column pictures
- [X] remove useless ` <ul> ` 
- [X] auto tag pictures and videos when refreshing db

# 1.1.2

- [X] tag "pictures" pictures without any tag
- [X] tag "videos" videos without any tag
- [X] config maxWidth for right column picture
- [X] config maxHeight for right column picture
- [X] config height for middle column pictures
- [X] detect videos without thumbnails
- [X] detect thumbnails without videos

# 1.1.1

- [X] first column open/close tree link
- [X] third column open/close tree link

# 1.1.0

- [X] enable links between media and leaf-tag only
- [X] toggle tags with children (third column)
- [X] add "clean DB" to delete links between media and non-leaf-tag

# 1.0.10

- [X] remove UL CSS modifications
- [X] CSS class to tags with children
- [X] add jQuery library
- [X] toggle tags with children (first column)

# 1.0.9

- [X] show/hide titles within config file
- [X] move "header" markup into first column
- [X] first column split into two rows (height into config file)
- [X] third column split into two rows (height into config file)

# 1.0.8

- [X] three scrollable columns
- [X] "all-files" special tag : allowing "all-files without xxx"
- [X] Redefined "media without tags" link

# 1.0.7

- [X] window.scrollTo(0,0) when clicking datas
- [X] custom title for platform
- [X] refresh DB only if manual action launched
- [X] add labels onto tags for checkbox
- [X] select datas without tags

# 1.0.6

- [X] add default root for xampp (windows)

# 1.0.5

- [X] fix add button function ("add" instead of "and")

# 1.0.4

- [X] add reset button for search field

# 1.0.3

- [X] search infini !
- [X] Unable to tag video !
- [X] javascript from tree to search field

# 1.0.2

- [X] delete file properly from UI
- [X] thumbnail can be several picture formats (only png for the moment)

# 1.0.1

- [X] Show media, if possible, instead of label
- [X] Command line to add tag into database
- [X] Recursive function to get requested tag with all its sons
- [X] json config pour le directory
- [X] video mp4 : thumbnail directory
- [X] link files to tags with checkboxes
