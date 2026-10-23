const PORT=9222, BASE='http://127.0.0.1:12000/';
async function open(url,width){
 const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`,{method:'PUT'})).json();
 const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map(); const reqs=[];
 const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
 ws.onmessage=e=>{const m=JSON.parse(e.data);
   if(m.method==='Network.responseReceived'){const r=m.params.response; if(r.status>=400) reqs.push(r.status+' '+r.url.slice(-70));}
   if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
 await new Promise(r=>ws.onopen=r);
 await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
 await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<900});
 await send('Page.navigate',{url}); await new Promise(r=>setTimeout(r,3000));
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,returnByValue:true,awaitPromise:true})).result.value;
 return {ev,reqs,close:()=>ws.close()};
}
const pages=['index.html','gallery.html','amenities.html','weather.html','contact.html','forecast-pro.html','privacy.html','cookies.html','404.html','en/index.html','en/gallery.html','en/amenities.html','en/weather.html','en/contact.html','en/forecast-pro.html','en/privacy.html','en/cookies.html','en/404.html'];
let bad=[];
for (const p of pages) {
  const {ev,reqs,close}=await open(BASE+p,1280);
  const r=await ev(`(() => { const im=[...document.querySelectorAll('img')];
    return { broken: im.filter(i=>i.getAttribute('src') && i.complete && i.naturalWidth===0).map(i=>i.getAttribute('src')).slice(0,4),
             total: im.length, loaded: im.filter(i=>i.naturalWidth>0).length,
             title: document.title.slice(0,55), lang: document.documentElement.lang,
             canonical: (document.querySelector('link[rel=canonical]')||{}).href };
  })()`);
  console.log(`${p.padEnd(20)} lang=${r.lang} imgs=${r.loaded}/${r.total} title="${r.title}"`);
  if (r.broken.length) bad.push(`${p}: broken ${JSON.stringify(r.broken)}`);
  const errs=reqs.filter(u=>!/favicon/.test(u));
  if (errs.length) bad.push(`${p}: HTTP errors ${JSON.stringify(errs.slice(0,3))}`);
  if (!['el','en'].includes(r.lang)) bad.push(`${p}: lang=${r.lang}`);
  if (p.startsWith('en/') ? !/\/en\//.test(r.canonical) : !r.canonical.endsWith(p)) bad.push(`${p}: canonical ${r.canonical}`);
  close();
}
console.log('\n=== ΠΡΟΒΛΗΜΑΤΑ EN: '+bad.length+' ===');
bad.forEach(b=>console.log('  ❌ '+b));
if(!bad.length) console.log('  ✅ ΚΑΘΑΡΟ');
