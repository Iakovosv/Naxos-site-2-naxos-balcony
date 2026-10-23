/* Weather Station Pro — ζωντανά δεδομένα από τον μετεωρολογικό σταθμό του καταλύματος.
   Ίδια κείμενα/ετικέτες με το πρωτότυπο forecast-pro.html. */

let rainChart, pressureChart, tempChart;

function kmhToBeaufort(kmh) {
  if (!kmh || kmh < 1) return 0;
  if (kmh < 6) return 1;
  if (kmh < 12) return 2;
  if (kmh < 20) return 3;
  if (kmh < 29) return 4;
  if (kmh < 39) return 5;
  if (kmh < 50) return 6;
  if (kmh < 62) return 7;
  if (kmh < 75) return 8;
  if (kmh < 89) return 9;
  if (kmh < 103) return 10;
  return 11;
}

// Τοπικό preview: δεν υπάρχει το PHP api → δείχνουμε δείγμα.
const IS_PREVIEW = /^(localhost|127\.0\.0\.1)$/.test(location.hostname);

// The English copy of this page lives in /en/ and loads the same script, so
// every relative path below has to be resolved against the script's own URL.
// Using document-relative paths would look for /en/api/... and /en/images/...
const SITE_ROOT = (function () {
  const el = document.currentScript ||
    Array.prototype.slice.call(document.scripts).filter(s => /pro\.js/.test(s.src)).pop();
  return el ? el.src.replace(/pro\.js.*$/, '') : '';
})();

const DEMO_LATEST = {
  temperature: 25.9, feels_like: 25.9, wind_speed: 7.2, wind_gust: 13,
  wind_dir: 'ΝΝΔ', wind_deg: 199, humidity: 42, dew_point: 12, pressure: 1016.8,
  rain_rate: 0, rain_year: 363.6, uv_index: 1, solar_radiation: 164.02,
  timestamp: new Date().toISOString(),
  history: {
    pressure: Array.from({ length: 24 }, (_, i) => ({
      hr: String(i).padStart(2, '0') + ':00',
      press: +(1014 + 3 * Math.sin(i / 4)).toFixed(1)
    })),
    monthly: Array.from({ length: 24 }, (_, i) => ({
      year: i < 12 ? '2025' : '2026',
      mon: (i % 12) + 1,
      total_rain: +(Math.random() * 90).toFixed(1),
      tmax: +(24 + 6 * Math.sin(((i % 12) - 3) / 12 * 6.28)).toFixed(1),
      tmin: +(15 + 6 * Math.sin(((i % 12) - 3) / 12 * 6.28)).toFixed(1)
    }))
  }
};

const DEMO_FORECAST = (() => {
  const now = Date.now(), DAY = 864e5;
  return {
    validTimeLocal: [null, ...Array.from({ length: 3 }, (_, i) => new Date(now + (i + 1) * DAY).toISOString())],
    calendarDayTemperatureMax: [null, 26, 27, 25],
    calendarDayTemperatureMin: [null, 18, 19, 17],
    daypart: [{
      iconCode: [null, null, 32, 32, 30, 30, 28, 28],
      precipChance: [null, null, 0, 0, 10, 10, 20, 20],
      windSpeed: [null, null, 12, 14, 16, 15, 11, 10],
      windDirectionCardinal: [null, null, 'Ν', 'Ν', 'ΝΔ', 'ΝΔ', 'Δ', 'Δ'],
      relativeHumidity: [null, null, 45, 43, 48, 50, 52, 51]
    }]
  };
})();

async function fetchJSON(url) {
  const r = await fetch(url + '?v=' + Date.now());
  if (!r.ok) throw new Error('HTTP ' + r.status);
  return r.json();
}

// Αλλάζει το φόντο ανάλογα με ήλιο/βροχή. Αν λείπει κάποια εικόνα, μένει το βασικό.
function updateDynamicBackground(solar, rain) {
  const bgEl = document.getElementById('bgFix');
  if (!bgEl) return;
  const isNight = parseFloat(solar) < 15;
  const isRaining = parseFloat(rain) > 0;
  let newImg;
  if (isRaining) newImg = isNight ? 'naxos-balcony-exterior-night-rain.jpg' : 'naxos-balcony-exterior-rain.jpg';
  else newImg = isNight ? 'naxos-balcony-exterior-night.jpg' : 'ai-exterior.jpg';

  const path = SITE_ROOT + 'images/gallery/exterior/';
  if (bgEl.dataset.bg === newImg) return;
  const img = new Image();
  img.onload = function () {
    bgEl.style.backgroundImage = "url('" + path + newImg + "')";
    bgEl.dataset.bg = newImg;
  };
  img.src = path + newImg;
}

