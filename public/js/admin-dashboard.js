/**
 * NdamStore — Vue d'ensemble admin (abonnements uniquement)
 */
(() => {
  'use strict';

  const dataEl = document.getElementById('admin-chart-data');
  const trendCanvas = document.getElementById('adminTrendChart');
  if (!dataEl || !trendCanvas || typeof Chart === 'undefined') {
    return;
  }

  /** @type {any} */
  const payload = JSON.parse(dataEl.textContent || '{}');

  const periodEl = document.getElementById('adminChartPeriod');
  const metricEl = document.getElementById('adminChartMetric');
  const typeEl = document.getElementById('adminChartType');
  const pieTypeEl = document.getElementById('adminPieType');
  const titleEl = document.getElementById('adminTrendTitle');
  const totalEl = document.getElementById('adminTrendTotal');
  const summaryEl = document.getElementById('adminChartSummary');
  const resetBtn = document.getElementById('adminChartReset');
  const exportBtn = document.getElementById('adminChartExport');

  const COLORS = {
    brand: '#0c5c50',
    mid: '#147a6a',
    soft: 'rgba(20,122,106,.22)',
    grid: 'rgba(19,32,28,.08)',
    muted: '#5f726c',
    palette: ['#0c5c50', '#ef4444', '#6b7280', '#147a6a', '#3bb39e', '#f59e0b'],
  };

  const METRIC_META = {
    revenue: {
      title: 'Sommes reçues (abonnements)',
      unit: 'FCFA',
      series: payload.revenueByMonth || [],
    },
    cancelled: {
      title: 'Résiliations',
      unit: '',
      series: payload.cancelledByMonth || [],
    },
  };

  let trendChart = null;

  const money = (n) =>
    new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Number(n) || 0);

  const sliceSeries = (series, months) => {
    const n = Number(months) || 12;
    return series.slice(Math.max(0, series.length - n));
  };

  const destroy = (chart) => {
    if (chart) chart.destroy();
  };

  const buildTrend = () => {
    const metricKey = metricEl?.value || 'revenue';
    const meta = METRIC_META[metricKey] || METRIC_META.revenue;
    const months = periodEl?.value || '12';
    const mode = typeEl?.value || 'bar';
    const rows = sliceSeries(meta.series, months);
    const labels = rows.map((r) => r.label);
    const values = rows.map((r) => Number(r.value) || 0);
    const total = values.reduce((a, b) => a + b, 0);

    if (titleEl) titleEl.textContent = `${meta.title} (${months} mois)`;
    if (totalEl) {
      totalEl.textContent = meta.unit ? `${money(total)} ${meta.unit}` : String(Math.round(total));
    }
    if (summaryEl) {
      const avg = values.length ? total / values.length : 0;
      const peak = values.length ? Math.max(...values) : 0;
      summaryEl.textContent = meta.unit
        ? `Total : ${money(total)} FCFA · Moyenne/mois : ${money(avg)} FCFA · Meilleur mois : ${money(peak)} FCFA`
        : `Total résiliations : ${Math.round(total)} · Moyenne/mois : ${avg.toFixed(1)} · Pic : ${Math.round(peak)}`;
    }

    const isArea = mode === 'area';
    const chartType = mode === 'bar' ? 'bar' : 'line';

    destroy(trendChart);
    trendChart = new Chart(trendCanvas, {
      type: chartType,
      data: {
        labels,
        datasets: [{
          label: meta.title,
          data: values,
          borderColor: COLORS.brand,
          backgroundColor: (context) => {
            if (chartType === 'bar') return COLORS.mid;
            if (!isArea) return 'transparent';
            const { ctx, chartArea } = context.chart;
            if (!chartArea) return COLORS.soft;
            const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
            g.addColorStop(0, 'rgba(20,122,106,.35)');
            g.addColorStop(1, 'rgba(20,122,106,0)');
            return g;
          },
          fill: isArea,
          tension: 0.35,
          pointRadius: chartType === 'line' ? 3 : 0,
          pointBackgroundColor: COLORS.brand,
          borderWidth: 2.4,
          borderRadius: 6,
        }],
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const v = ctx.parsed.y ?? 0;
                return meta.unit ? `${money(v)} ${meta.unit}` : String(v);
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: COLORS.grid },
            ticks: {
              color: COLORS.muted,
              callback: (v) => (meta.unit ? money(v) : v),
            },
          },
          x: {
            grid: { display: false },
            ticks: { color: COLORS.muted, maxRotation: 0 },
          },
        },
      },
    });
  };

  const buildDistribution = (canvasId, dist, storeKey) => {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const type = pieTypeEl?.value || 'doughnut';
    const labels = dist?.labels || [];
    const values = (dist?.values || []).map((v) => Number(v) || 0);

    destroy(window[storeKey]);
    const chart = new Chart(canvas, {
      type: type === 'bar' ? 'bar' : type,
      data: {
        labels,
        datasets: [{
          data: values,
          backgroundColor: COLORS.palette,
          borderWidth: type === 'bar' ? 0 : 2,
          borderColor: '#fff',
          borderRadius: type === 'bar' ? 6 : 0,
        }],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: type !== 'bar',
            position: 'bottom',
            labels: { boxWidth: 12, color: COLORS.muted },
          },
        },
        scales: type === 'bar'
          ? {
              y: { beginAtZero: true, ticks: { precision: 0, color: COLORS.muted }, grid: { color: COLORS.grid } },
              x: { grid: { display: false }, ticks: { color: COLORS.muted } },
            }
          : undefined,
      },
    });
    window[storeKey] = chart;
    return chart;
  };

  const rebuildPies = () => {
    buildDistribution('adminHealthChart', payload.paymentHealth, '_adminHealthChart');
    buildDistribution('adminPlanChart', payload.plans, '_adminPlanChart');
    buildDistribution('adminStatusChart', payload.statuses, '_adminStatusChart');
  };

  const rebuildAll = () => {
    buildTrend();
    rebuildPies();
  };

  periodEl?.addEventListener('change', buildTrend);
  metricEl?.addEventListener('change', buildTrend);
  typeEl?.addEventListener('change', buildTrend);
  pieTypeEl?.addEventListener('change', rebuildPies);

  resetBtn?.addEventListener('click', () => {
    if (periodEl) periodEl.value = '12';
    if (metricEl) metricEl.value = 'revenue';
    if (typeEl) typeEl.value = 'bar';
    if (pieTypeEl) pieTypeEl.value = 'doughnut';
    rebuildAll();
  });

  exportBtn?.addEventListener('click', () => {
    if (!trendChart) return;
    const link = document.createElement('a');
    link.download = `ndamstore-abonnements-${Date.now()}.png`;
    link.href = trendChart.toBase64Image('image/png', 1);
    link.click();
  });

  rebuildAll();
})();
