const BASE='http://127.0.0.1:12000'; const PORT=9222;
async function open(url,w){
 const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`,{method:'PUT'})).json();
 const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map();
 const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
 ws.onmessage=e=>{const m=JSON.parse(e.data); if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
 await new Promise(r=>ws.onopen=r);
 await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,returnByValue:true,awaitPromise:true})).result.value;
 await send('Emulation.setDeviceMetricsOverride',{width:w,height:900,deviceScaleFactor:1,mobile:w<800});
 await send('Page.navigate',{url}); await new Promise(r=>setTimeout(r,3000));
 await ev(`(async()=>{for(let y=0;y<document.body.scrollHeight;y+=600){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,150));}window.scrollTo(0,0);})()`);
 await ev(`Promise.all([...document.images].filter(i=>!i.complete).map(i=>new Promise(r=>{i.onload=i.onerror=r;setTimeout(r,15000);})))`);
 return {ev,close:()=>ws.close()};
}
for (const [pg,w] of [['/gallery.html',1280],['/en/gallery.html',1280],['/gallery.html',375]]) {
  const {ev,close}=await open(BASE+pg,w);
  const r=await ev(`(()=>{
    const imgs=[...document.images];
    const pics=[...document.querySelectorAll('.tile picture')];
    const thumbs=imgs.filter(i=>/thumb\\.(webp|jpg)/.test(i.currentSrc||i.src));
    const full=imgs.filter(i=>!/-thumb\\./.test(i.src));
    return {tiles:document.querySelectorAll('.tile').length, pictures:pics.length,
            imgs:imgs.length, usingThumb:thumbs.length, stillFull:full.length,
            broken:imgs.filter(i=>i.naturalWidth===0).length,
            sampleThumb:(thumbs[0]&&thumbs[0].currentSrc)||'-'};
  })()`);
  console.log(`  ${pg} @${w}: tiles=${r.tiles} picture=${r.pictures} εικόνες=${r.imgs} thumb=${r.usingThumb} πλήρεις=${r.stillFull} σπασμένες=${r.broken}`);
  console.log(`      δείγμα: ${r.sampleThumb.replace(BASE,'')}`);
  // Δοκιμή lightbox
  await ev(`document.querySelector('.tile a').click()`);
  await new Promise(r=>setTimeout(r,2500));
  const lb=await ev(`(()=>{const i=document.getElementById('lbImg');return {open:document.getElementById('lightbox').classList.contains('open'),src:i.src.replace('${BASE}',''),w:i.naturalWidth};})()`);
  console.log(`      lightbox: open=${lb.open}  ${lb.src}  πλάτος=${lb.w}px ${lb.w>700?'✅ ΠΛΗΡΗΣ':'❌ θολή'}`);
  close();
}
