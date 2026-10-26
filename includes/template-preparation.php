<?php
require_once __DIR__ . '/document-template-helpers.php';

function barangay_template_read_package(string $path): array {
  if (!class_exists('ZipArchive') || !is_file($path)) throw new InvalidArgumentException('Choose a valid Word DOCX file.');
  if (filesize($path)>10*1024*1024) throw new InvalidArgumentException('Choose a DOCX file smaller than 10 MB.');
  $zip=new ZipArchive();
  if ($zip->open($path)!==true) throw new InvalidArgumentException('This file could not be read as a Word DOCX.');
  try {
    if ($zip->numFiles>500 || $zip->locateName('word/document.xml')===false || $zip->locateName('[Content_Types].xml')===false) throw new InvalidArgumentException('Choose a standard Word DOCX document.');
    $total=0;$parts=[];
    for($i=0;$i<$zip->numFiles;$i++) {
      $entry=$zip->statIndex($i);$name=$entry['name'];$total+=(int)$entry['size'];
      if($total>30*1024*1024 || preg_match('/vbaProject\.bin$/i',$name)) throw new InvalidArgumentException('Choose a standard DOCX without macros, with a smaller image size.');
      if(preg_match('/^word\/(document|header\d*|footer\d*)\.xml$/D',$name)) {
        if((int)$entry['size']>2*1024*1024) throw new InvalidArgumentException('The document text is too large. Use a shorter template.');
        $xml=$zip->getFromIndex($i);
        if(!is_string($xml) || preg_match('/<!DOCTYPE/i',$xml)) throw new InvalidArgumentException('The Word document contains unsupported XML. Save it as a new DOCX and try again.');
        $parts[$name]=$xml;
      }
    }
    ksort($parts);return $parts;
  } finally {$zip->close();}
}
function barangay_template_xml(string $xml): array {
  $previous=libxml_use_internal_errors(true);$doc=new DOMDocument();$doc->preserveWhiteSpace=true;$doc->formatOutput=false;
  $ok=$doc->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($previous);
  if(!$ok)throw new InvalidArgumentException('Some Word text could not be read. Save a fresh DOCX and try again.');
  $xpath=new DOMXPath($doc);$xpath->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');return [$doc,$xpath];
}
function barangay_template_paragraph_segments(DOMElement $paragraph,DOMXPath $xpath): array {
  $segments=[];$text='';
  foreach($xpath->query('.//w:t|.//w:tab|.//w:br|.//w:cr',$paragraph) as $node) {
    $parent=$node->parentNode;
    while($parent && !($parent instanceof DOMElement && $parent->localName==='p' && $parent->namespaceURI==='http://schemas.openxmlformats.org/wordprocessingml/2006/main'))$parent=$parent->parentNode;
    if($parent!==$paragraph)continue;
    $value=$node->localName==='t'?$node->nodeValue:($node->localName==='tab'?"\t":"\n");
    $segments[]=['node'=>$node,'start'=>mb_strlen($text),'length'=>mb_strlen($value),'editable'=>$node->localName==='t'];$text.=$value;
  }
  return [$text,$segments];
}
function barangay_template_catalog(array $definitions,array $existingTags): array {
  $catalog=[];$labels=['resident_name'=>'Resident name (uppercase)','resident_name_proper'=>'Resident name (as entered)','resident_address'=>'Resident address','age'=>'Resident age','civil_status'=>'Civil status','purpose'=>'Purpose','date_issued'=>'Date issued','document_number'=>'Document number','date_day_ordinal'=>'Issued day (25th)','date_month'=>'Issued month','date_month_upper'=>'Issued month (uppercase)','date_year'=>'Issued year'];
  foreach(BARANGAY_STANDARD_DOCUMENT_KEYS as $key)$catalog[$key]=['key'=>$key,'label'=>$labels[$key]??ucfirst(str_replace('_',' ',$key)),'standard'=>true];
  $common=[['business_name','Business name','text'],['business_address','Business address','textarea'],['business_type','Nature of business','text'],['monthly_income','Monthly income','number'],['annual_income','Annual income','number'],['occupation','Occupation','text'],['organization_name','Organization name','text']];
  foreach($common as [$key,$label,$type])$catalog[$key]=['key'=>$key,'label'=>$label,'type'=>$type,'required'=>false,'options'=>[],'standard'=>false];
  foreach($definitions as $field){$field=barangay_custom_field_definitions([$field])[0];$catalog[$field['key']]=$field+['standard'=>false];}
  foreach($existingTags as $key) {
    if(isset($catalog[$key]))continue;
    if(!preg_match('/^[a-z][a-z0-9_]{1,39}$/D',$key))throw new InvalidArgumentException('The existing Word tag {{'.$key.'}} is not supported. Use lowercase letters, numbers and underscores in its tag name.');
    $type=preg_match('/(?:date|_since)$/',$key)?'date':(preg_match('/(?:amount|income|count|quantity)$/',$key)?'number':(preg_match('/(?:address|notes|details)$/',$key)?'textarea':'text'));
    $catalog[$key]=['key'=>$key,'label'=>ucfirst(str_replace('_',' ',$key)),'type'=>$type,'required'=>false,'options'=>[],'standard'=>false];
  }
  return $catalog;
}
function barangay_template_tag_ranges(string $text): array {
  preg_match_all('/\{\{[^{}]*\}\}/u',$text,$matches,PREG_OFFSET_CAPTURE);$ranges=[];
  foreach($matches[0] as [$value,$offset])$ranges[]=['start'=>mb_strlen(substr($text,0,$offset)),'length'=>mb_strlen($value)];
  return $ranges;
}
function barangay_template_overlap(int $start,int $length,array $ranges): bool {
  foreach($ranges as $range)if($start<$range['start']+$range['length'] && $range['start']<$start+$length)return true;
  return false;
}
function barangay_template_suggestions(string $text,string $id): array {
  $items=[];$taken=barangay_template_tag_ranges($text);
  $add=function(string $pattern,string $key,string $reason,int $group=1)use($text,$id,&$items,&$taken):void {
    preg_match_all($pattern,$text,$matches,PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
    foreach($matches as $match) {
      [$source,$offset]=$match[$group];$trim=trim($source," \t\n\r\0\x0B,.;");
      if($trim==='' || mb_strlen($trim)>300)continue;
      $leading=strpos($source,$trim);$offset+=$leading===false?0:$leading;
      $start=mb_strlen(substr($text,0,$offset));$length=mb_strlen($trim);
      if(barangay_template_overlap($start,$length,$taken))continue;
      if($key==='age' && (!ctype_digit($trim) || (int)$trim>130))continue;
      if($key==='resident_name_proper' && preg_match('/\b(?:years?|yrs?|old|single|married|residing|resident|certify|business|address)\b|[0-9]/iu',$trim))continue;
      if($key==='resident_name_proper' && mb_strtoupper($trim)===$trim && preg_match('/[A-Z]/',$trim))$useKey='resident_name';elseif($key==='date_month' && mb_strtoupper($trim)===$trim)$useKey='date_month_upper';else $useKey=$key;
      $items[]=['paragraph'=>$id,'start'=>$start,'length'=>$length,'source'=>$trim,'key'=>$useKey,'reason'=>$reason];$taken[]=['start'=>$start,'length'=>$length];
    }
  };
  $stop='(?=\s+(?:Age|Address|Gender|Sex|Civil\s+status|Purpose|Date|Business\s+name|Business\s+address|Business\s+type|Nature\s+of\s+business|Monthly\s+income|Annual\s+income|Occupation)\s*[:：]|;|\r|\n|$)';
  foreach([
    ['(?:Business name|Name of business)','business_name'],['(?:Business address)','business_address'],['(?:Nature of business|Business type)','business_type'],['(?:Monthly income)','monthly_income'],['(?:Annual income)','annual_income'],['(?:Occupation)','occupation'],
    ['(?:Resident(?:\x{2019}|\x27)?s? name|Applicant(?:\x{2019}|\x27)?s? name|Full name|Name of (?:resident|applicant)|Name)','resident_name_proper'],
    ['(?:Resident address|Home address|Address)','resident_address'],['(?:Age)','age'],['(?:Civil status)','civil_status'],['(?:Gender|Sex)','gender'],['(?:Purpose)','purpose'],
  ] as [$label,$key])$add('/\b'.$label.'\s*[:：]\s*([^\r\n;]+?)'.$stop.'/iu',$key,'Suggested from a labelled detail.');
  $add('/\bcertif(?:y|ies|ied)\s+that\s+(.+?)(?=,\s*(?:\d{1,3}\s*(?:years?|yrs?)|of legal age|(?:a\s+)?(?:Filipino|male|female|single|married|widowed|separated))|\s+is\s+(?:a\s+)?(?:bona fide|bonafide|resident)|,\s*(?:a|an)\s+(?:bona fide|bonafide|resident))/iu','resident_name_proper','Suggested from the certification sentence.');
  $add('/\b(\d{1,3})(?=\s*(?:years?|yrs?)\s+old\b)/iu','age','Suggested from “years old”.');
  $add('/\b(single|married|widowed|separated|divorced)\b(?=\s*(?:,|and|Filipino|resident|citizen|of))/iu','civil_status','Possible civil status; review the sentence.');
  $add('/\b(?:residing at|resident of|residing in)\s+(.+?)(?=,\s*(?:is|has|who|this|and)\b|\s+(?:is hereby|has been|for the purpose|for purposes)|[.;]?\s*$)/iu','resident_address','Suggested from the residence sentence.');
  $add('/\b(?:for the purpose of|for purposes of|issued for)\s+(.+?)(?=[.;]?\s*$)/iu','purpose','Possible document purpose; review the wording.');
  $add('/\b(?:Document|Certificate|Control)\s*(?:No\.?|Number|#)\s*[:：]\s*([\w\/-]+)/iu','document_number','Suggested from the document reference label.');
  $date='((?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+\d{1,2}(?:st|nd|rd|th)?[,]?\s+\d{4}|\d{4}-\d{2}-\d{2}|\d{1,2}[\/-]\d{1,2}[\/-]\d{4})';
  $add('/\b(?:Date issued|Date of issuance|Issued on)\s*[:：]\s*'.$date.'/iu','date_issued','Possible issuance date; confirm it is not a birthdate.');
  $add('/\b(?:Given|Issued|Signed)(?:\s+this|\s+on)?\s+'.$date.'/iu','date_issued','Suggested from the issuance sentence.');
  $add('/\b(?:this|on the)\s+(\d{1,2}(?:st|nd|rd|th))\s+day\s+of\b/iu','date_day_ordinal','Suggested from the issued-day wording.');
  $add('/\bday\s+of\s+([A-Za-z]+)\s*,?\s*\d{4}/iu','date_month','Suggested from the issued-month wording.');
  $add('/\bday\s+of\s+[A-Za-z]+\s*,?\s*(\d{4})/iu','date_year','Suggested from the issued-year wording.');
  return $items;
}
function barangay_template_analyze(string $path,array $definitions=[]): array {
  $parts=barangay_template_read_package($path);$paragraphs=[];$suggestions=[];$tags=[];$textSize=0;$partNumber=0;
  foreach($parts as $part=>$xml) {
    [$doc,$xpath]=barangay_template_xml($xml);
    foreach($xpath->query('//w:p') as $index=>$paragraph) {
      [$text]=barangay_template_paragraph_segments($paragraph,$xpath);if(trim($text)==='')continue;
      $textSize+=strlen($text);if(count($paragraphs)>=300 || $textSize>200000 || mb_strlen($text)>8000)throw new InvalidArgumentException('Use a shorter template with up to 300 text paragraphs.');
      $id='p'.$partNumber.'_'.$index;$paragraphs[$id]=['id'=>$id,'part'=>$part,'index'=>$index,'text'=>$text];
      preg_match_all('/\{\{([^{}]*)\}\}/u',$text,$matches);foreach($matches[1] as $tag)$tags[]=trim($tag);
      $suggestions=array_merge($suggestions,barangay_template_suggestions($text,$id));
    }
    $partNumber++;
  }
  if(!$paragraphs)throw new InvalidArgumentException('No editable Word text was found. Scanned pages and text saved inside images need an editable DOCX.');
  $tags=array_values(array_unique($tags));$catalog=barangay_template_catalog($definitions,$tags);
  return ['paragraphs'=>$paragraphs,'suggestions'=>array_slice($suggestions,0,120),'existing_tags'=>$tags,'catalog'=>$catalog];
}
function barangay_template_prepare(string $source,string $target,array $mappings,array $definitions=[]): array {
  $plan=barangay_template_analyze($source,$definitions);$catalog=$plan['catalog'];
  if(count($mappings)>120)throw new InvalidArgumentException('Use up to 120 text replacements.');
  $byPart=[];$ranges=[];$used=$plan['existing_tags'];
  foreach($mappings as $mapping) {
    if(!is_array($mapping) || !is_string($mapping['paragraph']??null) || !isset($plan['paragraphs'][$mapping['paragraph']]))throw new InvalidArgumentException('Choose a valid text paragraph.');
    $paragraph=$plan['paragraphs'][$mapping['paragraph']];$id=$paragraph['id'];$key=$mapping['key']??'';
    if(!is_string($key) || !isset($catalog[$key]))throw new InvalidArgumentException('Choose a listed field for each replacement.');
    $start=$mapping['start']??null;$length=$mapping['length']??null;$text=$mapping['source']??null;
    if(!is_int($start) || !is_int($length) || $start<0 || $length<1 || !is_string($text) || $length!==mb_strlen($text) || mb_substr($paragraph['text'],$start,$length)!==$text)throw new InvalidArgumentException('Some selected text no longer matches the file. Analyze the uploaded file again.');
    if(barangay_template_overlap($start,$length,barangay_template_tag_ranges($paragraph['text'])))throw new InvalidArgumentException('Existing Word tags are kept unchanged. Select ordinary text outside those tags.');
    if(barangay_template_overlap($start,$length,$ranges[$id]??[]))throw new InvalidArgumentException('Two replacements overlap. Keep only one replacement for the same text.');
    $ranges[$id][]=['start'=>$start,'length'=>$length];$byPart[$paragraph['part']][$paragraph['index']][]=['start'=>$start,'length'=>$length,'key'=>$key];$used[]=$key;
  }
  if(!$mappings && !$used)throw new InvalidArgumentException('Select at least one replacement, or add one manually, before preparing this template.');
  $custom=[];foreach(array_unique($used) as $key)if(empty($catalog[$key]['standard'])){$field=$catalog[$key];unset($field['standard']);$custom[]=$field;}
  $custom=barangay_custom_field_definitions($custom);
  $parts=barangay_template_read_package($source);
  if(!copy($source,$target))throw new RuntimeException('The prepared file could not be created. Please retry.');
  $zip=new ZipArchive();if($zip->open($target)!==true)throw new RuntimeException('The prepared Word file could not be opened.');
  try {
    foreach($byPart as $part=>$paragraphMappings) {
      [$doc,$xpath]=barangay_template_xml($parts[$part]);$paragraphs=$xpath->query('//w:p');
      foreach($paragraphMappings as $index=>$replacements) {
        [$text,$segments]=barangay_template_paragraph_segments($paragraphs->item($index),$xpath);
        usort($replacements,static fn($a,$b)=>$b['start']<=>$a['start']);
        foreach($replacements as $replacement) {
          $start=$replacement['start'];$end=$start+$replacement['length'];$inserted=false;
          foreach($segments as $segment) {
            $a=max($start,$segment['start']);$b=min($end,$segment['start']+$segment['length']);if($a>=$b)continue;
            if(!$segment['editable'])throw new InvalidArgumentException('This replacement crosses a tab or line break. Select a smaller piece of text.');
            $node=$segment['node'];$value=$node->nodeValue;
            $node->nodeValue=mb_substr($value,0,$a-$segment['start']).($inserted?'':'{{'.$replacement['key'].'}}').mb_substr($value,$b-$segment['start']);$inserted=true;
            if(preg_match('/^\s|\s$/u',$node->nodeValue))$node->setAttributeNS('http://www.w3.org/XML/1998/namespace','xml:space','preserve');
          }
        }
      }
      $zip->addFromString($part,$doc->saveXML());
    }
  } finally {$zip->close();}
  return ['fields'=>$custom,'replacements'=>count($mappings),'existing_tags'=>count($plan['existing_tags'])];
}
function barangay_template_work_dir(): string {
  $dir=rtrim(sys_get_temp_dir(),'/\\').DIRECTORY_SEPARATOR.'luna-template-preparation';
  if(!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir))throw new RuntimeException('Temporary template storage is unavailable.');return $dir;
}
function barangay_template_work_path(string $token,string $kind): string {
  if(!preg_match('/^[a-f0-9]{48}$/D',$token) || !in_array($kind,['source','prepared'],true))throw new InvalidArgumentException('Invalid template reference.');
  return barangay_template_work_dir().DIRECTORY_SEPARATOR.$token.'-'.$kind.'.docx';
}
function barangay_template_cleanup(): void {
  foreach(glob(barangay_template_work_dir().DIRECTORY_SEPARATOR.'*-*.docx')?:[] as $path)if(preg_match('/^[a-f0-9]{48}-(source|prepared)\.docx$/D',basename($path)) && filemtime($path)<time()-3600)@unlink($path);
  $_SESSION['template_preparation']=array_filter($_SESSION['template_preparation']??[],static fn($item)=>($item['expires']??0)>time());
}
