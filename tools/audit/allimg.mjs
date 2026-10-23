const PORT=9222;
const pages=['index','gallery','amenities','weather','contact'];
const fails={};
for(const pg of pages){
 const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent('http://127.0.0.1:12000/'+pg+'.html')}`,{method:'PUT'})).json();
 const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map(); const bad=[];
 const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
 ws.onmessage=e=>{const m=JSON.parse(e.data);
  if(m.method==='Network.responseReceived'){const r=m.params.response; if(r.status>=400) bad.push(r.status+' '+r.url.replace('http://127.0.0.1:12000/',''));}
  if(m.method==='Network.loadingFailed'){bad.push('FAILED '+m.params.errorText);}
  if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
 await new Promise(r=>ws.onopen=r);
 await send('Page.enable'); await send('Network.enable');
 await send('Emulation.setDeviceMetricsOverride',{width:1280,height:900,deviceScaleFactor:1,mobile:false});
 await send('Page.navigate',{url:'http://127.0.0.1:12000/'+pg+'.html'});
 await new Promise(r=>setTimeout(r,5000));
 const {result}=await send('Runtime.evaluate',{expression:`(()=>{const i=[...document.images];return {total:i.length,broken:i.filter(x=>!x.complete||x.naturalWidth===0).map(x=>x.src.replace(location.origin+'/','')), heroBg:document.querySelector('.hero')?getComputedStyle(document.querySelector('.hero')).backgroundImage:null}})()`,returnByValue:true});
 fails[pg]={...result.value, httpErrors:[...new Set(bad)]};
 ws.close();
}
console.log(JSON.stringify(fails,null,2));
process.exit(0);
