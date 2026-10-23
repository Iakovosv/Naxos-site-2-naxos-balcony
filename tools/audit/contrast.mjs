const PORT=9222;
async function open(url,width){
 const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`,{method:'PUT'})).json();
 const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map();
 const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
 ws.onmessage=e=>{const m=JSON.parse(e.data); if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
 await new Promise(r=>ws.onopen=r);
 await send('Page.enable'); await send('Runtime.enable');
 await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<900});
 await send('Page.navigate',{url}); await new Promise(r=>setTimeout(r,2200));
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,returnByValue:true,awaitPromise:true})).result.value;
 return {ev,close:()=>ws.close()};
}
function lum(c){const[r,g,b]=c.map(v=>{v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4)});return 0.2126*r+0.7152*g+0.0722*b;}
function ratio(a,b){const l1=lum(a),l2=lum(b);return ((Math.max(l1,l2)+0.05)/(Math.min(l1,l2)+0.05)).toFixed(2);}
const parse=s=>s.match(/\d+/g).slice(0,3).map(Number);

for (const [pg,w] of [['index.html',1280],['index.html',375],['en/index.html',1280]]) {
  const {ev,close}=await open('http://127.0.0.1:12000/'+pg,w);
  const r=await ev(`(() => {
    const btn=document.querySelector('.call-btn');
    const cs=getComputedStyle(btn);
    const nb=getComputedStyle(document.getElementById('navbar'));
    const logo=document.querySelector('.logo img');
    const lr=logo.getBoundingClientRect();
    return { text: btn.textContent.trim(), color: cs.color, bg: cs.backgroundColor,
             navBg: nb.backgroundColor, logoVisible: lr.width>0 && lr.height>0,
             logoW: Math.round(lr.width), logoH: Math.round(lr.height),
             logoOk: logo.complete && logo.naturalWidth>0 };
  })()`);
  const fg=parse(r.color);
  // effective background: the button's own bg, or the navbar's if transparent
  const bgRaw = r.bg==='rgba(0, 0, 0, 0)' ? parse(r.navBg) : parse(r.bg);
  console.log(`${pg} @${w}px  "${r.text}"  fg=${r.color} bg=${r.bg==='rgba(0, 0, 0, 0)'?'(navbar)'+r.navBg:r.bg}  contrast=${ratio(fg,bgRaw)}  logo=${r.logoW}x${r.logoH} loaded=${r.logoOk}`);
  close();
}
