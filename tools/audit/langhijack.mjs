const BASE='http://127.0.0.1:12000'; const PORT=9222;
async function open(url, setup){
 const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`,{method:'PUT'})).json();
 const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map();
 const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
 ws.onmessage=e=>{const m=JSON.parse(e.data); if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
 await new Promise(r=>ws.onopen=r);
 await send('Page.enable'); await send('Runtime.enable');
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,returnByValue:true,awaitPromise:true})).result.value;
 // Θέτουμε τη προτίμηση ΠΡΙΝ φορτώσει η σελίδα
 await send('Page.navigate',{url:'about:blank'});
 if(setup) await ev(setup);
 await send('Page.navigate',{url}); await new Promise(r=>setTimeout(r,3000));
 return {ev,close:()=>ws.close()};
}
console.log('ΣΕΝΑΡΙΟ: ο χρήστης διάλεξε ΑΓΓΛΙΚΑ, μετά ανοίγει την ΕΛΛΗΝΙΚΗ αρχική');
let {ev,close}=await open(BASE+'/index.html',
  `localStorage.setItem('preferredLang','en')`);
console.log('  html lang        :', await ev(`document.documentElement.lang`));
console.log('  nav "Αρχική"     :', await ev(`document.querySelector('.nav-links a')?.textContent.trim()`));
console.log('  hero h1          :', await ev(`document.querySelector('h1')?.textContent?.trim().slice(0,50)`));
console.log('  κουμπί           :', await ev(`document.querySelector('.call-btn')?.textContent.trim()`));
close();
console.log();
console.log('ΣΕΝΑΡΙΟ: ο χρήστης διάλεξε ΕΛΛΗΝΙΚΑ, μετά ανοίγει την ΑΓΓΛΙΚΗ αρχική');
({ev,close}=await open(BASE+'/en/index.html',
  `localStorage.setItem('preferredLang','el')`));
console.log('  html lang        :', await ev(`document.documentElement.lang`));
console.log('  nav "Home"       :', await ev(`document.querySelector('.nav-links a')?.textContent.trim()`));
console.log('  hero h1          :', await ev(`document.querySelector('h1')?.textContent?.trim().slice(0,50)`));
close();
