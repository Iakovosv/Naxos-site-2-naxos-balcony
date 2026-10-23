const PORT=9222;
const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent('http://127.0.0.1:12000/index.html')}`,{method:'PUT'})).json();
const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map(); const failed=[];
const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
ws.onmessage=e=>{const m=JSON.parse(e.data);
 if(m.method==='Network.loadingFailed'){failed.push(m.params.errorText);}
 if(m.method==='Network.responseReceived'){const r=m.params.response; if(r.status>=400) failed.push(r.status+' '+r.url);}
 if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
await new Promise(r=>ws.onopen=r);
await send('Page.enable'); await send('Network.enable');
await send('Emulation.setDeviceMetricsOverride',{width:1280,height:900,deviceScaleFactor:1,mobile:false});
await send('Page.navigate',{url:'http://127.0.0.1:12000/index.html'});
await new Promise(r=>setTimeout(r,4000));
const {result}=await send('Runtime.evaluate',{expression:`(()=>{const imgs=[...document.images].map(i=>({src:i.currentSrc||i.src,natural:i.naturalWidth,ok:i.complete&&i.naturalWidth>0}));const heroBg=getComputedStyle(document.querySelector('.hero')).backgroundImage;return {imgs,heroBg}})()`,returnByValue:true});
console.log(JSON.stringify(result.value,null,2));
console.log("FAILED:", JSON.stringify(failed));
ws.close(); process.exit(0);
