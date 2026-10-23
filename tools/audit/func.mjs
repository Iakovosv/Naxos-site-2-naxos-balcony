const PORT=9222;
async function open(url,width){
 const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`,{method:'PUT'})).json();
 const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map(); const logs=[];
 const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
 ws.onmessage=e=>{const m=JSON.parse(e.data);
   if(m.method==='Runtime.consoleAPICalled'){logs.push(m.params.type+': '+m.params.args.map(a=>a.value||a.description||'').join(' '));}
   if(m.method==='Log.entryAdded'){logs.push('log: '+m.params.entry.text);}
   if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
 await new Promise(r=>ws.onopen=r);
 await send('Page.enable'); await send('Runtime.enable'); await send('Log.enable');
 await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<900});
 await send('Page.navigate',{url});
 await new Promise(r=>setTimeout(r,3500));
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,returnByValue:true,awaitPromise:true})).result.value;
 return {ev,send,logs,close:()=>ws.close()};
}
// GALLERY
{
 const {ev,close}=await open('http://127.0.0.1:12000/gallery.html',1280);
 const a=await ev(`({tiles:document.querySelectorAll('.tile').length, lazy:[...document.querySelectorAll('.tile img')].every(i=>i.loading==='lazy')})`);
 // filter room5
 await ev(`document.querySelector('[data-filter="room5"]').click()`);
 const b=await ev(`({visible:[...document.querySelectorAll('.tile')].filter(t=>t.style.display!=='none').length, info:document.getElementById('room-info').textContent})`);
 // open lightbox
 await ev(`document.querySelectorAll('.tile')[0].querySelector('a').click()`);
 const c=await ev(`({open:document.getElementById('lightbox').classList.contains('open'), img:document.getElementById('lbImg').src.split('/').pop()})`);
 // next
 await ev(`document.getElementById('lbNext').click()`);
 const d=await ev(`document.getElementById('lbImg').src.split('/').pop()`);
 console.log('GALLERY', JSON.stringify({...a,...b,lb:c,next:d})); close();
}
// WEATHER (CORS test)
{
 const {ev,logs,close}=await open('http://127.0.0.1:12000/weather.html',1280);
 await new Promise(r=>setTimeout(r,2500));
 const w=await ev(`({temp:document.getElementById('w_temp').textContent,hum:document.getElementById('w_hum').textContent,status:document.getElementById('w_status').textContent,dot:document.getElementById('w_dot').className})`);
 console.log('WEATHER', JSON.stringify(w));
 console.log('WEATHER LOGS', JSON.stringify(logs.filter(l=>/cors|error|fail|block/i.test(l)))); close();
}
// LANGUAGE on each page
for(const pg of ['index','gallery','amenities','weather','contact']){
 const {ev,close}=await open(`http://127.0.0.1:12000/${pg}.html`,1280);
 await ev(`document.getElementById('langEn').click()`);
 await new Promise(r=>setTimeout(r,400));
 const t=await ev(`({title:document.querySelector('h1').textContent, nav:document.querySelector('[data-i18n="home"]').textContent})`);
 await ev(`document.getElementById('langEl').click()`);
 await new Promise(r=>setTimeout(r,300));
 const t2=await ev(`document.querySelector('h1').textContent`);
 console.log('LANG '+pg, JSON.stringify({en:t,el:t2})); close();
}
