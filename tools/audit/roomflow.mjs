const PORT=9222;
async function open(url,width=1280){
 const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`,{method:'PUT'})).json();
 const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map();
 const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
 ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
 await new Promise(r=>ws.onopen=r);
 await send('Page.enable');
 await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<900});
 await send('Page.navigate',{url});
 for(let i=0;i<120;i++){const r=await send('Runtime.evaluate',{expression:'document.readyState',returnByValue:true}); if(r.result.value==='complete')break; await new Promise(r=>setTimeout(r,100));}
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,returnByValue:true})).result.value;
 return {ev,close:()=>ws.close()};
}
const BASE='http://127.0.0.1:12000/';
for(const [label,home,gal] of [['ΕΛ','index.html','gallery.html'],['ΕΝ','en/index.html','en/gallery.html']]){
 const c=await open(BASE+home);
 // click the Room 5 card link (simulate a real click on the card body, not the title)
 const before=await c.ev(`location.pathname`);
 const clicked=await c.ev(`(()=>{const a=document.querySelectorAll('.room .room-link')[2]; if(!a) return 'no link'; a.click(); return a.getAttribute('href');})()`);
 await new Promise(r=>setTimeout(r,2500));
 const res=await c.ev(`({
   path:location.pathname+location.search+location.hash,
   active:(document.querySelector('.tab-btn.active')||{}).textContent,
   visible:[...document.querySelectorAll('.tile')].filter(t=>t.style.display!=='none').length,
   total:document.querySelectorAll('.tile').length,
   info:document.getElementById('room-info').textContent.slice(0,38),
   scrolled:Math.round(scrollY)
 })`);
 console.log(` ${label}: click "${clicked}"`);
 console.log(`    -> ${res.path}`);
 console.log(`    active tab=${JSON.stringify(res.active)}  visible=${res.visible}/${res.total}  scrollY=${res.scrolled}`);
 console.log(`    caption: ${res.info}...`);
 c.close();
}
// bogus value must not blank the gallery
const c2=await open(BASE+'gallery.html?room=DROP');
const b=await c2.ev(`({visible:[...document.querySelectorAll('.tile')].filter(t=>t.style.display!=='none').length,total:document.querySelectorAll('.tile').length,active:(document.querySelector('.tab-btn.active')||{}).textContent})`);
console.log(` ΑΣΧΕΤΗ ΤΙΜΗ ?room=DROP -> visible=${b.visible}/${b.total} active=${JSON.stringify(b.active)}`);
c2.close();
process.exit(0);
