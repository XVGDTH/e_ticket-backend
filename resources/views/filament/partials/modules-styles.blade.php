<style>
.md-grille{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:16px}
.md-carte{position:relative;overflow:hidden;border-radius:24px;padding:20px;background:var(--card);border:1px solid var(--line);transition:.25s}
.md-carte:hover{transform:translateY(-5px)}
.md-carte::before{content:"";position:absolute;inset:0 0 auto 0;height:5px;background:var(--c);opacity:.25;transition:.25s}
.md-carte.on{border-color:var(--c);box-shadow:0 26px 40px -28px var(--c)}
.md-carte.on::before{opacity:1}
.md-tete{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.md-ico{width:54px;height:54px;border-radius:18px;display:grid;place-items:center;color:var(--c);background:color-mix(in srgb,var(--c) 15%,transparent)}
.md-carte h4{margin:0 0 6px;font-size:1.05rem;font-weight:800}
.md-carte p{margin:0;font-size:.82rem;line-height:1.45;color:var(--muted)}
.md-pied{display:flex;gap:8px;align-items:center;margin-top:14px;font-size:.74rem;font-weight:800}
.md-etat{color:var(--muted)}
.md-carte.on .md-etat{color:var(--c)}
.md-bientot{padding:3px 10px;border-radius:99px;background:rgba(245,158,11,.16);color:#b45309}
.md-switch{width:54px;height:30px;border-radius:99px;border:0;background:#94a3b8;position:relative;cursor:pointer;transition:.25s;flex:none}
.md-switch i{position:absolute;top:3px;left:3px;width:24px;height:24px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.3);transition:.25s}
.md-switch.on{background:var(--c,#0d9488)}
.md-switch.on i{left:27px}
.md-switch:disabled{opacity:.6}
.md-ring{position:relative;width:150px;height:150px}
.md-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.md-ring .ctr{position:absolute;inset:0;display:grid;place-items:center;text-align:center;font-weight:800;font-size:1.9rem}
.md-ring .ctr small{display:block;font-size:.62rem;letter-spacing:.14em;text-transform:uppercase;opacity:.8}
.md-matrice th{white-space:nowrap}
.md-matrice td.n,.md-matrice th.n{text-align:center}
.md-tout{display:block;margin:4px auto 0;font-size:.62rem;font-weight:800;color:var(--a);background:none;border:0;cursor:pointer;text-decoration:underline}
</style>