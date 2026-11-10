<style>
.rf{--ink:#0f172a;--muted:#64748b;--card:#fff;--line:rgba(15,23,42,.08);--a:#0f766e;--b:#14b8a6;--gold:#f59e0b;--ok:#10b981;--bad:#ef4444;--soft:#f1f5f9;display:grid;gap:22px;color:var(--ink)}
.dark .rf{--ink:#f1f5f9;--muted:#94a3b8;--card:rgba(30,41,59,.72);--line:rgba(255,255,255,.09);--soft:rgba(255,255,255,.07)}
@keyframes rf-rise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
@keyframes rf-float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.rf>*{animation:rf-rise .65s cubic-bezier(.2,.8,.2,1) both}
.rf>*:nth-child(2){animation-delay:.06s}.rf>*:nth-child(3){animation-delay:.12s}.rf>*:nth-child(4){animation-delay:.18s}.rf>*:nth-child(5){animation-delay:.24s}.rf>*:nth-child(6){animation-delay:.3s}
@media (prefers-reduced-motion:reduce){.rf *{animation:none!important;transition:none!important}}

.rf-hero{position:relative;overflow:hidden;border-radius:28px;padding:30px 34px;color:#fff;
 background:radial-gradient(900px 380px at 92% -25%,rgba(245,158,11,.6),transparent 60%),radial-gradient(800px 460px at -8% 125%,rgba(20,184,166,.65),transparent 55%),linear-gradient(135deg,#042f2e 0%,#0f766e 58%,#115e59 100%);
 box-shadow:0 34px 60px -30px rgba(15,118,110,.75)}
.rf-hero::before{content:"";position:absolute;width:260px;height:260px;right:-60px;top:-80px;border-radius:50%;border:1px solid rgba(255,255,255,.25);animation:rf-float 7s ease-in-out infinite}
.rf-hero::after{content:"";position:absolute;inset:0;pointer-events:none;background-image:linear-gradient(rgba(255,255,255,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.07) 1px,transparent 1px);background-size:36px 36px;-webkit-mask-image:radial-gradient(circle at 85% 15%,#000,transparent 68%);mask-image:radial-gradient(circle at 85% 15%,#000,transparent 68%)}
.rf-hero-grid{position:relative;z-index:1;display:grid;grid-template-columns:1.15fr 1fr;gap:26px;align-items:center}
@media (max-width:900px){.rf-hero-grid{grid-template-columns:1fr}}
.rf-eyebrow{letter-spacing:.2em;text-transform:uppercase;font-size:.7rem;opacity:.82;font-weight:700}
.rf-big{font-size:clamp(2.1rem,5.5vw,3.9rem);font-weight:800;line-height:1.05;letter-spacing:-.035em;margin:8px 0 14px}
.rf-big small{font-size:.38em;font-weight:700;opacity:.85;margin-left:.45rem;letter-spacing:0}
.rf-chips{display:flex;flex-wrap:wrap;gap:8px}
.rf-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 13px;border-radius:999px;font-size:.78rem;font-weight:700;background:rgba(255,255,255,.15);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.22)}
.rf-spark{width:100%;height:120px;display:block}
.rf-spark-cap{display:flex;justify-content:space-between;font-size:.7rem;opacity:.75;margin-top:6px}

.rf-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 14px;border-radius:20px;background:var(--card);border:1px solid var(--line);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px)}
.rf-pill{border:1px solid var(--line);background:var(--soft);color:var(--ink);padding:7px 14px;border-radius:999px;font-size:.8rem;font-weight:700;cursor:pointer;transition:.2s}
.rf-pill:hover{transform:translateY(-2px)}
.rf-pill.is-on{background:linear-gradient(135deg,var(--a),var(--b));color:#fff;border-color:transparent;box-shadow:0 10px 22px -10px var(--a)}
.rf-select,.rf-date{border:1px solid var(--line);background:var(--card);color:var(--ink);padding:7px 12px;border-radius:12px;font-size:.8rem;font-weight:600;max-width:210px}
.rf-sep{width:1px;height:26px;background:var(--line)}

.rf-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}
.rf-kpi{position:relative;overflow:hidden;background:var(--card);border:1px solid var(--line);border-radius:22px;padding:18px 18px 16px;transition:.25s}
.rf-kpi:hover{transform:translateY(-5px);box-shadow:0 22px 36px -22px var(--a)}
.rf-kpi::before{content:"";position:absolute;left:0;top:0;height:4px;width:100%;background:linear-gradient(90deg,var(--a),var(--b),var(--gold))}
.rf-ico{width:42px;height:42px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,rgba(20,184,166,.18),rgba(245,158,11,.18));color:var(--a);margin-bottom:12px}
.rf-kpi-l{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);font-weight:700}
.rf-kpi-v{font-size:1.55rem;font-weight:800;letter-spacing:-.02em;margin-top:3px}
.rf-kpi-v small{font-size:.5em;color:var(--muted);font-weight:700}
.rf-delta{font-size:.74rem;font-weight:800;margin-top:6px}
.rf-delta.up{color:var(--ok)}.rf-delta.down{color:var(--bad)}.rf-delta.flat{color:var(--muted)}

