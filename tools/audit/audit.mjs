const PORT=9222;
const pages=['index','gallery','amenities','weather','contact'];
const widths=[375,768,1280];
async function open(url,width){
 const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`,{method:'PUT'})).json();
 const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map();
 const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
 ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
 await new Promise(r=>ws.onopen=r);
 await send('Page.enable');
 await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<900});
 await send('Page.navigate',{url});
 await new Promise(r=>setTimeout(r,2600));
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,returnByValue:true,awaitPromise:true})).result.value;
 return {send,ev,close:()=>{ws.close()}};
}
const results=[];
for(const pg of pages){
 for(const w of widths){
  const {ev,close}=await open(`http://127.0.0.1:12000/${pg}.html`,w);
  const r=await ev(`(()=>{
    const cs=el=>el?getComputedStyle(el):null;
    const call=document.querySelector('.call-btn');
    const navLink=document.querySelector('.nav-links a');
    const logo=document.querySelector('.logo img');
    const toggle=document.getElementById('navToggle');
    return {
      vw:innerWidth,
      overflow:document.documentElement.scrollWidth>innerWidth+1,
      scrollW:document.documentElement.scrollWidth,
      callBg:call?cs(call).backgroundColor:null,
      callColor:call?cs(call).color:null,
      navLinkColor:navLink?cs(navLink).color:null,
      logoVisible: logo? (logo.getBoundingClientRect().width>0 && getComputedStyle(logo).display!=='none') : null,
      toggleDisplay: toggle?cs(toggle).display:null,
      navOpenMaxHeight: (()=>{const n=document.getElementById('nav');return n?cs(n).maxHeight:null})(),
      langBtns: !!document.getElementById('langEl') && !!document.getElementById('langEn'),
      errs: window.__err||0
    };})()`);
  results.push({pg,w,...r}); close();
 }
}
console.log(JSON.stringify(results,null,1));
