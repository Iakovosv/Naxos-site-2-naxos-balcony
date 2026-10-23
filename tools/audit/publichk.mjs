const BASE='https://work-1-hnyicijvgiyfjvbs.prod-runtime.all-hands.dev';
const PORT=9222;
async function open(url,w,h){
 const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`,{method:'PUT'})).json();
 const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map();
 const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
 ws.onmessage=e=>{const m=JSON.parse(e.data); if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
 await new Promise(r=>ws.onopen=r);
 await send('Page.enable'); await send('Runtime.enable');
 await send('Emulation.setDeviceMetricsOverride',{width:w,height:h,deviceScaleFactor:1,mobile:w<900});
 await send('Page.navigate',{url}); await new Promise(r=>setTimeout(r,2800));
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,returnByValue:true,awaitPromise:true})).result.value;
 // Walk the page so lazy-loaded images actually start fetching, then wait for
 // them. Without this the "broken" count just measures how slow the tunnel is.
 await ev(`(async()=>{for(let y=0;y<document.body.scrollHeight;y+=500){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,200));}window.scrollTo(0,0);})()`);
 await ev(`Promise.all([...document.images].filter(i=>i.getAttribute('src')&&!i.complete).map(i=>new Promise(r=>{i.onload=i.onerror=r;setTimeout(r,20000);})))`);
 await new Promise(r=>setTimeout(r,1500));
 return {ev,close:()=>ws.close()};
}
const pages=['/','/gallery.html','/amenities.html','/weather.html','/contact.html','/forecast-pro.html',
             '/en/','/en/gallery.html','/en/amenities.html','/en/weather.html','/en/contact.html','/en/forecast-pro.html'];
let problems=0;
for (const w of [375,768,1280]) {
  for (const pg of pages) {
    const {ev,close}=await open(BASE+pg,w,w<900?812:900);
    const r=await ev(`(()=>{
      const de=document.documentElement;
      const overflow=de.scrollWidth>de.clientWidth+1;
      const imgs=[...document.images];
      const broken=imgs.filter(i=>i.getAttribute('src')&&!i.complete||i.naturalWidth===0&&i.getAttribute('src')).map(i=>i.getAttribute('src'));
      const noalt=imgs.filter(i=>i.alt===undefined||i.alt===null).length;
      const nav=document.getElementById('navbar');
      const logo=document.querySelector('.logo img');
      return {overflow, scrollW:de.scrollWidth, clientW:de.clientWidth,
              broken:broken.slice(0,3), brokenN:broken.length, imgs:imgs.length,
              navBg:nav?getComputedStyle(nav).backgroundColor:null,
              logoOk:logo?logo.naturalWidth>0:null};
    })()`);
    const bad = r.overflow || r.brokenN>0 || r.logoOk===false;
    if (bad) problems++;
    console.log(`  ${bad?'❌':'✅'} ${pg.padEnd(20)} @${String(w).padEnd(4)} overflow=${r.overflow} imgs=${r.imgs} broken=${r.brokenN} logo=${r.logoOk}`);
    close();
  }
}
console.log(problems===0?'\n✅ 0 ΠΡΟΒΛΗΜΑΤΑ':`\n❌ ${problems} προβλήματα`);