async function load() {
  try {
    const d = IS_PREVIEW ? DEMO_LATEST : await fetchJSON(SITE_ROOT + 'api/weather-latest-pro.php');

    document.getElementById('temp').textContent = d.temperature + '°C';
    document.getElementById('feels').textContent = d.feels_like + '°C';
    document.getElementById('wind').textContent = d.wind_speed + ' km/h (' + kmhToBeaufort(d.wind_speed) + ' Bf)';
    document.getElementById('gust').textContent = d.wind_gust + ' km/h (' + kmhToBeaufort(d.wind_gust) + ' Bf)';
    document.getElementById('dir').textContent = d.wind_dir;
    document.getElementById('wind_arrow').style.transform = 'rotate(' + d.wind_deg + 'deg)';
    document.getElementById('hum').textContent = d.humidity + '%';
    document.getElementById('dew').textContent = d.dew_point + '°C';
    document.getElementById('press').textContent = d.pressure + ' hPa';
    document.getElementById('rain_rate').textContent = d.rain_rate + ' mm/h';
    document.getElementById('rain_day').textContent = d.rain_year + ' mm';
    document.getElementById('uv').textContent = d.uv_index;
    document.getElementById('solar').textContent = d.solar_radiation + ' W/m²';

    updateDynamicBackground(d.solar_radiation, d.rain_rate);
    document.getElementById('updated').textContent =
      d.timestamp ? new Date(d.timestamp).toLocaleString('el-GR') : new Date().toLocaleString('el-GR');

    if (d.history) {
      if (d.history.pressure && pressureChart) {
        pressureChart.data.labels = d.history.pressure.map(i => i.hr);
        pressureChart.data.datasets[0].data = d.history.pressure.map(i => i.press);
        pressureChart.update();
      }
      if (d.history.monthly && rainChart && tempChart) {
        const months = ['Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μαι', 'Ιουν', 'Ιουλ', 'Αυγ', 'Σεπ', 'Οκτ', 'Νοε', 'Δεκ'];
        const labels = d.history.monthly.map(i => months[parseInt(i.mon) - 1] + ' ' + i.year.toString().substr(-2));
        const dynamicWidth = Math.max(window.innerWidth - 60, d.history.monthly.length * 45);
        document.getElementById('rain-inner').style.width = dynamicWidth + 'px';
        document.getElementById('temp-inner').style.width = dynamicWidth + 'px';

        rainChart.data.labels = labels;
        rainChart.data.datasets[0].data = d.history.monthly.map(i => i.total_rain);
        rainChart.update();

        tempChart.data.labels = labels;
        tempChart.data.datasets[0].data = d.history.monthly.map(i => i.tmax);
        tempChart.data.datasets[1].data = d.history.monthly.map(i => i.tmin);
        tempChart.update();
      }
    }
  } catch (e) {
    console.error('Error loading weather data:', e);
  }
}

async function loadForecast() {
  try {
    const d = IS_PREVIEW ? DEMO_FORECAST : await fetchJSON(SITE_ROOT + 'api/weather-forecast.php');
    const grid = document.getElementById('forecast-grid');
    let html = '';

    for (let i = 1; i <= 3; i++) {
      const date = new Date(d.validTimeLocal[i]);
      const dayName = date.toLocaleDateString('el-GR', { weekday: 'long' });
      const iconCode = d.daypart[0].iconCode[i * 2] || d.daypart[0].iconCode[i * 2 + 1];
      const maxTemp = d.calendarDayTemperatureMax[i];
      const minTemp = d.calendarDayTemperatureMin[i];
      const precip = d.daypart[0].precipChance[i * 2] !== null ? d.daypart[0].precipChance[i * 2] : 0;
      const windSpeed = d.daypart[0].windSpeed[i * 2];
      const windDir = d.daypart[0].windDirectionCardinal[i * 2];
      const humidity = d.daypart[0].relativeHumidity[i * 2];

      html += `
        <div class="forecast-item">
          <div class="fc-date">${dayName}</div>
          <img class="forecast-icon" src="https://www.wunderground.com/static/i/c/v4/${iconCode}.svg" alt="weather" loading="lazy">
          <div><span class="fc-temp-max">${maxTemp}°</span><span class="fc-temp-min">${minTemp}°</span></div>
          <div class="fc-details">
            <div>💧 Βροχή: <strong>${precip}%</strong></div>
            <div>💨 Άνεμος: <strong>${windSpeed} km/h</strong> <span class="fc-wind-dir">${windDir}</span></div>
            <div>💦 Υγρασία: <strong>${humidity}%</strong></div>
          </div>
        </div>`;
    }
    grid.innerHTML = html;
  } catch (e) {
    console.error('Forecast Error:', e);
  }
}

function initCharts() {
  if (typeof Chart === 'undefined') return;
  const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#fff' } } },
    scales: {
      x: { ticks: { color: '#ccc', font: { size: 10 } } },
      y: { ticks: { color: '#ccc' } }
    }
  };

  rainChart = new Chart(document.getElementById('rainChart'), {
    type: 'bar',
    data: { labels: [], datasets: [{ label: 'Βροχή (mm)', data: [], backgroundColor: 'rgba(0, 212, 255, 0.6)' }] },
    options: commonOptions
  });

  pressureChart = new Chart(document.getElementById('pressureChart'), {
    type: 'line',
    data: { labels: [], datasets: [{ label: 'Πίεση (hPa)', data: [], borderColor: '#00d4ff', tension: 0.4, fill: true, backgroundColor: 'rgba(0,212,255,0.1)' }] },
    options: { ...commonOptions, scales: { y: { min: 980, max: 1040 } } }
  });

  tempChart = new Chart(document.getElementById('tempChart'), {
    type: 'line',
    data: { labels: [], datasets: [{ label: 'Max', data: [], borderColor: '#ff4d4d', tension: 0.3 }, { label: 'Min', data: [], borderColor: '#00d4ff', tension: 0.3 }] },
    options: commonOptions
  });
}

initCharts();
load();
loadForecast();
setInterval(load, 60000);
