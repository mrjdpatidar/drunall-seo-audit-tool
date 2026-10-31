<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
function fail(string $m,int $s=400):never{http_response_code($s);echo json_encode(['error'=>$m]);exit;}
function input():array{$d=json_decode(file_get_contents('php://input'),true);return is_array($d)?$d:fail('Invalid JSON.');}
function ip():string{return $_SERVER['REMOTE_ADDR']??'unknown';}
function device():string{$d=$_SERVER['HTTP_X_DEVICE_KEY']??'';if(!preg_match('/^[a-f0-9-]{20,64}$/i',$d))fail('Invalid device key.');return $d;}
function rate_limit(string $scope):void{$limits=$scope==='crawl'?[120,200,120]:($scope==='pagespeed'?[30,40,30]:[10,20,10]);$dir=dirname(__DIR__).'/storage/rate-limit';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))fail('Rate limiter unavailable.',503);foreach(['ip:'.ip(),'device:'.device(),'combo:'.ip().':'.device()]as$n=>$v){$f=$dir.'/'.hash('sha256',$scope.'|'.$v).'.json';$h=fopen($f,'c+');if(!$h||!flock($h,LOCK_EX))fail('Rate limiter unavailable.',503);$d=json_decode(stream_get_contents($h)?:'{}',true)?:[];$now=time();$hits=array_values(array_filter($d['hits']??[],fn($x)=>$x>$now-3600));if(count($hits)>=$limits[$n]){flock($h,LOCK_UN);fclose($h);fail('Rate limit reached. Please try again later.',429);}$hits[]=$now;ftruncate($h,0);rewind($h);fwrite($h,json_encode(['hits'=>$hits]));flock($h,LOCK_UN);fclose($h);}}
function normalize_url_input(string $v):string{$v=trim($v);if($v==='')fail('Enter a website URL, such as drunall.com.');if(preg_match('#^//#',$v))$v='https:'.$v;elseif(!preg_match('#^https?://#i',$v))$v='https://'.ltrim($v,'/');return $v;}
function public_url(string $v):string{$v=normalize_url_input($v);if(!filter_var($v,FILTER_VALIDATE_URL))fail('Enter a valid public URL, such as https://example.com or example.com.');$p=parse_url($v);if(!in_array($p['scheme']??'',['http','https'],true))fail('Only HTTP and HTTPS URLs are supported.');$h=$p['host']??'';if($h==='localhost'||str_ends_with($h,'.local'))fail('Localhost URLs are blocked.');$ips=gethostbynamel($h)?:[];if(!$ips)fail('Host could not be resolved.');foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))fail('Private-network URLs are blocked.');return $v;}
function track_usage(string $url):void{
  $dir=dirname(__DIR__).'/storage';
  if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))return;
  $file=$dir.'/usage.json';
  $fh=fopen($file,'c+');
  if(!$fh||!flock($fh,LOCK_EX)){if($fh)fclose($fh);return;}
  $data=json_decode(stream_get_contents($fh)?:'{}',true);
  if(!is_array($data))$data=[];
  if(!isset($data['sites'])||!is_array($data['sites']))$data['sites']=[];
  $domain=preg_replace('/^www\./i','',strtolower((string)(parse_url($url,PHP_URL_HOST)??'')));
  if($domain===''){flock($fh,LOCK_UN);fclose($fh);return;}
  $now=gmdate('c');
  if(!isset($data['sites'][$domain])||!is_array($data['sites'][$domain])){
    $data['sites'][$domain]=['domain'=>$domain,'url'=>$url,'count'=>1,'firstUsedAt'=>$now,'lastUsedAt'=>$now];
  }else{
    $row=&$data['sites'][$domain];
    $row['domain']=$domain;
    $row['url']=$url;
    $row['count']=(int)($row['count']??0)+1;
    $row['lastUsedAt']=$now;
    if(empty($row['firstUsedAt']))$row['firstUsedAt']=$now;
    unset($row);
  }
  if(count($data['sites'])>5000){
    uasort($data['sites'],fn($a,$b)=>strcmp((string)($a['lastUsedAt']??''),(string)($b['lastUsedAt']??'')));
    $data['sites']=array_slice($data['sites'],-5000,null,true);
  }
  $data['totalAudits']=array_sum(array_map(fn($s)=>(int)($s['count']??0),$data['sites']));
  $data['updatedAt']=$now;
  ftruncate($fh,0);rewind($fh);
  fwrite($fh,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  flock($fh,LOCK_UN);fclose($fh);
}
function resolve_url(string $v,string $b):string{if(filter_var($v,FILTER_VALIDATE_URL))return $v;$p=parse_url($b);return $p['scheme'].'://'.$p['host'].(str_starts_with($v,'/')?$v:'/'.ltrim($v,'/'));}
function fetch_page(string $url):array{$current=public_url($url);for($n=0;$n<6;$n++){$start=microtime(true);$got=fetch_hop($current);if(!$got['ok'])fail('Could not reach this URL: '.$got['error'],502);if($got['status']>=300&&$got['status']<400&&$got['location']){$current=public_url(resolve_url($got['location'],$current));continue;}return['html'=>$got['html'],'status'=>$got['status'],'url'=>$got['url']?:$current,'time'=>(int)round((microtime(true)-$start)*1000)];}fail('Too many redirects.',502);}
function fetch_hop(string $url):array{$ua='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';$headers=[];$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADERFUNCTION=>function($c,$h)use(&$headers){$headers[]=$h;return strlen($h);},CURLOPT_USERAGENT=>$ua,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_PROXY=>'',CURLOPT_NOPROXY=>'*',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS]);$html=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$effective=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);$err=curl_error($ch);curl_close($ch);if($html!==false){$loc='';foreach($headers as $h)if(str_starts_with(strtolower($h),'location:'))$loc=trim(substr($h,9));return['ok'=>true,'html'=>$html,'status'=>$status,'location'=>$loc,'url'=>$effective,'error'=>''];}$bin='curl';if(stripos(PHP_OS,'WIN')===0){$bin='curl.exe';foreach(['C:\\Windows\\System32\\curl.exe','C:\\Windows\\Sysnative\\curl.exe'] as $c)if(is_file($c)){$bin=$c;break;}}if(!function_exists('shell_exec'))return['ok'=>false,'error'=>$err.' (shell_exec disabled)','html'=>'','status'=>0,'location'=>'','url'=>''];$hdr=tempnam(sys_get_temp_dir(),'sah');$body=tempnam(sys_get_temp_dir(),'sab');$log=tempnam(sys_get_temp_dir(),'sal');if($hdr===false||$body===false)return['ok'=>false,'error'=>$err,'html'=>'','status'=>0,'location'=>'','url'=>''];$cmd=escapeshellarg($bin).' -4 -sS --max-redirs 0 --max-time 15 --connect-timeout 8 -A '.escapeshellarg($ua).' -D '.escapeshellarg($hdr).' -o '.escapeshellarg($body).' -w %{http_code} '.escapeshellarg($url).' 2>'.escapeshellarg((string)$log);$code=shell_exec($cmd);$status=(int)trim((string)$code);$html=is_file($body)?file_get_contents($body):false;$raw=is_file($hdr)?(string)file_get_contents($hdr):'';$clog=is_file((string)$log)?trim((string)file_get_contents((string)$log)):'';@unlink($hdr);@unlink($body);if(is_string($log))@unlink($log);if($html===false||$status===0)return['ok'=>false,'error'=>$err.' | cli '.substr($bin.' '.trim((string)$code).' '.$clog,0,220),'html'=>'','status'=>0,'location'=>'','url'=>''];$loc='';foreach(preg_split("/\r\n|\n/",$raw) as $line)if(stripos($line,'location:')===0)$loc=trim(substr($line,9));return['ok'=>true,'html'=>$html,'status'=>$status,'location'=>$loc,'url'=>$url,'error'=>''];}
function tag_text(string $h,string $r):string{return preg_match($r,$h,$m)?trim(preg_replace('/\s+/',' ',strip_tags($m[1]))):'';}
function attrs(string $t):array{preg_match_all('/([\w:-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?/',$t,$rows,PREG_SET_ORDER);$o=[];foreach($rows as $r)$o[strtolower($r[1])]=$r[2]??$r[3]??$r[4]??'';return$o;}
function inspect(string $html,string $url,int $status,int $time):array{
  $plain=preg_replace('/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/i',' ',$html);
  preg_match_all('/<img\b[^>]*>/i',$html,$images);preg_match_all('/<script\b[^>]*src\s*=\s*["\']([^"\']+)["\']/i',$html,$scripts);
  preg_match_all('/<a\b[^>]*href\s*=\s*["\']([^"\'#]+)["\']/i',$html,$links);preg_match_all('/<h1\b[^>]*>([\s\S]*?)<\/h1>/i',$plain,$h1);
  $domain=parse_url($url,PHP_URL_HOST);$external=[];foreach($links[1]as$l){$a=resolve_url($l,$url);$scheme=strtolower((string)(parse_url($a,PHP_URL_SCHEME)??''));if(in_array($scheme,['mailto','tel','sms','javascript'],true))continue;$host=parse_url($a,PHP_URL_HOST);if($host&&$host!==$domain)$external[]=$a;}
  $robots=strtolower(tag_text($html,'/<meta\b[^>]*name=["\']robots["\'][^>]*content=["\']([^"\']*)/i'));
  $canonical=tag_text($html,'/<link\b[^>]*rel=["\'][^"\']*canonical[^"\']*["\'][^>]*href=["\']([^"\']*)/i');
  if(!$canonical)$canonical=tag_text($html,'/<link\b[^>]*href=["\']([^"\']*)["\'][^>]*rel=["\'][^"\']*canonical/i');
  $keywords=array_values(array_filter(array_map('trim',explode(',',tag_text($html,'/<meta\b[^>]*name=["\']keywords["\'][^>]*content=["\']([^"\']*)/i')))));
  return ['status'=>$status,'time'=>$time,'images'=>array_map('attrs',$images[0]),'scripts'=>$scripts[1],'h1'=>array_map(fn($x)=>trim(strip_tags($x)),$h1[1]),'external'=>array_values(array_unique($external)),'seo'=>['page'=>$url,'title'=>tag_text($html,'/<title[^>]*>([\s\S]*?)<\/title>/i'),'description'=>tag_text($html,'/<meta\b[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)/i'),'canonical'=>$canonical,'keywords'=>$keywords,'noindex'=>str_contains($robots,'noindex')]];
}
function technologies(string $html):array{
  $checks=[
    'Shopify'=>['cdn.shopify.com','shopify-section','Shopify.theme'],
    'WordPress'=>['/wp-content/','/wp-includes/','wp-json','wp-emoji-release'],
    'WooCommerce'=>['/plugins/woocommerce','woocommerce.min.js','woocommerce.js'],
    'Drupal'=>['drupalSettings','sites/default/files'],
    'Google Tag Manager'=>['googletagmanager.com/gtm','googletagmanager.com/gtag'],
    'Google Analytics'=>['google-analytics.com/analytics','gtag/js'],
    'Meta Pixel'=>['connect.facebook.net','fbq('],
    'Hotjar'=>['static.hotjar.com','hj('],
    'Cloudflare'=>['cdn-cgi/','cloudflareinsights','challenges.cloudflare.com'],
    'jQuery'=>['jquery.min.js','jquery.js'],
    'React'=>['react-dom','react.production','react.development','_reactRootContainer','data-reactroot','__NEXT_DATA__'],
    'Vite'=>['/@vite/','vite/client'],
    'Vue.js'=>['vue.global','__vue__','vue.runtime'],
    'Next.js'=>['/_next/','__NEXT_DATA__'],
    'Bootstrap'=>['bootstrap.min.css','bootstrap.min.js'],
    'Intercom'=>['widget.intercom.io','intercomSettings'],
    'HubSpot'=>['js.hs-scripts.com','hubspot']
  ];
  $found=[];$lower=strtolower($html);
  foreach($checks as $name=>$signals){foreach($signals as $signal)if(str_contains($lower,strtolower($signal))){$found[]=$name;break;}}
  if(preg_match('/type=["\']module["\'][^>]*src=["\'][^"\']*\/assets\/[^"\']+\.js/i',$html)||preg_match('/\/assets\/index-[a-z0-9_-]+\.js/i',$html)){
    if(!in_array('Vite',$found,true))$found[]='Vite';
    if(!in_array('React',$found,true)&&!in_array('Vue.js',$found,true))$found[]='React';
  }
  return array_values(array_unique($found));
}
function detect_platform(string $html,string $url):array{
  $tech=technologies($html);
  if(in_array('Shopify',$tech,true))return ['name'=>'Shopify','detail'=>preg_match('/preview_theme_id=([^&]+)/',$url,$m)?'Preview theme '.$m[1]:'Detected from Shopify assets'];
  if(in_array('WooCommerce',$tech,true))return ['name'=>'WordPress + WooCommerce','detail'=>'Detected from WooCommerce / WordPress paths'];
  if(in_array('WordPress',$tech,true))return ['name'=>'WordPress','detail'=>'Detected from wp-content / wp-includes signatures'];
  if(in_array('Next.js',$tech,true))return ['name'=>'Next.js (React)','detail'=>'Detected from Next.js assets'];
  if(in_array('Drupal',$tech,true))return ['name'=>'Drupal','detail'=>'Detected from Drupal signatures'];
  if(in_array('Vue.js',$tech,true))return ['name'=>'Vue.js','detail'=>'Detected from Vue runtime signatures'];
  if(in_array('React',$tech,true)||in_array('Vite',$tech,true))return ['name'=>in_array('Vite',$tech,true)?'React + Vite':'React','detail'=>'Detected from JS bundle / framework signatures'];
  if(preg_match('/type=["\']module["\'][^>]*src=["\'][^"\']+\/assets\//i',$html))return ['name'=>'Modern SPA','detail'=>'ES module asset bundles detected'];
  return ['name'=>'Custom stack','detail'=>'No major CMS signature found in HTML'];
}
function format_bytes(int $n):string{if($n<=0)return 'Size unknown';if($n<1024)return $n.' B';if($n<1048576)return round($n/1024,1).' KB';return round($n/1048576,2).' MB';}
function probe_resource(string $url):array{
  $start=microtime(true);$headers=[];$ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_NOBODY=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>8,CURLOPT_USERAGENT=>'DrunallAudit/1.0',CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4]);
  $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$len=(int)curl_getinfo($ch,CURLINFO_CONTENT_LENGTH_DOWNLOAD);curl_close($ch);
  if($len<=0&&is_string($raw)&&preg_match('/content-length:\s*(\d+)/i',$raw,$m))$len=(int)$m[1];
  if(($code===0||$code>=400||$len<=0)){
    $start=microtime(true);$ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_USERAGENT=>'DrunallAudit/1.0',CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_RANGE=>'0-0']);
    curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$cl=(int)curl_getinfo($ch,CURLINFO_CONTENT_LENGTH_DOWNLOAD);curl_close($ch);
    if($cl>0)$len=$cl;
  }
  $ms=(int)max(1,round((microtime(true)-$start)*1000));
  return ['ms'=>$ms,'bytes'=>max(0,$len),'status'=>$code];
}
function is_sitemap_loc(string $u):bool{
  $path=strtolower((string)(parse_url($u,PHP_URL_PATH)??''));
  return str_ends_with($path,'.xml')||(bool)preg_match('/sitemap/i',$path);
}
function same_site_host(string $a,string $b):bool{
  $ha=preg_replace('/^www\./i','',strtolower($a));
  $hb=preg_replace('/^www\./i','',strtolower($b));
  if($ha===''||$hb==='')return false;
  if($ha===$hb)return true;
  $root=function(string $h):string{$p=explode('.',$h);return count($p)<=2?$h:implode('.',array_slice($p,-2));};
  return $root($ha)===$root($hb);
}
function collect_sitemap_pages(string $startUrl,string $siteHost,int $limit=50):array{
  $pages=[];$seen=[];$queue=[[$startUrl,0]];
  while($queue&&count($pages)<$limit){
    [$url,$depth]=array_shift($queue);
    $key=strtolower($url);
    if(isset($seen[$key])||$depth>2)continue;
    $seen[$key]=true;
    try{$xml=fetch_page($url);}catch(Throwable $e){continue;}
    if(($xml['status']??500)>=400||!preg_match_all('/<loc>\s*([^<\s]+)\s*<\/loc>/i',(string)$xml['html'],$loc))continue;
    $locs=array_values(array_unique($loc[1]));
    $body=strtolower((string)$xml['html']);
    $childMaps=0;foreach($locs as $u)if(is_sitemap_loc($u))$childMaps++;
    $isIndex=str_contains($body,'<sitemapindex')||str_contains($body,'<sitemap>')||($childMaps>=max(1,(int)ceil(count($locs)*0.5)));
    foreach($locs as $u){
      $host=(string)(parse_url($u,PHP_URL_HOST)??'');
      if(!same_site_host($host,$siteHost))continue;
      if($isIndex||is_sitemap_loc($u)){
        if($depth<2)$queue[]=[$u,$depth+1];
        continue;
      }
      $pages[]=$u;
      if(count($pages)>=$limit)break;
    }
  }
  return array_slice(array_values(array_unique($pages)),0,$limit);
}
function enrich_scripts(array $urls,string $base):array{
  $out=[];foreach(array_slice($urls,0,8) as $src){
    $abs=resolve_url($src,$base);$probe=probe_resource($abs);$name=basename(parse_url($abs,PHP_URL_PATH)?:$abs)?:$abs;
    $out[]=['name'=>$name,'url'=>$abs,'ms'=>$probe['ms'],'bytes'=>$probe['bytes'],'value'=>$probe['ms'].' ms','width'=>min(100,max(8,(int)round($probe['ms']/40))),'detail'=>format_bytes($probe['bytes']).' · HTTP '.$probe['status']];
  }
  usort($out,fn($a,$b)=>($b['ms']??0)<=>($a['ms']??0));
  if($out){$max=max(array_column($out,'ms')?:[1]);foreach($out as &$row)$row['width']=min(100,max(8,(int)round(($row['ms']/$max)*100)));unset($row);}
  return $out;
}
