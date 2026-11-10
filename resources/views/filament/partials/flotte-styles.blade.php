<style>
.fl-verdict{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:99px;font-size:.72rem;font-weight:800;color:#fff;background:var(--v)}
.fl-kpis{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
.fl-kpi{padding:14px 16px;border-radius:18px;background:var(--card);border:1px solid var(--line)}
.fl-kpi small{display:block;font-size:.64rem;letter-spacing:.09em;text-transform:uppercase;opacity:.65;font-weight:800}
.fl-kpi b{display:block;margin-top:4px;font-size:1.25rem;font-weight:900;letter-spacing:-.02em}
.fl-kpi em{display:block;font-style:normal;font-size:.7rem;opacity:.6;margin-top:2px}
.fl-pile{display:flex;height:16px;border-radius:99px;overflow:hidden;background:var(--soft)}
.fl-pile i{display:block;height:100%}
.fl-legende{display:flex;flex-wrap:wrap;gap:6px 16px;margin-top:10px;font-size:.78rem}
.fl-legende span::before{content:"";display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--c);margin-right:6px}
.fl-conseil{display:flex;gap:12px;padding:14px 16px;border-radius:18px;border:1px solid var(--line);border-left:5px solid var(--c);background:var(--card)}
.fl-conseil h5{margin:0 0 3px;font-weight:800;font-size:.92rem}
.fl-conseil p{margin:0;font-size:.82rem;line-height:1.5;opacity:.85}
.fl-rang{cursor:pointer;transition:.15s}
.fl-rang:hover{background:rgba(99,102,241,.08)}
.fl-rang.on{background:rgba(99,102,241,.16)}
.fl-jauge{height:8px;border-radius:99px;background:var(--soft);overflow:hidden;min-width:70px}
.fl-jauge i{display:block;height:100%;border-radius:99px;background:var(--c)}
.fl-liste{display:grid;gap:10px}
.flotte-cars{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,290px),1fr));gap:14px}
.fl-car-card{display:grid;gap:16px;width:100%;padding:18px;text-align:left;color:var(--ink);background:var(--card);border:1px solid var(--line);border-radius:20px;cursor:pointer;transition:border-color .2s,box-shadow .2s,transform .2s}
.fl-car-card:hover,.fl-car-card:focus-visible{border-color:#6366f1;box-shadow:0 12px 28px -18px #6366f1;transform:translateY(-2px);outline:none}
.fl-car-card.on{border-color:#6366f1;background:rgba(99,102,241,.08);box-shadow:0 0 0 2px rgba(99,102,241,.18)}
.fl-car-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.fl-car-head strong,.fl-car-head small,.fl-car-metrics small,.fl-car-metrics b{display:block}
.fl-car-head strong{font:800 1rem ui-monospace,SFMono-Regular,Menlo,monospace}
.fl-car-head small,.fl-car-fill small,.fl-car-metrics small{font-size:.72rem;color:var(--muted)}
.fl-car-fill{display:grid;gap:6px}
.fl-car-fill small{display:flex;justify-content:space-between}
.fl-car-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.fl-car-metrics b{margin-top:3px;font-size:.88rem;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
.flotte-cars-empty{grid-column:1/-1;padding:20px;border:1px dashed var(--line);border-radius:16px;color:var(--muted);text-align:center}
.fl-detail-cards{display:grid;gap:10px}
.fl-detail-card{display:grid;gap:10px;padding:14px;border:1px solid var(--line);border-radius:16px;background:var(--card)}
.fl-detail-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.fl-detail-head h4{margin:0;font-size:.9rem;font-weight:800}
.fl-detail-head small{color:var(--muted);font-size:.72rem}
.fl-detail-head>b{font-size:.75rem;white-space:nowrap}
.fl-detail-metrics{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px 14px;color:var(--muted);font-size:.74rem}
.fl-detail-metrics strong{color:var(--ink);font-variant-numeric:tabular-nums}
@media (prefers-reduced-motion:reduce){.fl-car-card{transition:none}}
</style>