.rf-two{display:grid;grid-template-columns:1.2fr 1fr;gap:18px}
@media (max-width:1000px){.rf-two{grid-template-columns:1fr}}
.rf-card{background:var(--card);border:1px solid var(--line);border-radius:24px;padding:22px}
.rf-card h3{margin:0 0 4px;font-size:1rem;font-weight:800}
.rf-card .sub{color:var(--muted);font-size:.78rem;margin-bottom:16px}
.rf-split{display:flex;height:46px;border-radius:16px;overflow:hidden;box-shadow:inset 0 0 0 1px var(--line)}
.rf-split>div{display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.82rem;min-width:46px;transition:width .8s}
.rf-legend{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:12px;font-size:.78rem;color:var(--muted)}
.rf-legend i{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px}

.rf-podium{display:flex;align-items:flex-end;justify-content:center;gap:14px;height:210px;margin-bottom:16px}
.rf-pod{flex:1;max-width:170px;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;text-align:center}
.rf-pod .box{width:100%;border-radius:18px 18px 8px 8px;display:flex;flex-direction:column;justify-content:flex-start;align-items:center;padding-top:10px;color:#fff;font-weight:800;box-shadow:0 16px 28px -18px rgba(0,0,0,.5)}
.rf-pod .nom{font-size:.78rem;font-weight:700;margin-bottom:6px;color:var(--ink);line-height:1.2}
.rf-pod .med{font-size:1.5rem}
.rf-pod .val{font-size:.8rem;margin-top:2px}
.rf-pod.p1 .box{background:linear-gradient(180deg,#fbbf24,#d97706)}
.rf-pod.p2 .box{background:linear-gradient(180deg,#94a3b8,#64748b)}
.rf-pod.p3 .box{background:linear-gradient(180deg,#d6a36a,#a16207)}

.rf-row{display:grid;grid-template-columns:28px 1fr auto;gap:10px;align-items:center;padding:8px 0;border-bottom:1px dashed var(--line);font-size:.82rem}
.rf-row:last-child{border:0}
.rf-rank{width:26px;height:26px;border-radius:9px;background:var(--soft);display:grid;place-items:center;font-weight:800;font-size:.75rem;color:var(--muted)}
.rf-track{height:7px;border-radius:99px;background:var(--soft);margin-top:5px;overflow:hidden}
.rf-track>i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,var(--a),var(--b));transition:width .8s}
.rf-val{font-weight:800;text-align:right}
.rf-val small{display:block;color:var(--muted);font-weight:600;font-size:.68rem}

.rf-donut{display:flex;align-items:center;gap:22px;flex-wrap:wrap}
.rf-donut svg{width:170px;height:170px;transform:rotate(-90deg)}
.rf-donut .ctr{position:absolute;inset:0;display:grid;place-items:center;text-align:center;font-weight:800}
.rf-heat{display:grid;grid-template-rows:repeat(7,14px);grid-auto-flow:column;grid-auto-columns:14px;gap:4px;overflow-x:auto;padding-bottom:6px}
.rf-cell{border-radius:4px;background:var(--soft);display:block}
.rf-cell.l1{background:#99f6e4}.rf-cell.l2{background:#2dd4bf}.rf-cell.l3{background:#0d9488}.rf-cell.l4{background:#115e59}.rf-cell.lf{opacity:.25}
.rf-table{width:100%;border-collapse:collapse;font-size:.82rem}
.rf-table th{text-align:left;color:var(--muted);font-size:.68rem;letter-spacing:.08em;text-transform:uppercase;padding:8px 10px}
.rf-table td{padding:9px 10px;border-top:1px solid var(--line)}
.rf-table td.n,.rf-table th.n{text-align:right}
.rf-scroll{max-height:380px;overflow:auto}
.rf-note{border-radius:18px;padding:14px 18px;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.35);font-size:.84rem;font-weight:600}

.rf-accounting{gap:18px}
.cpt-hero{padding:26px 30px}
.cpt-hero-head{display:flex;align-items:flex-start;justify-content:space-between;gap:22px}
.cpt-hero h1{margin:8px 0 6px;font-size:clamp(1.7rem,3vw,2.4rem);font-weight:850;letter-spacing:-.04em}
.cpt-hero p{margin:0;color:rgba(255,255,255,.78);font-size:.86rem}
.cpt-period-badge{display:inline-flex;align-items:center;gap:9px;padding:9px 13px;border:1px solid rgba(255,255,255,.25);border-radius:999px;background:rgba(255,255,255,.12);font-size:.78rem;font-weight:750}
.cpt-status-dot{width:8px;height:8px;border-radius:50%;background:#34d399;box-shadow:0 0 0 4px rgba(52,211,153,.16)}
.cpt-status-dot.is-inactive{background:#94a3b8;box-shadow:none}
.cpt-hero-filters{display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;margin-top:22px}
.cpt-hero-filters label{display:grid;gap:5px;color:rgba(255,255,255,.75);font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.cpt-hero-filters .rf-select{min-width:190px;background:rgba(255,255,255,.95);color:#0f172a}
.cpt-date-range{display:flex;align-items:center;gap:9px;padding:9px 12px;border:1px solid rgba(255,255,255,.2);border-radius:12px;color:#fff;font-size:.8rem;font-weight:700}
.cpt-hero-stat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.cpt-hero-stat{display:grid;gap:5px;padding:13px;border:1px solid rgba(255,255,255,.25);border-radius:16px;background:rgba(255,255,255,.14)}
.cpt-hero-stat>div{font-size:.64rem;letter-spacing:.1em;text-transform:uppercase;opacity:.85;font-weight:800}
.cpt-hero-stat>strong{font-size:1rem;font-weight:900;font-variant-numeric:tabular-nums}
.cpt-hero-stat small{font-size:.62rem;font-weight:700;opacity:.8}
.cpt-hero-empty{grid-column:1/-1;padding:20px;border:1px dashed rgba(255,255,255,.35);border-radius:16px;color:rgba(255,255,255,.82);font-size:.8rem}
.cpt-shortcuts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.cpt-shortcut{position:relative;display:flex;align-items:center;gap:12px;min-width:0;padding:16px;border:1px solid var(--line);border-radius:20px;background:var(--card);color:var(--ink);text-decoration:none;transition:transform .2s,border-color .2s,box-shadow .2s}
a.cpt-shortcut:hover{transform:translateY(-3px);border-color:rgba(20,184,166,.45);box-shadow:0 16px 30px -24px var(--a)}
.cpt-shortcut>span:nth-child(2){display:grid;gap:3px;min-width:0}
.cpt-shortcut strong{font-size:.82rem;font-weight:800}
.cpt-shortcut small{color:var(--muted);font-size:.69rem;line-height:1.45}
.cpt-shortcut-icon{display:grid;place-items:center;flex:0 0 40px;width:40px;height:40px;border-radius:13px;background:rgba(20,184,166,.12);color:var(--a)}
.cpt-shortcut-icon svg{width:20px;height:20px}
.cpt-shortcut-arrow{width:16px;height:16px;margin-left:auto;color:var(--muted)}
.cpt-shortcut-current{border-color:rgba(20,184,166,.42);background:linear-gradient(135deg,rgba(20,184,166,.09),var(--card))}
.cpt-current-label{position:absolute;right:12px;top:10px;color:var(--a);font-size:.6rem;font-weight:850;letter-spacing:.05em;text-transform:uppercase}
.cpt-report-nav{position:sticky;z-index:5;top:8px}
.cpt-section-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:16px}
.cpt-section-heading h2{margin:2px 0 0;font-size:1.12rem;font-weight:850;letter-spacing:-.02em}
.cpt-overline{color:var(--muted);font-size:.65rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
.cpt-section-caption{color:var(--muted);font-size:.75rem}
.cpt-statement-list{display:grid;gap:8px}
.cpt-statement-line{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:13px 15px;border:1px solid var(--line);border-radius:15px;background:var(--card);font-size:.82rem}
.cpt-statement-line strong{white-space:nowrap;font-size:.86rem;font-variant-numeric:tabular-nums}
.cpt-statement-line small,.cpt-metric-grid small{color:var(--muted);font-size:.67rem;font-weight:650}
.cpt-statement-line.is-total{border-color:rgba(99,102,241,.24);background:rgba(99,102,241,.08);font-weight:850}
.cpt-statement-line .is-negative,.cpt-metric-grid .is-negative strong{color:var(--bad)}
.cpt-report-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:11px}
.cpt-report-cards.cpt-scroll{max-height:590px;overflow:auto;padding:2px 5px 5px 2px;overscroll-behavior:contain}
.cpt-report-card{display:grid;gap:12px;min-width:0;padding:15px;border:1px solid var(--line);border-radius:18px;background:var(--card)}
.cpt-report-card.is-total{border-color:rgba(99,102,241,.28);background:linear-gradient(135deg,rgba(99,102,241,.1),var(--card))}
.cpt-report-card-title{font-size:.81rem;font-weight:800;line-height:1.4;overflow-wrap:anywhere}
.cpt-metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(95px,1fr));gap:10px}
.cpt-metric-grid>span{display:grid;gap:4px;min-width:0}
.cpt-metric-grid>span>strong{font-size:.75rem;font-weight:800;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
.cpt-metric-grid>span>small{font-size:.63rem;letter-spacing:.04em;text-transform:uppercase}
.cpt-single-metric{grid-template-columns:1fr}
.cpt-account-heading,.cpt-ledger-heading,.cpt-analytic-heading{display:flex;align-items:center;justify-content:space-between;gap:10px}
.cpt-account-number{display:inline-flex;align-items:center;padding:4px 8px;border-radius:8px;background:var(--soft);color:var(--muted);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.68rem;font-weight:800}
.cpt-date-tag,.cpt-type-tag{color:var(--muted);font-size:.69rem;font-weight:750}
.cpt-type-tag{padding:4px 8px;border-radius:999px;background:var(--soft)}
.cpt-analytic-grid{grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr))}
.cpt-metric-grid .is-positive strong{color:var(--ok)}
.cpt-empty-state{grid-column:1/-1;display:grid;justify-items:center;gap:9px;padding:38px 20px;border:1px dashed var(--line);border-radius:18px;color:var(--muted);text-align:center}
.cpt-empty-state svg{width:30px;height:30px;color:var(--a)}
.cpt-empty-state strong{color:var(--ink);font-size:.9rem}
.cpt-empty-state span{max-width:440px;font-size:.77rem;line-height:1.55}
.dark .cpt-account-number,.dark .cpt-type-tag{background:rgba(255,255,255,.08)}
@media(max-width:850px){.cpt-shortcuts{grid-template-columns:1fr 1fr}.cpt-shortcut-current{grid-column:1/-1}}
@media(max-width:600px){.cpt-hero{padding:21px}.cpt-hero-head{display:grid}.cpt-period-badge{justify-self:start}.cpt-hero-stat-grid{grid-template-columns:1fr}.cpt-shortcuts{grid-template-columns:1fr}.cpt-shortcut-current{grid-column:auto}.cpt-current-label{position:static;margin-left:auto}.cpt-report-nav{position:static}.cpt-statement-line{align-items:flex-start;flex-direction:column;gap:5px}.cpt-section-heading{align-items:flex-start;flex-direction:column}}

.cpt-asset-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.cpt-asset-kpis .rf-kpi{padding-top:17px}
.cpt-kpi-icon{display:grid;place-items:center;width:36px;height:36px;margin-bottom:10px;border-radius:12px;background:rgba(20,184,166,.12);color:var(--a)}
.cpt-kpi-icon svg{width:19px;height:19px}
.cpt-kpi-icon.is-warning{background:rgba(245,158,11,.14);color:#b45309}
.dark .cpt-kpi-icon.is-warning{color:#fbbf24}
.cpt-kpi-note,.cpt-category-count,.cpt-asset-category,.cpt-action-hint{color:var(--muted);font-size:.7rem;line-height:1.45}
.cpt-category-grid{grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr))}
.cpt-category-card{align-content:start}
.cpt-category-details{display:grid;gap:8px;padding:11px 12px;border-radius:13px;background:var(--soft)}
.cpt-category-details>span{display:flex;justify-content:space-between;gap:12px;font-size:.72rem}
.cpt-category-details small{color:var(--muted)}
.cpt-category-details strong{text-align:right;font-size:.7rem}
.cpt-type-tag.is-ready{background:rgba(16,185,129,.12);color:#047857}
.cpt-type-tag.is-pending{background:rgba(245,158,11,.15);color:#b45309}
.dark .cpt-type-tag.is-ready{color:#6ee7b7}
.dark .cpt-type-tag.is-pending{color:#fcd34d}
.cpt-asset-grid{grid-template-columns:repeat(auto-fit,minmax(min(100%,290px),1fr))}
.cpt-asset-card,.cpt-amort-card{align-content:start}
.cpt-asset-category{margin-top:-7px}
.cpt-amort-amount{font-size:1.2rem;font-weight:900;font-variant-numeric:tabular-nums}
.cpt-amort-amount small{color:var(--muted);font-size:.7rem;font-weight:750}
.cpt-action-hint{padding:9px 10px;border-radius:11px;background:var(--soft)}
@media(max-width:1000px){.cpt-asset-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.cpt-asset-kpis{grid-template-columns:1fr}}
</style>