<?php
require __DIR__.'/bootstrap.php';
$data=input();
$url=public_url((string)($data['url']??''));
$strategy=strtolower((string)($data['strategy']??'mobile'));
if(!in_array($strategy,['mobile','desktop'],true))$strategy='mobile';

$dir=dirname(__DIR__).'/storage/pagespeed';
if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))fail('Cache unavailable.',503);
$cacheFile=$dir.'/'.hash('sha256',$strategy.'|'.$url).'.json';
if(is_file($cacheFile)){
  $cached=json_decode((string)file_get_contents($cacheFile),true);
  if(is_array($cached)&&(($cached['cachedAt']??0)>time()-21600)){
    echo json_encode($cached,JSON_UNESCAPED_SLASHES);
    exit;
  }
}

rate_limit('pagespeed');

function psi_pack(array $json):array{
  $audits=$json['lighthouseResult']['audits']??[];
  $pick=function(string $id)use($audits):array{
    $a=$audits[$id]??[];
    $score=isset($a['score'])&&is_numeric($a['score'])?(float)$a['score']:null;
    $rating=$score===null?'unknown':($score>=0.9?'good':($score>=0.5?'average':'poor'));
    return [
      'id'=>$id,
      'title'=>(string)($a['title']??$id),
      'displayValue'=>(string)($a['displayValue']??'—'),
      'numericValue'=>isset($a['numericValue'])?(float)$a['numericValue']:null,
      'score'=>$score,
      'rating'=>$rating
    ];
  };
  $perfScore=$json['lighthouseResult']['categories']['performance']['score']??null;
  return [
    'performanceScore'=>$perfScore===null?null:(int)round(((float)$perfScore)*100),
    'metrics'=>[
      $pick('first-contentful-paint')+['short'=>'FCP','label'=>'First Contentful Paint'],
      $pick('largest-contentful-paint')+['short'=>'LCP','label'=>'Largest Contentful Paint'],
      $pick('total-blocking-time')+['short'=>'TBT','label'=>'Total Blocking Time'],
      $pick('cumulative-layout-shift')+['short'=>'CLS','label'=>'Cumulative Layout Shift'],
      $pick('speed-index')+['short'=>'SI','label'=>'Speed Index']
    ]
  ];
}

function psi_http(string $endpoint,int $timeout=90):array{
  $ch=curl_init($endpoint);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CONNECTTIMEOUT=>20,
    CURLOPT_TIMEOUT=>$timeout,
    CURLOPT_FOLLOWLOCATION=>true,
    CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,
    CURLOPT_HTTPHEADER=>['Accept: application/json,text/html'],
    CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
  ]);
  $raw=curl_exec($ch);
  $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  return ['raw'=>$raw,'status'=>$status,'error'=>$err];
}

function psi_from_api(string $url,string $strategy):?array{
  $key=getenv('PAGESPEED_API_KEY')?:'';
  $endpoint='https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url='.rawurlencode($url).'&strategy='.$strategy.'&category=performance';
  if($key!=='')$endpoint.='&key='.rawurlencode($key);
  $got=psi_http($endpoint);
  if($got['raw']===false||$got['status']>=400)return null;
  $json=json_decode((string)$got['raw'],true);
  if(!is_array($json)||empty($json['lighthouseResult']['audits']))return null;
  if(isset($json['error']))return null;
  return psi_pack($json);
}

function psi_from_webdev(string $url,string $strategy):?array{
  $factor=$strategy==='desktop'?'desktop':'mobile';
  $endpoint='https://pagespeed.web.dev/analysis?url='.rawurlencode($url).'&form_factor='.$factor;
  $got=psi_http($endpoint,60);
  if($got['raw']===false||$got['status']>=400)return null;
  $html=(string)$got['raw'];
  // Same Google lab payload sometimes appears embedded after client render; also try API mirror path used by the UI.
  $api='https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url='.rawurlencode($url).'&strategy='.$strategy.'&category=performance&prettyPrint=false';
  $got2=psi_http($api,90);
  if($got2['raw']!==false&&$got2['status']<400){
    $json=json_decode((string)$got2['raw'],true);
    if(is_array($json)&&!empty($json['lighthouseResult']['audits'])&&!isset($json['error']))return psi_pack($json);
  }
  if(preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s',$html,$m)){
    $next=json_decode($m[1],true);
    $blob=json_encode($next);
    if(is_string($blob)&&str_contains($blob,'first-contentful-paint')&&preg_match('/"lighthouseResult"\s*:\s*\{/',$blob)){
      $wrap=json_decode('{"lighthouseResult":'.(preg_match('/"lighthouseResult"\s*:\s*(\{.*?\})\s*,\s*"loadingExperience"/s',$blob,$lr)?$lr[1]:'null').'}',true);
      // fallback: search audits directly
    }
  }
  if(preg_match('/"first-contentful-paint"\s*:\s*\{.*?\}/s',$html)&&preg_match('/"largest-contentful-paint"\s*:\s*\{.*?\}/s',$html)){
    // Build minimal audits from display-like values if present in raw HTML JSON islands
    if(preg_match('/"audits"\s*:\s*(\{.+?\})\s*,\s*"categories"/s',$html,$am)){
      $audits=json_decode($am[1],true);
      if(is_array($audits)){
        $fake=['lighthouseResult'=>['audits'=>$audits,'categories'=>['performance'=>['score'=>null]]]];
        if(preg_match('/"performance"\s*:\s*\{[^}]*"score"\s*:\s*([0-9.]+)/',$html,$ps))$fake['lighthouseResult']['categories']['performance']['score']=(float)$ps[1];
        return psi_pack($fake);
      }
    }
  }
  return null;
}

$pack=psi_from_api($url,$strategy);
if(!$pack)$pack=psi_from_webdev($url,$strategy);
if(!$pack)fail('Too many requests. Try again later.',429);

$out=$pack+['url'=>$url,'strategy'=>$strategy,'cachedAt'=>time()];
file_put_contents($cacheFile,json_encode($out,JSON_UNESCAPED_SLASHES));
echo json_encode($out,JSON_UNESCAPED_SLASHES);
