const PORT=9222;
const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent('http://127.0.0.1:12000/gallery.html')}`,{method:'PUT'})).json();
const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map();
const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
await new Promise(r=>ws.onopen=r);
await send('Page.enable');
await send('Emulation.setDeviceMetricsOverride',{width:1280,height:900,deviceScaleFactor:1,mobile:false});
await send('Page.navigate',{url:'http://127.0.0.1:12000/gallery.html'});
await new Promise(r=>setTimeout(r,3000));
// scroll through whole page
for(let i=0;i<40;i++){ await send('Runtime.evaluate',{expression:`window.scrollBy(0,900)`}); await new Promise(r=>setTimeout(r,250)); }
await new Promise(r=>setTimeout(r,3000));
const {result}=await send('Runtime.evaluate',{expression:`(()=>{const i=[...document.querySelectorAll('.tile img')];const broken=i.filter(x=>x.naturalWidth===0);return {tiles:i.length, loaded:i.length-broken.length, broken:broken.map(x=>x.src.split('/').slice(-1)[0])}})()`,returnByValue:true});
console.log(JSON.stringify(result.value,null,2)); ws.close(); process.exit(0);
