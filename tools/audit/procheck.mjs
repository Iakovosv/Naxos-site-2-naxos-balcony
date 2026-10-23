const PORT=9222;
const t=await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent('http://127.0.0.1:12000/forecast-pro.html')}`,{method:'PUT'})).json();
const ws=new WebSocket(t.webSocketDebuggerUrl); let id=0; const p=new Map(); const errs=[];
const send=(m,q={})=>new Promise((res,rej)=>{const i=++id;p.set(i,{res,rej});ws.send(JSON.stringify({id:i,method:m,params:q}));});
ws.onmessage=e=>{const m=JSON.parse(e.data);
 if(m.method==='Runtime.exceptionThrown'){errs.push(m.params.exceptionDetails.text+' '+(m.params.exceptionDetails.exception?.description||''));}
 if(m.method==='Log.entryAdded'&&m.params.entry.level==='error'){errs.push('log: '+m.params.entry.text);}
 if(m.id&&p.has(m.id)){const q=p.get(m.id);p.delete(m.id);m.error?q.rej(new Error(JSON.stringify(m.error))):q.res(m.result);}};
await new Promise(r=>ws.onopen=r);
await send('Page.enable'); await send('Runtime.enable'); await send('Log.enable');
await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
await send('Page.navigate',{url:'http://127.0.0.1:12000/forecast-pro.html'});
await new Promise(r=>setTimeout(r,5000));
const {result}=await send('Runtime.evaluate',{expression:`(()=>({
  chartLoaded: typeof Chart!=='undefined',
  temp:document.getElementById('temp').textContent,
  feels:document.getElementById('feels').textContent,
  wind:document.getElementById('wind').textContent,
  gust:document.getElementById('gust').textContent,
  dir:document.getElementById('dir').textContent,
  hum:document.getElementById('hum').textContent,
  dew:document.getElementById('dew').textContent,
  press:document.getElementById('press').textContent,
  rain_rate:document.getElementById('rain_rate').textContent,
  rain_day:document.getElementById('rain_day').textContent,
  uv:document.getElementById('uv').textContent,
  solar:document.getElementById('solar').textContent,
  updated:document.getElementById('updated').textContent,
  forecastItems:document.querySelectorAll('.forecast-item').length,
  rainChartData:(window.rainChart?rainChart.data.labels.length:0),
  pressChartData:(window.pressureChart?pressureChart.data.labels.length:0),
  tempChartData:(window.tempChart?tempChart.data.labels.length:0),
  bg:getComputedStyle(document.getElementById('bgFix')).backgroundImage.split('/').pop(),
  overflow:document.documentElement.scrollWidth>innerWidth
}))()`,returnByValue:true});
console.log(JSON.stringify(result.value,null,2));
console.log("ERRORS:", JSON.stringify(errs)); ws.close(); process.exit(0);
