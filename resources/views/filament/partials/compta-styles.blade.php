<style>
.sg{--ok:#10b981;--warn:#f59e0b;--bad:#ef4444}
.sg-mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
.sg-strong{font-weight:900;font-size:1.05rem}
.sg-bad{color:var(--bad);font-weight:700}
.sg-small{display:block;color:var(--muted);font-size:.7rem;margin-top:2px}
.sg-top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.sg-top-info{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sg-back{font-size:.85rem;font-weight:700;color:var(--a);text-decoration:none}
.sg-note{padding:12px 16px;border-radius:14px;font-size:.8rem;font-weight:600;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.3)}

.sg-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;align-items:end}
.sg-facture{margin-top:16px;padding-top:16px;border-top:1px dashed var(--line)}
.sg-field{display:grid;gap:5px}
.sg-field>span{font-size:.65rem;letter-spacing:.09em;text-transform:uppercase;color:var(--muted);font-weight:800}
.sg-wide{grid-column:span 2}
@media(max-width:700px){.sg-wide{grid-column:auto}}
.sg-check{display:flex;align-items:center;gap:8px;padding-bottom:9px}
.sg-check>span{text-transform:none;letter-spacing:0;font-size:.82rem;color:var(--ink)}
.sg-in{width:100%;border:1px solid var(--line);background:var(--card);color:var(--ink);padding:8px 10px;border-radius:10px;font-size:.82rem}
.sg-in:focus{outline:2px solid var(--b);outline-offset:0}
.sg-in:disabled{opacity:.65;background:var(--soft)}
.sg-in.n{text-align:right;font-variant-numeric:tabular-nums;font-weight:700}

.sg-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:16px}
.sg-table{width:100%;border-collapse:collapse;font-size:.8rem;min-width:1050px}
.sg-table thead th{background:#0f172a;color:#e2e8f0;text-align:left;padding:11px 10px;font-size:.65rem;letter-spacing:.09em;text-transform:uppercase;font-weight:800;white-space:nowrap}
.dark .sg-table thead th{background:#1e293b}
.sg-table td{padding:7px 8px;border-top:1px solid var(--line);vertical-align:middle}
.sg-table .n{text-align:right;font-variant-numeric:tabular-nums}
.sg-table tbody tr:nth-child(even){background:rgba(100,116,139,.05)}
.sg-table tr.is-valid td:first-child{box-shadow:inset 4px 0 0 var(--ok)}
.sg-table tr.is-next{background:rgba(245,158,11,.1)!important}
.sg-table tr.is-next td:first-child{box-shadow:inset 4px 0 0 var(--warn)}
.sg-table tfoot td{padding:12px 10px;border-top:2px solid var(--line);font-weight:900;background:var(--soft)}
.sg-num{display:inline-grid;place-items:center;width:26px;height:26px;border-radius:9px;background:var(--soft);font-weight:900;font-size:.72rem}
.sg-row-actions{white-space:nowrap;text-align:right}

.sg-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;font-size:.7rem;font-weight:800;white-space:normal}
.sg-chip.ok{background:rgba(16,185,129,.14);color:#047857}
.sg-chip.wait{background:rgba(100,116,139,.14);color:var(--muted)}
.sg-chip.next{background:rgba(245,158,11,.18);color:#b45309}
.sg-chip.bad{background:rgba(239,68,68,.14);color:#b91c1c}
.dark .sg-chip.ok{color:#6ee7b7}.dark .sg-chip.next{color:#fcd34d}.dark .sg-chip.bad{color:#fca5a5}
.sg-kmaa{display:inline-flex;padding:4px 12px;border-radius:999px;background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#451a03;font-weight:900;font-size:.78rem;letter-spacing:.06em}
.sg-btn-mini{border:1px solid var(--line);background:var(--card);color:var(--ink);padding:5px 11px;border-radius:9px;font-size:.72rem;font-weight:800;cursor:pointer}
.sg-btn-mini:hover{border-color:var(--b)}
.sg-btn-x{border:0;background:transparent;color:var(--bad);font-weight:900;cursor:pointer;padding:4px 8px}
.sg-footer{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-top:16px}
.sg-actions{display:flex;gap:8px;flex-wrap:wrap}

.sg-overlay{position:fixed;inset:0;z-index:60;background:rgba(15,23,42,.55);display:grid;place-items:center;padding:20px;overflow:auto}
.sg-modal{width:min(640px,100%);max-height:92vh;overflow:auto;background:var(--card);color:var(--ink);border-radius:22px;border:1px solid var(--line);box-shadow:0 40px 80px -30px rgba(0,0,0,.6)}
.dark .sg-modal{background:#0f172a}
.sg-modal-head{display:flex;justify-content:space-between;gap:16px;padding:20px 22px;border-bottom:1px solid var(--line);background:linear-gradient(135deg,rgba(20,184,166,.12),transparent)}
.sg-modal-head h2{margin:4px 0 0;font-size:1.05rem;font-weight:850}
.sg-modal-amount{text-align:right}
.sg-modal-amount small{display:block;font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);font-weight:800}
.sg-modal-amount strong{font-size:1.2rem;font-weight:900;font-variant-numeric:tabular-nums;white-space:nowrap}
.sg-modal-body{padding:20px 22px;display:grid;gap:12px}
.sg-modal-body h3{margin:0;font-size:.9rem;font-weight:850}
.sg-modal-foot{display:flex;justify-content:flex-end;gap:8px;padding:16px 22px;border-top:1px solid var(--line)}
.sg-imp-row{display:grid;grid-template-columns:1fr 150px auto auto;gap:8px;align-items:center}
.sg-imp-total{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.sg-hr{border:0;border-top:1px dashed var(--line);margin:6px 0}
</style>