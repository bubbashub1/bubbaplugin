<?php
declare(strict_types=1);

/** Pure validator for Bubba Hub page-document schema v1. No database or session side effects. */
function bh_validate_page_document_v1(mixed $document): array {
 $errors=[];
 if(!is_array($document)) return ['ok'=>false,'errors'=>['Document must be an object.']];
 if(($document['schema_version']??null)!==1) $errors[]='Unsupported schema_version.';
 if(array_diff(array_keys($document),['schema_version','page','settings','sections'])) $errors[]='Unknown top-level properties.';
 if(!isset($document['page'])||!is_array($document['page'])) $errors[]='page must be an object.';
 else {
  if(!is_string($document['page']['title']??null)||trim($document['page']['title'])===''||mb_strlen($document['page']['title'])>190) $errors[]='Page title is invalid.';
  $slug=$document['page']['slug']??null;
  if(!is_string($slug)||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)||strlen($slug)>190) $errors[]='Page slug is invalid.';
  if(array_diff(array_keys($document['page']),['title','slug']))$errors[]='Unknown page properties.';
 }
 $settings=$document['settings']??null;
 if(!is_array($settings))$errors[]='settings must be an object.';
 else{
  foreach($settings as $key=>$value){
   if($key==='max_width'&&!in_array($value,['960px','1100px','1200px'],true))$errors[]='Invalid max_width.';
   elseif($key==='background'&&(!is_string($value)||!preg_match('/^#[0-9a-fA-F]{6}$/',$value)))$errors[]='Invalid background colour.';
   elseif($key==='columns'&&(!is_int($value)||$value<1||$value>3))$errors[]='Invalid page column count.';
   elseif(!in_array($key,['max_width','background','columns'],true))$errors[]='Unknown settings property.';
  }
 }
 $sections=$document['sections']??null;
 if(!is_array($sections)||count($sections)>40)$errors[]='sections must be an array of at most 40 items.';
 $ids=[];$nodes=0;
 $idCheck=function($id)use(&$errors,&$ids,&$nodes){$nodes++;if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,64}$/',$id)){$errors[]='Invalid element ID.';return;}if(isset($ids[$id]))$errors[]='Duplicate element ID.';$ids[$id]=true;};
 if(is_array($sections))foreach($sections as $section){
  if(!is_array($section)||($section['type']??null)!=='section'){$errors[]='Each top-level node must be a section.';continue;}
  if(array_diff(array_keys($section),['id','type','props','children']))$errors[]='Unknown section properties.';
  $idCheck($section['id']??null);
  $props=$section['props']??[];
  if(!array_key_exists('props',$section)||!is_array($props)){$errors[]='Section props must be an object.';$props=[];}
  foreach($props as $key=>$value){
   if($key==='background'&&(!is_string($value)||!preg_match('/^#[0-9a-fA-F]{6}$/',$value)))$errors[]='Invalid section background.';
   elseif($key==='padding'&&(!is_int($value)||$value<0||$value>96))$errors[]='Invalid section padding.';
   elseif($key==='gap'&&(!is_int($value)||$value<0||$value>64))$errors[]='Invalid section gap.';
   elseif($key==='width'&&(!is_int($value)||$value<25||$value>100))$errors[]='Invalid container width.';
   elseif($key==='min_height'&&(!is_int($value)||$value<0||$value>800))$errors[]='Invalid container minimum height.';
   elseif($key==='radius'&&(!is_int($value)||$value<0||$value>48))$errors[]='Invalid container radius.';
   elseif($key==='border_width'&&(!is_int($value)||$value<0||$value>8))$errors[]='Invalid container border width.';
   elseif($key==='border_color'&&(!is_string($value)||!preg_match('/^#[0-9a-fA-F]{6}$/',$value)))$errors[]='Invalid container border colour.';
   elseif($key==='align'&&!in_array($value,['left','center','right'],true))$errors[]='Invalid container alignment.';
   elseif($key==='margin_bottom'&&(!is_int($value)||$value<0||$value>96))$errors[]='Invalid container spacing.';
   elseif($key==='background_image'){
    $safeImage=$value==='';
    if(is_string($value)&&strlen($value)<=2048&&!preg_match('/\s/',$value)&&!str_contains($value,chr(92))&&!str_starts_with($value,'//')){
     $scheme=parse_url($value,PHP_URL_SCHEME);
     $safeImage=$safeImage||str_starts_with($value,'/')||($scheme==='https'&&filter_var($value,FILTER_VALIDATE_URL)!==false);
    }
    if(!$safeImage)$errors[]='Invalid container background image.';
   }
   elseif($key==='overlay_color'&&(!is_string($value)||!preg_match('/^#[0-9a-fA-F]{6}$/',$value)))$errors[]='Invalid container overlay colour.';
   elseif($key==='overlay_opacity'&&(!is_numeric($value)||$value<0||$value>1))$errors[]='Invalid container overlay opacity.';
   elseif($key==='background_position'&&!in_array($value,['center center','center top','center bottom','left center','right center'],true))$errors[]='Invalid container background position.';
   elseif($key==='color'&&(!is_string($value)||!preg_match('/^#[0-9a-fA-F]{6}$/',$value)))$errors[]='Invalid container text colour.';
   elseif($key==='palette'&&!in_array($value,['custom','meadow','coastal','sunshine','blush','neutral'],true))$errors[]='Invalid container colour theme.';
   elseif($key==='gradient'&&!in_array($value,['','meadow','coastal','sunset','berry','sand'],true))$errors[]='Invalid container background style.';
   elseif($key==='columns'&&(!is_int($value)||$value<1||$value>4))$errors[]='Invalid container column count.';
   elseif($key==='column_widths'){
    if(!is_array($value)||count($value)<1||count($value)>4)$errors[]='Invalid container column widths.';
    else foreach($value as $columnWidth)if(!is_numeric($columnWidth)||$columnWidth<10||$columnWidth>100)$errors[]='Invalid container column width.';
   }
   elseif($key==='mobile'){
    $mobileAllowed=['width','padding','gap','min_height','radius','border_width','margin_bottom','align'];
    if(!is_array($value)||array_diff(array_keys($value),$mobileAllowed))$errors[]='Invalid mobile layout properties.';
    else foreach($value as $mobileKey=>$mobileValue){
     if($mobileKey==='width'&&(!is_int($mobileValue)||$mobileValue<25||$mobileValue>100))$errors[]='Invalid mobile container width.';
     elseif($mobileKey==='padding'&&(!is_int($mobileValue)||$mobileValue<0||$mobileValue>96))$errors[]='Invalid mobile container padding.';
     elseif($mobileKey==='gap'&&(!is_int($mobileValue)||$mobileValue<0||$mobileValue>64))$errors[]='Invalid mobile container gap.';
     elseif($mobileKey==='min_height'&&(!is_int($mobileValue)||$mobileValue<0||$mobileValue>800))$errors[]='Invalid mobile container minimum height.';
     elseif($mobileKey==='radius'&&(!is_int($mobileValue)||$mobileValue<0||$mobileValue>48))$errors[]='Invalid mobile container radius.';
     elseif($mobileKey==='border_width'&&(!is_int($mobileValue)||$mobileValue<0||$mobileValue>8))$errors[]='Invalid mobile container border width.';
     elseif($mobileKey==='margin_bottom'&&(!is_int($mobileValue)||$mobileValue<0||$mobileValue>96))$errors[]='Invalid mobile container spacing.';
     elseif($mobileKey==='align'&&!in_array($mobileValue,['left','center','right'],true))$errors[]='Invalid mobile container alignment.';
    }
   }
   elseif(!in_array($key,['background','background_image','overlay_color','overlay_opacity','background_position','color','palette','gradient','columns','column_widths','padding','gap','width','min_height','radius','border_width','border_color','align','margin_bottom','mobile'],true))$errors[]='Unknown section property.';
  }
  if(!array_key_exists('children',$section))$errors[]='Section children are required.';
  $children=$section['children']??null;
  if(!is_array($children)||count($children)>80){$errors[]='Section children must be an array of at most 80 items.';continue;}
  foreach($children as $child){
   if(!is_array($child)){$errors[]='Block must be an object.';continue;}
   $type=$child['type']??null;
   $allowed=['id','type','props'];
   if(array_diff(array_keys($child),$allowed))$errors[]='Unknown block properties.';
   if(!array_key_exists('id',$child)||!array_key_exists('type',$child)||!array_key_exists('props',$child))$errors[]='Block id, type and props are required.';
   $idCheck($child['id']??null);$p=$child['props']??null;
   if(!is_array($p)){$errors[]='Block props must be an object.';continue;}
   if($type==='image'){
    if(array_diff(array_keys($p),['src','alt','width','fit','radius','margin_bottom','font_size','align','color','background']))$errors[]='Unknown image property.';
    $src=$p['src']??null;$safe=false;if(is_string($src)&&strlen($src)<=2048&&!preg_match('/[\\x00-\\x20\\x7f\\\\]/',$src)&&!str_starts_with($src,'//')){$scheme=parse_url($src,PHP_URL_SCHEME);$safe=str_starts_with($src,'/')||($scheme==='https'&&filter_var($src,FILTER_VALIDATE_URL)!==false);}
    if(!$safe)$errors[]='Image source must be a safe https URL or site-relative path.';
    if(!is_string($p['alt']??null)||mb_strlen($p['alt'])>300)$errors[]='Image alternative text invalid.';
    if(isset($p['width'])&&(!is_int($p['width'])||$p['width']<10||$p['width']>100))$errors[]='Image width invalid.';
    if(isset($p['fit'])&&!in_array($p['fit'],['cover','contain'],true))$errors[]='Image fit invalid.';
    if(isset($p['radius'])&&(!is_int($p['radius'])||$p['radius']<0||$p['radius']>48))$errors[]='Image radius invalid.';
    if(isset($p['margin_bottom'])&&(!is_int($p['margin_bottom'])||$p['margin_bottom']<0||$p['margin_bottom']>80))$errors[]='Image spacing invalid.';
    if(isset($p['font_size'])&&(!is_int($p['font_size'])||$p['font_size']<10||$p['font_size']>72))$errors[]='Image size invalid.';
    if(isset($p['align'])&&!in_array($p['align'],['left','center','right'],true))$errors[]='Image alignment invalid.';
    foreach(['color','background'] as $colourKey)if(isset($p[$colourKey])&&(!is_string($p[$colourKey])||!preg_match('/^#[0-9a-fA-F]{6}$/',$p[$colourKey])))$errors[]='Image colour invalid.';
   }elseif($type==='heading'){
    if(array_diff(array_keys($p),['text','level','align','font_size','width','margin_bottom','color','background','radius','padding']))$errors[]='Unknown heading property.';
    if(!array_key_exists('text',$p)||!array_key_exists('level',$p))$errors[]='Heading text and level are required.';
    if(!is_string($p['text']??null)||mb_strlen($p['text'])>500)$errors[]='Heading text invalid.';
    if(!in_array($p['level']??null,[1,2,3,4],true))$errors[]='Heading level invalid.';
   }elseif($type==='paragraph'){
    if(array_diff(array_keys($p),['text','align','font_size','width','margin_bottom','color','background','radius','padding']))$errors[]='Unknown paragraph property.';
    if(!array_key_exists('text',$p))$errors[]='Paragraph text is required.';
    if(!is_string($p['text']??null)||mb_strlen($p['text'])>10000)$errors[]='Paragraph text invalid.';
   }elseif($type==='directory_search'){
    $allowedSearch=['label','placeholder','search_scope','show_filters','search_fields','filter_fields','font_size','width','align','margin_bottom','color','background','radius','padding'];
    if(array_diff(array_keys($p),$allowedSearch))$errors[]='Unknown directory search property.';
    if(!is_string($p['label']??null)||mb_strlen($p['label'])>100)$errors[]='Directory search label invalid.';
    if(!is_string($p['placeholder']??null)||mb_strlen($p['placeholder'])>150)$errors[]='Directory search placeholder invalid.';
    if(!in_array($p['search_scope']??'all',['all','activities','businesses','childcare','events','places','school_clubs'],true))$errors[]='Directory search scope invalid.';
    if(isset($p['show_filters'])&&!is_bool($p['show_filters']))$errors[]='Directory search filter setting invalid.';
    foreach(['search_fields','filter_fields'] as $fieldList){
     if(!isset($p[$fieldList]))continue;
     $fieldConfig=$p[$fieldList];
     if(!is_array($fieldConfig)||count($fieldConfig)>80){$errors[]='Directory search field list invalid.';continue;}
     $directoryKeys=['activities','businesses','childcare','events','places','school_clubs'];
     $isDirectoryMap=(bool)array_intersect(array_keys($fieldConfig),$directoryKeys);
     $fieldGroups=$isDirectoryMap?$fieldConfig:['default'=>$fieldConfig];
     $totalFields=0;
     foreach($fieldGroups as $directoryKey=>$fields){
      if($isDirectoryMap&&!in_array($directoryKey,$directoryKeys,true)){$errors[]='Directory search field group invalid.';continue;}
      if(!is_array($fields)){$errors[]='Directory search field group invalid.';continue;}
      $totalFields+=count($fields);
      foreach($fields as $field)if(!is_string($field)||!preg_match('/^[a-z][a-z0-9_]{0,63}$/',$field))$errors[]='Directory search field name invalid.';
     }
     if($totalFields>80)$errors[]='Directory search field list invalid.';
    }
    if(isset($p['font_size'])&&(!is_int($p['font_size'])||$p['font_size']<10||$p['font_size']>72))$errors[]='Directory search size invalid.';
    if(isset($p['width'])&&(!is_int($p['width'])||$p['width']<10||$p['width']>100))$errors[]='Directory search width invalid.';
    if(isset($p['align'])&&!in_array($p['align'],['left','center','right'],true))$errors[]='Directory search alignment invalid.';
    if(isset($p['margin_bottom'])&&(!is_int($p['margin_bottom'])||$p['margin_bottom']<0||$p['margin_bottom']>80))$errors[]='Directory search spacing invalid.';
    foreach(['color','background'] as $colourKey)if(isset($p[$colourKey])&&(!is_string($p[$colourKey])||!preg_match('/^#[0-9a-fA-F]{6}$/',$p[$colourKey])))$errors[]='Directory search colour invalid.';
    if(isset($p['radius'])&&(!is_int($p['radius'])||$p['radius']<0||$p['radius']>48))$errors[]='Directory search radius invalid.';
    if(isset($p['padding'])&&(!is_int($p['padding'])||$p['padding']<0||$p['padding']>48))$errors[]='Directory search padding invalid.';
   }elseif($type==='button'){
    if(array_diff(array_keys($p),['text','href','font_size','width','align','margin_bottom','color','background','radius','padding']))$errors[]='Unknown button property.';
    if(!array_key_exists('text',$p)||!array_key_exists('href',$p))$errors[]='Button text and link are required.';
    if(!is_string($p['text']??null)||trim($p['text'])===''||mb_strlen($p['text'])>120)$errors[]='Button text invalid.';
    $href=$p['href']??null;
    if(!is_string($href)||strlen($href)>2048||!bh_page_document_safe_href_v1($href))$errors[]='Button link invalid.';
   }elseif($type==='library_widget'){
    if(array_diff(array_keys($p),['widget_key','widget_type','name','description','settings','text','width','align','margin_bottom','color','background','radius','padding','font_size']))$errors[]='Unknown library widget property.';
    if(!is_string($p['widget_key']??null)||!preg_match('/^[a-z0-9][a-z0-9-]{1,79}$/',$p['widget_key']))$errors[]='Library widget key invalid.';
    if(!is_string($p['widget_type']??null)||!preg_match('/^[a-z][a-z0-9_]{1,59}$/',$p['widget_type']))$errors[]='Library widget type invalid.';
    if(!is_string($p['name']??null)||trim($p['name'])===''||mb_strlen($p['name'])>120)$errors[]='Library widget name invalid.';
    if(isset($p['description'])&&(!is_string($p['description'])||mb_strlen($p['description'])>500))$errors[]='Library widget description invalid.';
    if(isset($p['text'])&&(!is_string($p['text'])||mb_strlen($p['text'])>500))$errors[]='Library widget label invalid.';
    if(isset($p['settings'])&&(!is_array($p['settings'])||strlen((string)json_encode($p['settings']))>12000))$errors[]='Library widget settings invalid.';
    if(($p['widget_type']??null)==='tabs'&&isset($p['settings'])){
     $tabSettings=$p['settings'];
     if(!is_array($tabSettings)||array_diff(array_keys($tabSettings),['label','items','active_tab','tab_style']))$errors[]='Tabs settings invalid.';
     else{
      $items=$tabSettings['items']??null;
      if(!is_array($items)||count($items)<1||count($items)>20)$errors[]='Tabs must contain between 1 and 20 items.';
      else foreach($items as $tab){
       if(!is_array($tab)||array_diff(array_keys($tab),['title','content'])||!is_string($tab['title']??null)||trim($tab['title'])===''||mb_strlen($tab['title'])>100||!is_string($tab['content']??null)||mb_strlen($tab['content'])>5000)$errors[]='A tab title or content is invalid.';
      }
      if(isset($tabSettings['active_tab'])&&(!is_int($tabSettings['active_tab'])||$tabSettings['active_tab']<0||!is_array($items)||$tabSettings['active_tab']>=count($items)))$errors[]='Active tab selection is invalid.';
      if(isset($tabSettings['tab_style'])&&!in_array($tabSettings['tab_style'],['classic','pills','underline'],true))$errors[]='Tabs appearance is invalid.';
     }
    }
   }else $errors[]='Unsupported block type.';
   foreach(['font_size'=>[10,72],'width'=>[10,100],'margin_bottom'=>[0,80],'radius'=>[0,48],'padding'=>[0,48]] as $styleKey=>$range){
    if(isset($p[$styleKey])&&(!is_int($p[$styleKey])||$p[$styleKey]<$range[0]||$p[$styleKey]>$range[1]))$errors[]='Invalid block '.$styleKey.'.';
   }
   foreach(['color','background'] as $colourKey)if(isset($p[$colourKey])&&(!is_string($p[$colourKey])||!preg_match('/^#[0-9a-fA-F]{6}$/',$p[$colourKey])))$errors[]='Invalid block colour.';
   if(isset($p['align'])&&!in_array($p['align'],['left','center','right'],true))$errors[]='Invalid alignment.';
  }
 }
 if($nodes>4000)$errors[]='Document node limit exceeded.';
 $encoded=json_encode($document,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 if($encoded===false||strlen($encoded)>500000)$errors[]='Document size exceeds 500 KB.';
 return ['ok'=>!$errors,'errors'=>array_values(array_unique($errors))];
}
function bh_page_document_safe_href_v1(string $href): bool {
 if($href===''||preg_match('/[\x00-\x20\x7f]/',$href)||str_contains($href,'\\')||str_starts_with($href,'//'))return false;
 if(preg_match('/^https?:/i',$href)){
  $parts=parse_url($href);
  return is_array($parts)&&isset($parts['host'])&&filter_var($href,FILTER_VALIDATE_URL)!==false;
 }
 if(str_starts_with(strtolower($href),'mailto:')){
  $email=substr($href,7);
  return filter_var($email,FILTER_VALIDATE_EMAIL)!==false;
 }
 if(str_starts_with(strtolower($href),'tel:')){
  $number=substr($href,4);
  return (bool)preg_match('/^\+?[0-9().-]{3,32}$/',$number);
 }
 if(str_starts_with($href,'/'))return true;
 if(str_starts_with($href,'#')||str_starts_with($href,'?'))return strlen($href)>1;
 return false;
}
