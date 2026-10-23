const BASE='http://127.0.0.1:12000'; const PORT=9222;
async function open(url,w,h){
 const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`,{method:'PUT'})).json();
 const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map();
 const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
 ws.onmessage=e=>{const m=JSON.parse(e.data); if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
 await new Promise(r=>ws.onopen=r);
 await send('Page.enable'); await send('Runtime.enable');
 await send('Emulation.setDeviceMetricsOverride',{width:w,height:h,deviceScaleFactor:1,mobile:true});
 await send('Page.navigate',{url}); await new Promise(r=>setTimeout(r,4000));
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,returnByValue:true,awaitPromise:true})).result.value;
 return {ev,close:()=>ws.close()};
}
// Το κλειστό μενού έχει max-height:0 -> μετράμε ΠΡΑΓΜΑΤΙΚΟ ύψος
const probe=`(()=>{
  const nav=document.getElementById('nav'), t=document.getElementById('navToggle');
  const r=nav.getBoundingClientRect();
  const link=[...nav.querySelectorAll('a')][1]; const lr=link.getBoundingClientRect();
  return {navH:Math.round(r.height), linkH:Math.round(lr.height), linkY:Math.round(lr.top),
          open:nav.classList.contains('open'), aria:t.getAttribute('aria-expanded'),
          toggleVisible:getComputedStyle(t).display!=='none'};
})()`;
let bad=0;
for (const pg of ['/','/en/','/gallery.html','/en/contact.html']) {
  const {ev,close}=await open(BASE+pg,375,812);
  const before=await ev(probe);
  await ev(`document.getElementById('navToggle').click()`);
  await new Promise(r=>setTimeout(r,900));
  const after=await ev(probe);
  const ok = before.navH<=2 && !before.open && after.navH>100 && after.open && after.aria==='true';
  if(!ok) bad++;
  console.log(`  ${ok?'✅':'❌'} ${pg.padEnd(18)} κλειστό: ύψος=${before.navH} | ανοιχτό: ύψος=${after.navH} open=${after.open} aria=${after.aria} ύψοςLink=${after.linkH}`);
  close();
}
console.log(bad===0?'\n✅ ΤΟ ΜΕΝΟΥ ΔΟΥΛΕΥΕΙ ΣΩΣΤΑ':`\n❌ ${bad}`);
