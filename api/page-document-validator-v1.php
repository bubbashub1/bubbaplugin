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
   elseif(!in_array($key,['background','padding','gap','width','min_height','radius','border_width','border_color','align','margin_bottom','mobile'],true))$errors[]='Unknown section property.';
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
   if($type==='image'){\n    if(array_diff(array_keys($p),['src','alt','width','fit','radius','margin_bottom']))$errors[]='Unknown image property.';\n    $src=$p['src']??null;$safe=is_string($src)&&strlen($src)<=2048&&!preg_match('/[\\x00-\\x20\\x7f\\\\]/',$src)&&!str_starts_with($src,'//')&&(str_starts_with($src,'/')||((parse_url($src,PHP_URL_SCHEME)==='https')&&filter_var($src,FILTER_VALIDATE_URL)!==false));\n    if(!$safe)$errors[]='Image source must be a safe https URL or site-relative path.';\n    if(!is_string($p['alt']??null)||mb_strlen($p['alt'])>300)$errors[]='Image alternative text invalid.';\n    if(isset($p['width'])&&(!is_int($p['width'])||$p['width']<10||$p['width']>100))$errors[]='Image width invalid.';\n    if(isset($p['fit'])&&!in_array($p['fit'],['cover','contain'],true))$errors[]='Image fit invalid.';\n    if(isset($p['radius'])&&(!is_int($p['radius'])||$p['radius']<0||$p['radius']>48))$errors[]='Image radius invalid.';\n    if(isset($p['margin_bottom'])&&(!is_int($p['margin_bottom'])||$p['margin_bottom']<0||$p['margin_bottom']>80))$errors[]='Image spacing invalid.';\n   }elseif($type==='heading'){
    if(array_diff(array_keys($p),['text','level','align']))$errors[]='Unknown heading property.';
    if(!array_key_exists('text',$p)||!array_key_exists('level',$p))$errors[]='Heading text and level are required.';
    if(!is_string($p['text']??null)||mb_strlen($p['text'])>500)$errors[]='Heading text invalid.';
    if(!in_array($p['level']??null,[1,2,3,4],true))$errors[]='Heading level invalid.';
   }elseif($type==='paragraph'){
    if(array_diff(array_keys($p),['text','align']))$errors[]='Unknown paragraph property.';
    if(!array_key_exists('text',$p))$errors[]='Paragraph text is required.';
    if(!is_string($p['text']??null)||mb_strlen($p['text'])>10000)$errors[]='Paragraph text invalid.';
   }elseif($type==='button'){
    if(array_diff(array_keys($p),['text','href']))$errors[]='Unknown button property.';
    if(!array_key_exists('text',$p)||!array_key_exists('href',$p))$errors[]='Button text and link are required.';
    if(!is_string($p['text']??null)||trim($p['text'])===''||mb_strlen($p['text'])>120)$errors[]='Button text invalid.';
    $href=$p['href']??null;
    if(!is_string($href)||strlen($href)>2048||!bh_page_document_safe_href_v1($href))$errors[]='Button link invalid.';
   }else $errors[]='Unsupported block type.';
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
