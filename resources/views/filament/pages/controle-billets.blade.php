<x-filament-panels::page>
    @assets
        <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    @endassets

    <style>
        .cb-grille{display:grid;grid-template-columns:minmax(300px,1fr) minmax(320px,1.2fr);gap:18px}
        @media (max-width:900px){.cb-grille{grid-template-columns:1fr}}
        .cb-carte{background:var(--cb-card,#fff);border:1px solid rgba(15,23,42,.1);border-radius:22px;padding:20px}
        .dark .cb-carte{--cb-card:rgba(30,41,59,.72);border-color:rgba(255,255,255,.1)}
        .cb-carte h3{margin:0 0 4px;font-size:1.05rem;font-weight:800}
        .cb-sous{font-size:.8rem;opacity:.7;margin:0 0 14px}
        .cb-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 18px;border-radius:12px;border:0;cursor:pointer;color:#fff;font-weight:800;font-size:.9rem;background:linear-gradient(135deg,#0f766e,#14b8a6)}
        .cb-btn.gris{background:#64748b}
        .cb-btn.vert{background:linear-gradient(135deg,#047857,#10b981);width:100%;margin-top:14px;padding:14px;font-size:1rem}
        .cb-btn:disabled{opacity:.6}
        .cb-boutons{margin:12px 0}
        .cb-saisie{display:flex;gap:8px;margin-top:6px}
        .cb-saisie input{flex:1;padding:10px 14px;border-radius:12px;border:1px solid rgba(100,116,139,.4);background:transparent;color:inherit;font-family:monospace}
        .cb-vide{display:grid;place-items:center;min-height:220px;opacity:.55;text-align:center}
        .cb-verdict{display:flex;gap:16px;align-items:center;border-radius:18px;padding:18px 20px;color:#fff;box-shadow:0 18px 30px -18px rgba(0,0,0,.45)}
        .cb-icone{font-size:2.4rem;line-height:1}
        .cb-titre{font-size:1.35rem;font-weight:900;letter-spacing:-.01em}
        .cb-msg{font-size:.88rem;opacity:.95;margin-top:4px;line-height:1.35}
        .cb-alerte{margin-top:12px;padding:10px 14px;border-radius:14px;background:rgba(245,158,11,.14);border:1px solid rgba(245,158,11,.5);font-size:.82rem;font-weight:700}
        .cb-lignes{margin-top:14px;display:grid;gap:0}
        .cb-ligne{display:flex;justify-content:space-between;gap:14px;padding:8px 0;border-bottom:1px dashed rgba(100,116,139,.35);font-size:.86rem}
        .cb-ligne span:first-child{opacity:.65}
        .cb-ligne span:last-child{font-weight:700;text-align:right}
        .cb-noms{margin-top:14px}
        .cb-noms strong{font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;opacity:.65}
        .cb-nom{display:flex;gap:10px;align-items:center;padding:10px 12px;margin-top:6px;border-radius:12px;background:rgba(20,184,166,.1);font-weight:800}
    </style>

    @php
        $r = $this->resultat;
        $couleurs = [
            'ok' => ['#065f46', '#10b981', '✅'],
            'warn' => ['#92400e', '#f59e0b', '⚠️'],
            'danger' => ['#991b1b', '#ef4444', '⛔'],
        ];
    @endphp

    <div
        x-data="{
            actif: false, scanner: null, dernier: '', quand: 0,
            async ouvrir() {
                if (! window.Html5Qrcode) { alert('Le lecteur se charge encore, réessayez dans un instant.'); return; }
                this.actif = true;
                await this.$nextTick();
                this.scanner = new Html5Qrcode('cb-lecteur');
                try {
                    await this.scanner.start(
                        { facingMode: 'environment' },
                        { fps: 10, qrbox: { width: 240, height: 240 } },
                        (texte) => {
                            const t = Date.now();
                            if (texte === this.dernier && t - this.quand < 4000) { return; }
                            this.dernier = texte; this.quand = t;
                            this.$wire.verifier(texte);
                        },
                        () => {}
                    );
                } catch (e) {
                    this.actif = false;
                    alert('Caméra indisponible : autorisez-la dans le navigateur (le site doit être en HTTPS, sauf en local).');
                }
            },
            async fermer() {
                try { await this.scanner?.stop(); this.scanner?.clear(); } catch (e) {}
                this.scanner = null; this.actif = false;
            }
        }"
        x-on:billet-verifie.window="navigator.vibrate && navigator.vibrate($event.detail.niveau === 'ok' ? 80 : [120, 80, 120])"
    >
        <div class="cb-grille">
            <div class="cb-carte">
                <h3>Lire un billet</h3>
                <p class="cb-sous">Scannez le code QR du client, ou saisissez la référence (un lecteur USB fonctionne aussi).</p>

                <div wire:ignore>
                    <div id="cb-lecteur" x-show="actif" x-cloak style="width:100%;border-radius:16px;overflow:hidden"></div>
                </div>

                <div class="cb-boutons">
                    <button type="button" class="cb-btn" x-show="! actif" x-on:click="ouvrir()">📷 Ouvrir la caméra</button>
                    <button type="button" class="cb-btn gris" x-show="actif" x-cloak x-on:click="fermer()">Fermer la caméra</button>
                </div>

                <form wire:submit="verifierSaisie" class="cb-saisie">
                    <input type="text" wire:model="saisie" placeholder="Référence ou contenu du code" autocomplete="off" autofocus>
                    <button type="submit" class="cb-btn">Vérifier</button>
                </form>
            </div>

            <div class="cb-carte">
                @if (! $r)
                    <div class="cb-vide">Le résultat du contrôle s'affichera ici,<br>avec toutes les informations du billet.</div>
                @else
                    @php [$fond, $vif, $icone] = $couleurs[$r['niveau']] ?? $couleurs['danger']; @endphp

                    <div class="cb-verdict" style="background: linear-gradient(135deg, {{ $fond }}, {{ $vif }})">
                        <div class="cb-icone">{{ $icone }}</div>
                        <div>
                            <div class="cb-titre">{{ $r['titre'] }}</div>
                            <div class="cb-msg">{{ $r['message'] }}</div>
                        </div>
                    </div>

                    @if ($r['mode'] === 'manuel' && $r['billet_id'])
                        <div class="cb-alerte">Saisie manuelle : le code QR n'a pas été lu. Demandez une pièce d'identité et comparez les noms.</div>
                    @endif

                    @if (! empty($r['voyageurs']))
                        <div class="cb-noms">
                            <strong>Voyageurs à contrôler</strong>
                            @foreach ($r['voyageurs'] as $nom)
                                <div class="cb-nom">🪪 {{ $nom }}</div>
                            @endforeach
                        </div>
                    @endif

                    @if (! empty($r['lignes']))
                        <div class="cb-lignes">
                            @foreach ($r['lignes'] as [$libelle, $valeur])
                                <div class="cb-ligne"><span>{{ $libelle }}</span><span>{{ $valeur }}</span></div>
                            @endforeach
                        </div>
                    @endif

                    @if ($r['peut_embarquer'])
                        <button type="button" class="cb-btn vert" wire:click="embarquer({{ $r['billet_id'] }})" wire:loading.attr="disabled">
                            ✅ Embarquer ce voyageur
                        </button>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div style="margin-top: 18px">
        {{ $this->table }}
    </div>
</x-filament-panels::page>