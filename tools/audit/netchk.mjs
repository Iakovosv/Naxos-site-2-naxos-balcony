const BASE='http://127.0.0.1:12000'; const PORT=9222;
async function measure(url){
 const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`,{method:'PUT'})).json();
 const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map();
 const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
 let bytes=0, byType={};
 ws.onmessage=e=>{const m=JSON.parse(e.data);
   if(m.method==='Network.loadingFinished'){bytes+=m.params.encodedDataLength;}
   if(m.method==='Network.responseReceived'){const u=m.params.response.url;
     const ext=(u.split('.').pop()||'').split('?')[0].toLowerCase();
     byType[ext]=byType[ext]||{n:0}; byType[ext].n++;}
   if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
 await new Promise(r=>ws.onopen=r);
 await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,returnByValue:true,awaitPromise:true})).result.value;
 await send('Emulation.setDeviceMetricsOverride',{width:1280,height:900,deviceScaleFactor:1,mobile:false});
 await send('Page.navigate',{url}); await new Promise(r=>setTimeout(r,2500));
 await ev(`(async()=>{for(let y=0;y<document.body.scrollHeight;y+=600){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,200));}window.scrollTo(0,0);})()`);
 await ev(`Promise.all([...document.images].filter(i=>!i.complete).map(i=>new Promise(r=>{i.onload=i.onerror=r;setTimeout(r,25000);})))`);
 await new Promise(r=>setTimeout(r,2500));
 const loaded=await ev(`[...document.images].filter(i=>i.naturalWidth>0).length+'/'+document.images.length`);
 ws.close();
 return {bytes, loaded};
}
for (const pg of ['/','/gallery.html','/en/gallery.html']) {
  const r=await measure(BASE+pg);
  console.log(`  ${pg.padEnd(18)} ${(r.bytes/1024/1024).toFixed(2)} MB   εικόνες ${r.loaded}`);
}
