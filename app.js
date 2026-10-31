const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)],key='signal-audit-reports',deviceKey='signal-audit-device';let report,device='mobile',filter='all';
const safe=s=>{const d=document.createElement('div');d.textContent=s||'';return d.innerHTML};const tone=n=>n<50?'#e75d4c':n<90?'#d99424':'#0096fa';
function icons(){window.lucide?.createIcons()}
function note(s){const e=$('#toast');if(!e)return;e.textContent=s;e.classList.add('show');setTimeout(()=>e.classList.remove('show'),2600)}
function hist(){try{return JSON.parse(localStorage.getItem(key))||[]}catch{return[]}}
function save(){const list=hist().filter(x=>x.url!==report.url);list.unshift(report);localStorage.setItem(key,JSON.stringify(list.slice(0,10)))}
function getDeviceKey(){let id=localStorage.getItem(deviceKey);if(!id){id=crypto.randomUUID();localStorage.setItem(deviceKey,id)}return id}
async function api(path,data){const r=await fetch(path,{method:'POST',headers:{'content-type':'application/json','X-Device-Key':getDeviceKey()},body:JSON.stringify(data)}),d=await r.json();if(!r.ok)throw Error(d.error||'Audit failed');return d}

function normalizeUrl(v){
  v=(v||'').trim();
  if(!v)return v;
  if(/^\/\//.test(v))v='https:'+v;
  else if(!/^https?:\/\//i.test(v))v='https://'+v.replace(/^\/+/,'');
  return v;
}
function hostKey(v){try{return new URL(normalizeUrl(v)).hostname.replace(/^www\./i,'').toLowerCase()}catch{return ''}}
function sameSite(a,b){const ha=hostKey(a),hb=hostKey(b);if(!ha||!hb)return false;const root=h=>{const p=h.split('.');return p.length<=2?h:p.slice(-2).join('.')};return ha===hb||root(ha)===root(hb)}
function auditParam(){
  const q=new URLSearchParams(location.search).get('audit');
  if(q)return normalizeUrl(q);
  const m=location.pathname.match(/\/audit=(.+)$/i);
  return m?normalizeUrl(decodeURIComponent(m[1])): '';
}
function syncAuditUrl(url){
  const next=new URL(location.href);
  if(url){next.searchParams.set('audit',normalizeUrl(url));next.hash=''}
  else next.searchParams.delete('audit');
  history.replaceState({},'',next.pathname+next.search+next.hash);
}
function findCached(url){
  const n=normalizeUrl(url),h=hostKey(n);
  const list=hist();
  return list.find(x=>normalizeUrl(x.url)===n)||list.find(x=>hostKey(x.url)===h)||null;
}
function showHero(on){const el=$('#start');if(el)el.hidden=!on}
function busy(on){
  $('#skeleton').hidden=!on;
  $('#runButton').disabled=on;
  $('#runButton span').textContent=on?'Auditing...':'Run SEO audit';
  if(on){showHero(false);$('#report').hidden=true}
  else if(!report)$('#report').hidden=true;
}
function showCachedReport(cached,{fetchLab=false}={}){
  report=cached;report.lab=report.lab||{};report.crawling=false;
  if(report.sitemap&&!Array.isArray(report.sitemap.results))report.sitemap.results=report.sitemap.results||[];
  showHero(false);syncAuditUrl(report.url);render();openPanel('overview');
  if(fetchLab)loadLabMetrics(device);
  else metrics();
}

$('#auditForm').onsubmit=async e=>{
  e.preventDefault();
  const input=$('#siteUrl');
  const url=normalizeUrl(input.value);
  input.value=url;
  const cached=findCached(url);
  if(cached){showCachedReport(cached,{fetchLab:!cached.lab?.[device]?.metrics});note('Loaded saved report for '+cached.domain);return}
  busy(true);syncAuditUrl(url);
  try{
    report=await api('api/audit.php',{url});
    report.id=crypto.randomUUID();report.createdAt=new Date().toISOString();report.sitemap.crawled=0;report.sitemap.results=[];report.crawling=false;report.lab={};
    try{const tech=await api('api/technology.php',{url:report.url});report.technologies=tech.technologies||[]}catch{report.technologies=[]}
    save();syncAuditUrl(report.url);render();openPanel('overview');loadLabMetrics(device);
  }catch(err){report=null;busy(false);showHero(true);syncAuditUrl('');note(err.message)}
};

function openPanel(id){
  $$('#reportAccordion .accordion-item').forEach(item=>{
    const on=item.dataset.panel===id;
    item.open=on;
  });
  if(id==='pages')crawl();
  icons();
}

function setSignal(name,state,icon,value,detail){
  const card=$(`[data-signal="${name}"]`);if(!card)return;
  card.className=`signal ${state}`;
  const ic=card.querySelector('.signal-icon');
  if(ic){ic.setAttribute('data-lucide',icon);ic.innerHTML=''}
  const b=card.querySelector('b'),s=card.querySelector('small');
  if(b)b.textContent=value;if(s)s.textContent=detail;
}
function render(){
  const t=report.tags||{};
  const set=(sel,val)=>{const el=$(sel);if(el)el.textContent=val};
  set('#domain',report.domain);
  set('#meta',`Audited ${new Date(report.createdAt).toLocaleString()} | ${report.url}`);
  const known=report.platform.name&&!/unknown/i.test(report.platform.name);
  setSignal('platform',known?'ok':'warn',known?'check-circle-2':'help-circle',report.platform.name,report.platform.detail);
  setSignal('ssl',report.ssl.secure?'ok':'bad',report.ssl.secure?'shield-check':'shield-alert',report.ssl.secure?'HTTPS active':'HTTP only',report.ssl.detail);
  setSignal('gtag',t.gtag?'ok':'warn',t.gtag?'check-circle-2':'circle-x',t.gtag?'Detected':'Not found',t.gtag?'Google tag signal in HTML':'No GTM or gtag script found');
  setSignal('cookie',t.cookie?'ok':'bad',t.cookie?'check-circle-2':'circle-x',t.cookie?'Detected':'Not found',t.cookie?'Consent marker in HTML':'No common consent marker found');
  const warn=$('#protocolWarning');if(warn)warn.hidden=report.ssl.secure;
  set('#issueCount',String(report.issues.length));
  const grid=$('#scoreGrid');
  if(grid)grid.innerHTML=report.scores.map(s=>`<article class="score-card"><span class="score-label">${s.label}</span><div class="score-row"><div class="score-ring" style="--score:${s.score};--score-color:${tone(s.score)}"><b>${s.score}</b></div><span class="score-desc">${s.description}</span></div></article>`).join('');
  metrics();issues();assets();sitemap();
  list('alt','Missing alt text');list('h1','Multiple H1 headings');list('external','External links');
  seo();consoleRows();renderTechnologies();renderFirstImpression();renderLoadShots();
  busy(false);showHero(false);$('#report').hidden=false;icons();
}

function ratingIcon(r){
  if(r==='good')return '<span class="status-badge good" title="Good"><i data-lucide="check"></i></span>';
  if(r==='average')return '<span class="status-badge average" title="Needs improvement"><i data-lucide="minus"></i></span>';
  if(r==='poor')return '<span class="status-badge poor" title="Poor"><i data-lucide="x"></i></span>';
  return '<span class="status-badge unknown" title="Unavailable"><i data-lucide="help-circle"></i></span>';
}
function metrics(){
  const el=$('#metrics'),legend=$('#psiLegend');if(!el)return;
  $$('.device').forEach(x=>x.classList.toggle('active',x.dataset.device===device));
  const lab=report.lab?.[device];
  if(lab?.loading){
    if(legend)legend.hidden=true;
    el.innerHTML='<div class="psi-loading">Fetching PageSpeed lab metrics (FCP, LCP, TBT, CLS, Speed Index)…</div>';
    return;
  }
  if(lab?.error){
    if(legend)legend.hidden=false;
    const placeholders=[
      {label:'First Contentful Paint',short:'FCP'},
      {label:'Largest Contentful Paint',short:'LCP'},
      {label:'Total Blocking Time',short:'TBT'},
      {label:'Cumulative Layout Shift',short:'CLS'},
      {label:'Speed Index',short:'SI'}
    ];
    el.innerHTML=`<div class="psi-error"><p>Too many requests. Try again later.</p><button type="button" class="outline-button" id="retryLab">Retry metrics</button></div><div class="psi-grid">${placeholders.map(m=>`<article class="psi-metric unknown"><div class="psi-top">${ratingIcon('unknown')}<span>${m.label} <em>(${m.short})</em></span></div><strong>—</strong></article>`).join('')}</div>`;
    icons();
    return;
  }
  if(lab?.metrics?.length){
    if(legend)legend.hidden=false;
    const score=lab.performanceScore!=null?`<div class="psi-score">Performance <b class="${lab.performanceScore>=90?'good':lab.performanceScore>=50?'average':'poor'}">${lab.performanceScore}</b></div>`:'';
    el.innerHTML=score+`<div class="psi-grid">${lab.metrics.map(m=>`<article class="psi-metric ${m.rating}"><div class="psi-top">${ratingIcon(m.rating)}<span>${safe(m.label)} <em>(${safe(m.short)})</em></span></div><strong>${safe(m.displayValue)}</strong></article>`).join('')}</div>`;
    icons();
    return;
  }
  if(legend)legend.hidden=true;
  const m=report.performance?.[device]||[];
  el.innerHTML=m.map(x=>`<div class="metric"><strong>${safe(x.value)}</strong><span>${x.name}</span><small>${safe(x.status)}</small></div>`).join('')||'<p class="empty">No speed metrics yet.</p>';
}
async function loadLabMetrics(strategy,{force=false}={}){
  if(!report)return;
  report.lab=report.lab||{};
  if(!force&&(report.lab[strategy]?.metrics||report.lab[strategy]?.loading)){metrics();return}
  report.lab[strategy]={loading:true};metrics();
  try{
    const data=await api('api/pagespeed.php',{url:report.url,strategy});
    report.lab[strategy]={metrics:data.metrics,performanceScore:data.performanceScore,loading:false};
    save();
  }catch(err){
    report.lab[strategy]={error:err.message,loading:false};save();
  }
  if(device===strategy)metrics();
}
function issues(){
  const a=filter==='all'?report.issues:report.issues.filter(x=>x.severity===filter),el=$('#issueList');if(!el)return;
  el.innerHTML=a.map(x=>`<article class="issue"><i class="severity ${x.severity}"></i><div><h4>${safe(x.title)}</h4><p>${safe(x.text)}</p><p class="fix"><b>How to fix:</b> ${safe(x.fix)}</p></div><span class="issue-badge">${x.severity}</span></article>`).join('')||'<p class="empty">No matching issues.</p>';
}
function assets(){
  const row=x=>`<div class="asset"><div class="asset-top"><span class="asset-name">${safe(x.name)}</span><span class="asset-time">${safe(x.value)}</span></div><div class="resource-bar"><i style="width:${x.width}%"></i></div><small>${safe(x.detail)}</small></div>`;
  const s=$('#scriptList'),a=$('#assetList');
  if(s)s.innerHTML=report.scripts.map(row).join('')||'<p class="empty">No scripts found.</p>';
  if(a)a.innerHTML=report.assets.map(row).join('')||'<p class="empty">No media found.</p>';
}
function sitemap(){
  const s=report.sitemap||{url:null,pages:[],crawled:0};
  const urlEl=$('#sitemapUrl'),statsEl=$('#sitemapStats');
  if(urlEl)urlEl.textContent=s.url||'No sitemap.xml found';
  if(statsEl)statsEl.innerHTML=`<div class="sitemap-stat"><b>${s.pages.length}</b><span>URLs found</span></div><div class="sitemap-stat"><b>${s.crawled||0}</b><span>Reviewed</span></div><div class="sitemap-stat"><b>${Math.max(0,s.pages.length-(s.crawled||0))}</b><span>Queued</span></div>`;
}
function list(k,title){
  let a=report.findings[k]||[];
  if(k==='external')a=a.filter(x=>!/^(mailto|tel|sms|javascript):/i.test(String(x.url||x.page||'')));
  const id=k==='alt'?'altList':k==='h1'?'h1List':'externalList';
  const el=$('#'+id);if(!el)return;
  el.innerHTML=`<div class="finding-intro"><b>${title}</b><span>${a.length} found</span></div>${a.length?a.map(x=>`<article class="finding"><b>${safe(x.page||x.url)}</b><p>${safe(x.detail)}</p></article>`).join(''):'<p class="empty">Nothing found yet.</p>'}`;
}
function seo(){
  const a=report.findings.seo||[],el=$('#seoList');if(!el)return;
  el.innerHTML=`<div class="finding-intro"><b>Page SEO metadata</b><span>${a.length} reviewed</span></div>${a.map(x=>`<article class="seo-card"><div><b>${safe(x.page)}</b><span class="${x.noindex?'seo-noindex':'seo-index'}">${x.noindex?'NOINDEX':'INDEXABLE'}</span></div><p><strong>Title:</strong> ${safe(x.title||'Missing')}</p><p><strong>Description:</strong> ${safe(x.description||'Missing')}</p><p><strong>Canonical:</strong> ${safe(x.canonical||'Missing')}</p><div class="chips">${x.keywords?.length?x.keywords.map(k=>`<span>${safe(k)}</span>`).join(''):'<em>No meta keywords</em>'}</div></article>`).join('')}`;
}
function consoleRows(){
  const el=$('#consoleList');if(!el)return;
  el.innerHTML=(report.console||[]).map(x=>`<div class="console-row"><span class="console-level ${x.level}">${x.level}</span><span>${safe(x.message)}</span></div>`).join('')||'<p class="empty">All checked resources responded cleanly — no fetch failures on this page.</p>';
}
function renderTechnologies(){
  const items=report.technologies||[],el=$('#technologyList');if(!el)return;
  el.innerHTML=`<div class="finding-intro"><b>Detected technologies</b><span>${items.length} found</span></div><p class="finding-description">Signals are detected from the page HTML and loaded resources.</p><div class="tech-grid">${items.length?items.map(x=>`<span>${safe(x)}</span>`).join(''):'<p class="empty">No common technology signatures were detected.</p>'}</div>`;
}
function scoreOf(label){return report.scores.find(x=>x.label===label)?.score||0}
function renderFirstImpression(){
  const el=$('#firstImpression');if(!el)return;
  const seo=scoreOf('Technical SEO'),access=scoreOf('Accessibility'),security=scoreOf('Security'),index=scoreOf('Indexing');
  const shot=shotUrl(report.url);
  const fallback=`https://s0.wp.com/mshots/v1/${encodeURIComponent(report.url)}?w=640`;
  el.innerHTML=`<div class="impression-score" style="--score:${seo}"><div><b>${seo}</b><span>SEO score</span></div></div><div class="impression-bars"><div><b>Technical SEO</b><span>${seo}%</span><i><em style="width:${seo}%;background:#e75d4c"></em></i></div><div><b>Security / HTTPS</b><span>${security}%</span><i><em style="width:${security}%;background:#d99424"></em></i></div><div><b>Accessibility</b><span>${access}%</span><i><em style="width:${access}%;background:#0096fa"></em></i></div><div><b>Indexing signals</b><span>${index}%</span><i><em style="width:${index}%;background:#367baf"></em></i></div></div><div class="page-preview has-shot"><div class="preview-browser"><span>${safe(report.domain)}</span><i></i><i></i><i></i></div><img class="preview-shot" alt="Full loaded screenshot of ${safe(report.domain)}" src="${safe(shot)}" data-fallback="${safe(fallback)}" loading="lazy" onerror="if(this.dataset.fallback&&this.src!==this.dataset.fallback){this.src=this.dataset.fallback}else{this.classList.add('is-missing')}"></div>`;
}

function shotUrl(url){
  return `https://v1.screenshot.11ty.dev/${encodeURIComponent(url)}/large/`;
}
function renderLoadShots(){
  const el=$('#loadShots');if(!el||!report?.url)return;
  const stages=[
    {label:'First paint',stage:'paint-0',hint:'Early shell / blank start'},
    {label:'Content paint',stage:'paint-1',hint:'Main content appearing'},
    {label:'Near interactive',stage:'paint-2',hint:'Most of the page painted'},
    {label:'Full load',stage:'paint-3',hint:'Complete website screenshot'}
  ];
  const src=shotUrl(report.url);
  const fallback=`https://s0.wp.com/mshots/v1/${encodeURIComponent(report.url)}?w=480`;
  el.innerHTML=stages.map((s,i)=>`<figure class="load-shot ${s.stage}"><div class="shot-frame"><img alt="${safe(s.label)} screenshot of ${safe(report.domain)}" loading="${i?'lazy':'eager'}" src="${safe(src)}" data-fallback="${safe(fallback)}" onerror="if(this.dataset.fallback&&this.src!==this.dataset.fallback){this.src=this.dataset.fallback}else{this.replaceWith(Object.assign(document.createElement('div'),{className:'shot-fallback',innerHTML:'<span>${i+1}</span><small>Preview unavailable</small>'}))}"></div><figcaption><b>${s.label}</b><span>${s.hint}</span></figcaption></figure>`).join('');
}

function renderCrawlRows(rows){
  const box=$('#pageRows');if(!box)return;
  if(!rows.length){box.innerHTML='<p class="empty">No same-domain sitemap URLs to review.</p>';return}
  box.innerHTML=rows.map(r=>{
    const state=r.state||'queued',label=r.label||(state==='queued'?'Queued':state);
    return `<div class="page-row" data-url="${safe(r.url)}"><span class="crawl-state ${state}">${safe(label)}</span><span class="page-url">${safe(r.url)}</span><span class="page-result">${safe(r.detail||'Waiting')}</span></div>`;
  }).join('');
}
async function crawl(){
  if(!report?.sitemap){const box=$('#pageRows');if(box)box.innerHTML='<p class="empty">No sitemap URLs to review.</p>';return}
  report.sitemap.pages=(report.sitemap.pages||[]).filter(u=>sameSite(u,report.url));
  report.sitemap.results=report.sitemap.results||[];
  if(!report.sitemap.pages.length){renderCrawlRows([]);sitemap();return}
  if(report.sitemap.results.length>=report.sitemap.pages.length){
    renderCrawlRows(report.sitemap.results);sitemap();return;
  }
  if(report.crawling)return;
  report.crawling=true;
  const done=new Map(report.sitemap.results.map(r=>[r.url,r]));
  renderCrawlRows(report.sitemap.pages.map(u=>done.get(u)||{url:u,state:'queued',label:'Queued',detail:'Waiting'}));
  for(const url of report.sitemap.pages){
    if(done.has(url))continue;
    const row=[...($('#pageRows')?.children||[])].find(x=>x.dataset.url===url);
    const state=row?.querySelector('.crawl-state'),result=row?.querySelector('.page-result');
    if(state){state.textContent='Checking';state.className='crawl-state checking'}
    let entry;
    try{
      const p=await api('api/crawl-page.php',{url,domain:report.domain});
      entry={url,state:p.status>=400?'failed':'done',label:String(p.status),detail:`${p.title||'Untitled'} | ${p.seo.noindex?'NOINDEX | ':''}${p.speed}`};
      if(state){state.textContent=entry.label;state.className=`crawl-state ${entry.state}`}
      if(result)result.textContent=entry.detail;
      report.findings.seo.push(p.seo);report.findings.alt.push(...p.alt);if(p.h1)report.findings.h1.push(p.h1);report.findings.external.push(...p.external);
    }catch(err){
      entry={url,state:'failed',label:'Failed',detail:err.message};
      if(state){state.textContent='Failed';state.className='crawl-state failed'}
      if(result)result.textContent=err.message;
    }
    report.sitemap.results.push(entry);done.set(url,entry);
    report.sitemap.crawled=report.sitemap.results.length;sitemap();seo();list('alt','Missing alt text');list('h1','Multiple H1 headings');list('external','External links');save();
  }
  report.crawling=false;note('Sitemap SEO review completed.');
}

function renderHistory(){
  const list=$('#historyList');if(!list)return;
  const items=hist();
  list.innerHTML=items.length?items.map(x=>`<article class="history-item" data-id="${safe(x.id)}"><h4>${safe(x.domain)}</h4><p>${new Date(x.createdAt).toLocaleString()}</p></article>`).join(''):'<p class="empty">No saved SEO audits yet.</p>';
}
function openHistory(on=true){$('#historyDrawer')?.classList.toggle('open',on);if(on)renderHistory()}

$('#reportAccordion')?.addEventListener('toggle',e=>{
  const item=e.target.closest('.accordion-item');
  if(!item||e.target!==item||!item.open)return;
  $$('#reportAccordion .accordion-item').forEach(x=>{if(x!==item)x.open=false});
  if(item.dataset.panel==='pages')crawl();
  icons();
},true);

document.addEventListener('click',e=>{
  const f=e.target.closest('.filter');
  if(f){filter=f.dataset.filter;$$('.filter').forEach(x=>x.classList.toggle('active',x===f));issues()}
  const d=e.target.closest('.device');
  if(d){device=d.dataset.device;metrics();loadLabMetrics(device)}
  if(e.target.closest('#retryLab'))loadLabMetrics(device,{force:true});
  const h=e.target.closest('.history-item');
  if(h){const cached=hist().find(x=>x.id===h.dataset.id);if(cached){showCachedReport(cached,{fetchLab:!cached.lab?.[device]?.metrics});openHistory(false)}}
});
$('#newAuditButton').onclick=()=>{report=null;syncAuditUrl('');showHero(true);$('#report').hidden=true;$('#start').scrollIntoView({behavior:'smooth'});$('#siteUrl')?.focus()};
$('#exportButton').onclick=()=>{if(!report)return note('Run an SEO audit first.');const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([JSON.stringify(report,null,2)],{type:'application/json'}));a.download=`${report.domain}-seo-audit.json`;a.click()};
$('#historyButton')?.addEventListener('click',()=>openHistory(true));
$('#closeHistory')?.addEventListener('click',()=>openHistory(false));
$('#clearHistory')?.addEventListener('click',()=>{localStorage.removeItem(key);renderHistory();note('Saved SEO reports cleared.')});

(function bootFromUrl(){
  const url=auditParam();
  if(!url)return;
  const cached=findCached(url);
  if(cached){showCachedReport(cached,{fetchLab:false});return}
  const input=$('#siteUrl');if(input)input.value=url;
})();
icons();
