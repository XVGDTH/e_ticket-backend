<style>
    .gx { display:flex; flex-direction:column; gap:1.1rem; width:100%; min-width:0; box-sizing:border-box; }
    .gx-filtres { display:flex; flex-wrap:wrap; align-items:center; gap:.6rem; }
    .gx-filtre { min-width:170px; }
    .gx-note { font-size:.85rem; opacity:.7; }
    .gx-vide { padding:1.5rem; text-align:center; opacity:.75; border:1px dashed rgba(128,128,128,.4); border-radius:1rem; }
    .gx-titre { font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; opacity:.6; margin-bottom:.5rem; }
    .gx-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:.8rem; }
    .gx-kpi { color:#fff; border-radius:1rem; padding:1rem 1.2rem; box-shadow:0 12px 28px -14px rgba(0,0,0,.55); }
    .gx-kpi-t { font-size:.72rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; opacity:.92; }
    .gx-kpi-v { font-size:1.7rem; font-weight:800; line-height:1.1; margin:.35rem 0 .15rem; }
    .gx-kpi-s { font-size:.8rem; opacity:.92; }
    .gx-bleu { background:linear-gradient(135deg, #0ea5e9, #6366f1); }
    .gx-ambre { background:linear-gradient(135deg, #f59e0b, #ea580c); }
    .gx-vert { background:linear-gradient(135deg, #22c55e, #0d9488); }
    .gx-rouge { background:linear-gradient(135deg, #f97316, #dc2626); }
    .gx-gris { background:linear-gradient(135deg, #64748b, #334155); }
    .gx-pal { display:flex; flex-direction:column; gap:.4rem; padding:1rem 1.2rem; border-radius:1rem; border:1px solid rgba(128,128,128,.3); }
    .gx-pal-l { display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; font-size:.9rem; }
    .gx-pal-l b { font-variant-numeric:tabular-nums; }
    .gx-moins { font-size:.75rem; opacity:.6; margin-top:.2rem; }
    .gx-bar { height:.7rem; border-radius:999px; background:rgba(128,128,128,.18); overflow:hidden; }
    .gx-bar i { display:block; height:100%; border-radius:999px; }
    .gx-grille { display:grid; grid-template-columns:repeat(auto-fill, minmax(290px, 1fr)); gap:.8rem; }
    .gx-carte { --c:#6b7280; display:flex; flex-direction:column; gap:.5rem; padding:.9rem 1rem; border-radius:1rem; border:1px solid rgba(128,128,128,.3); border-left:5px solid var(--c); }
    .gx-h { display:flex; justify-content:space-between; align-items:flex-start; gap:.5rem; }
    .gx-n { font-weight:700; line-height:1.25; }
    .gx-pastille { flex:none; padding:.1rem .55rem; font-size:.68rem; font-weight:700; color:#fff; border-radius:999px; background:var(--c); }
    .gx-res { font-size:1.45rem; font-weight:800; color:var(--c); }
    .gx-res small { font-size:.75rem; font-weight:600; opacity:.8; }
    .gx-mini { display:grid; grid-template-columns:repeat(3, 1fr); gap:.4rem; font-size:.8rem; font-weight:600; }
    .gx-mini div { display:flex; flex-direction:column; }
    .gx-mini small { font-weight:400; opacity:.6; }
    .gx-s { font-size:.8rem; opacity:.78; line-height:1.4; }
    .gx-com { font-size:.82rem; padding:.45rem .7rem; border-radius:.7rem; background:rgba(128,128,128,.12); }
    .gx-btns { display:flex; flex-wrap:wrap; gap:.5rem; }
    .gx-btn { padding:.2rem .75rem; font-size:.78rem; font-weight:600; cursor:pointer; color:inherit; background:transparent; border-radius:999px; border:1px solid rgba(128,128,128,.4); }
    .gx-btn:hover { border-color:#6366f1; }
    .gx-btn-v:hover { border-color:#16a34a; color:#16a34a; }
    .gx-btn-r:hover { border-color:#dc2626; color:#dc2626; }
    .gx-det { display:flex; flex-direction:column; gap:.15rem; padding-top:.5rem; font-size:.8rem; border-top:1px dashed rgba(128,128,128,.35); }
    .gx-det div { display:grid; grid-template-columns:1fr auto auto auto; gap:.6rem; }
    .gx-det .e { font-weight:700; }
    .gx-det .g { font-size:.68rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; opacity:.6; }
    .gx-alerte { --c:#6b7280; padding:.65rem .9rem; border-radius:.8rem; border:1px solid color-mix(in srgb, var(--c) 45%, transparent); background:color-mix(in srgb, var(--c) 10%, transparent); font-size:.85rem; }
    .gx-alertes { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:.6rem; }
    [x-cloak] { display:none !important; }
</style>