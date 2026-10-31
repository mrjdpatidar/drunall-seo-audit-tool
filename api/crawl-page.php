<?php
require __DIR__.'/bootstrap.php';
rate_limit('crawl');
$data=input();
$target=public_url((string)($data['url']??''));
$domain=preg_replace('/^www\./i','',strtolower((string)($data['domain']??'')));
$host=preg_replace('/^www\./i','',strtolower((string)(parse_url($target,PHP_URL_HOST)??'')));
$root=function(string $h):string{$p=explode('.',$h);return count($p)<=2?$h:implode('.',array_slice($p,-2));};
$related=$host!==''&&$domain!==''&&($host===$domain||$root($host)===$root($domain));
if(!$related)fail('This sitemap URL is outside the audited domain.');
$path=strtolower((string)(parse_url($target,PHP_URL_PATH)??''));
if(str_ends_with($path,'.xml')||preg_match('/sitemap/i',$path))fail('Nested sitemap files are expanded during audit; open a page URL instead.');
$page=fetch_page($target);
$p=inspect($page['html'],$page['url'],$page['status'],$page['time']);
echo json_encode([
  'status'=>$page['status'],
  'title'=>$p['seo']['title'],
  'speed'=>$page['time'].' ms',
  'seo'=>$p['seo'],
  'alt'=>array_map(fn($x)=>['page'=>$page['url'],'detail'=>$x['src']??'Image without source'],array_filter($p['images'],fn($x)=>!array_key_exists('alt',$x))),
  'h1'=>count($p['h1'])>1?['page'=>$page['url'],'detail'=>count($p['h1']).' H1 headings']:null,
  'external'=>array_map(fn($x)=>['url'=>$x,'detail'=>'Found on '.$page['url']],$p['external'])
]);
