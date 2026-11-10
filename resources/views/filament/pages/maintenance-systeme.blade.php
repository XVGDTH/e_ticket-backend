<x-filament-panels::page>
    <div class="ms-dashboard">
        <style>
            .ms-dashboard {
                --ms-ink: #0f172a;
                --ms-muted: #64748b;
                --ms-line: rgba(15, 23, 42, .1);
                --ms-card: #fff;
                display: grid;
                gap: 1.15rem;
                color: var(--ms-ink);
            }
            .dark .ms-dashboard {
                --ms-ink: #f1f5f9;
                --ms-muted: #94a3b8;
                --ms-line: rgba(255, 255, 255, .1);
                --ms-card: rgba(30, 41, 59, .8);
            }
            .ms-hero {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1.25rem;
                padding: clamp(1.3rem, 3vw, 2rem);
                border-radius: 1.5rem;
                color: #fff;
                background: radial-gradient(580px 300px at 90% 0%, rgba(45, 212, 191, .34), transparent 60%),
                    linear-gradient(130deg, #0f172a, #134e4a 72%, #0f766e);
                box-shadow: 0 24px 55px -38px rgba(15, 118, 110, .9);
            }
            .ms-eyebrow { font-size: .68rem; font-weight: 850; letter-spacing: .15em; text-transform: uppercase; opacity: .75; }
            .ms-hero h2 { margin: .4rem 0; font-size: clamp(1.45rem, 3vw, 2rem); font-weight: 850; letter-spacing: -.03em; }
            .ms-hero p { max-width: 46rem; margin: 0; font-size: .88rem; line-height: 1.55; opacity: .82; }
            .ms-health {
                display: inline-flex;
                flex: none;
                align-items: center;
                gap: .55rem;
                padding: .55rem .85rem;
                border: 1px solid rgba(255,255,255,.25);
                border-radius: 999px;
                background: rgba(255,255,255,.12);
                font-size: .8rem;
                font-weight: 800;
            }
            .ms-health-dot { width: .55rem; height: .55rem; border-radius: 50%; background: #5eead4; box-shadow: 0 0 0 4px rgba(94,234,212,.16); }
            .ms-health.is-warning .ms-health-dot { background: #fbbf24; box-shadow: 0 0 0 4px rgba(251,191,36,.16); }
            .ms-health.is-error .ms-health-dot { background: #f87171; box-shadow: 0 0 0 4px rgba(248,113,113,.16); }
            .ms-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
            .ms-panel { min-width: 0; padding: 1.15rem; border: 1px solid var(--ms-line); border-radius: 1.25rem; background: var(--ms-card); }
            .ms-panel-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; margin-bottom: .95rem; }
            .ms-panel-title { margin: 0; font-size: 1rem; font-weight: 850; }
            .ms-panel-subtitle { margin: .2rem 0 0; color: var(--ms-muted); font-size: .75rem; }
            .ms-count { display: grid; width: 2rem; height: 2rem; flex: none; place-items: center; border-radius: .7rem; color: #0f766e; background: rgba(20,184,166,.12); }
            .ms-count svg { width: 1.1rem; height: 1.1rem; }
            .ms-runtime { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; }
            .ms-runtime-card { min-width: 0; padding: .8rem; border: 1px solid var(--ms-line); border-radius: .9rem; }
            .ms-label { color: var(--ms-muted); font-size: .66rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
            .ms-value { margin-top: .22rem; overflow-wrap: anywhere; font-size: .88rem; font-weight: 800; }
            .ms-list { display: grid; gap: .6rem; }
            .ms-check { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: start; gap: .75rem; padding: .8rem; border: 1px solid var(--ms-line); border-radius: .9rem; }
            .ms-check-icon { display: grid; width: 2rem; height: 2rem; place-items: center; border-radius: .65rem; color: #047857; background: rgba(16,185,129,.12); }
            .ms-check-icon svg { width: 1rem; height: 1rem; }
            .ms-check.is-warning .ms-check-icon { color: #b45309; background: rgba(245,158,11,.14); }
            .ms-check.is-error .ms-check-icon { color: #b91c1c; background: rgba(239,68,68,.12); }
            .ms-check-title { margin: 0; font-size: .84rem; font-weight: 850; }
            .ms-check-detail { margin: .2rem 0 0; color: var(--ms-muted); font-size: .75rem; line-height: 1.45; overflow-wrap: anywhere; }
            .ms-badge { padding: .2rem .55rem; border-radius: 999px; color: #047857; background: rgba(16,185,129,.12); font-size: .65rem; font-weight: 850; white-space: nowrap; }
            .ms-badge.is-warning { color: #b45309; background: rgba(245,158,11,.14); }
            .ms-badge.is-error { color: #b91c1c; background: rgba(239,68,68,.12); }
            .ms-safety { display: flex; align-items: flex-start; gap: .65rem; padding: .9rem 1rem; border: 1px solid rgba(59,130,246,.2); border-radius: 1rem; color: #1d4ed8; background: rgba(59,130,246,.07); font-size: .76rem; line-height: 1.5; }
            .dark .ms-safety { color: #93c5fd; }
            .ms-safety svg { width: 1rem; height: 1rem; flex: none; margin-top: .05rem; }
            .ms-extra-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
            .ms-wide { grid-column: 1 / -1; }
            .ms-items { display: grid; gap: .55rem; max-height: 28rem; overflow: auto; }
            .ms-item { display: grid; gap: .45rem; padding: .8rem; border: 1px solid var(--ms-line); border-radius: .85rem; }
            .ms-item-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .7rem; }
            .ms-item-title { margin: 0; font-size: .79rem; font-weight: 850; overflow-wrap: anywhere; }
            .ms-item-meta { color: var(--ms-muted); font-size: .68rem; }
            .ms-item-copy { margin: 0; color: var(--ms-muted); font-size: .74rem; line-height: 1.45; overflow-wrap: anywhere; white-space: pre-wrap; }
            .ms-item-action { padding: .32rem .65rem; border: 1px solid var(--ms-line); border-radius: 999px; color: var(--ms-ink); background: transparent; font-size: .7rem; font-weight: 800; cursor: pointer; }
            .ms-migration-list { display: grid; gap: .4rem; max-height: 20rem; overflow: auto; }
            .ms-migration { padding: .55rem .7rem; border: 1px solid var(--ms-line); border-radius: .7rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .69rem; overflow-wrap: anywhere; }
            .ms-bypass { display: inline-flex; align-items: center; gap: .35rem; padding: .5rem .75rem; border-radius: .7rem; color: #fff; background: #0f766e; font-size: .74rem; font-weight: 800; text-decoration: none; }
            .ms-empty { padding: 1.2rem; border: 1px dashed var(--ms-line); border-radius: .85rem; color: var(--ms-muted); font-size: .76rem; text-align: center; }
            @media (max-width: 800px) { .ms-grid { grid-template-columns: 1fr; } }
            @media (max-width: 560px) {
                .ms-hero { align-items: flex-start; flex-direction: column; }
                .ms-runtime { grid-template-columns: 1fr 1fr; gap: .5rem; }
                .ms-panel { padding: .85rem; }
                .ms-check { grid-template-columns: auto minmax(0, 1fr); }
                .ms-badge { grid-column: 2; justify-self: start; }
                .ms-extra-grid { grid-template-columns: 1fr; }
                .ms-wide { grid-column: auto; }
            }
        </style>

        @php
            $statut = $diagnostic['resume']['statut'];
            $libelleStatut = ['ok' => 'Système opérationnel', 'warning' => 'Surveillance requise', 'error' => 'Action requise'][$statut];
            $labelsStatut = ['ok' => 'OK', 'warning' => 'À vérifier', 'error' => 'En erreur'];
        @endphp

        <section class="ms-hero">
            <div>
                <div class="ms-eyebrow">Centre de supervision · {{ config('app.env') }} · Vérifié à {{ now()->format('d/m/Y H:i:s') }}</div>
                <h2>Maintenance et santé de la plateforme</h2>
                <p>Un aperçu immédiat des composants critiques, des migrations et des travaux asynchrones. Les contrôles affichés sont non destructifs.</p>
            </div>
            <div class="ms-health is-{{ $statut }}">
                <span class="ms-health-dot"></span>
                {{ $libelleStatut }}
            </div>
        </section>

        <div class="ms-grid">
            <section class="ms-panel">
                <div class="ms-panel-head">
                    <div>
                        <h3 class="ms-panel-title">Environnement d’exécution</h3>
                        <p class="ms-panel-subtitle">Version, sécurité et état du déploiement</p>
                    </div>
                    <span class="ms-count"><x-filament::icon icon="heroicon-o-server-stack" /></span>
                </div>
                <div class="ms-runtime">
                    @foreach ($diagnostic['runtime'] as $element)
                        <div class="ms-runtime-card">
                            <div class="ms-label">{{ $element['label'] }}</div>
                            <div class="ms-value">{{ $element['valeur'] }}</div>
                            @if ($element['statut'] !== 'ok')
                                <span class="ms-badge is-{{ $element['statut'] }}">{{ $labelsStatut[$element['statut']] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ms-panel">
                <div class="ms-panel-head">
                    <div>
                        <h3 class="ms-panel-title">Contrôles de santé</h3>
                        <p class="ms-panel-subtitle">Connectivité et éléments à surveiller</p>
                    </div>
                    <span class="ms-count"><x-filament::icon icon="heroicon-o-heart" /></span>
                </div>
                <div class="ms-list">
                    @foreach ($diagnostic['controles'] as $controle)
                        <article class="ms-check is-{{ $controle['statut'] }}">
                            <span class="ms-check-icon">
                                <x-filament::icon :icon="$controle['statut'] === 'ok' ? 'heroicon-o-check' : ($controle['statut'] === 'warning' ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-x-mark')" />
                            </span>
                            <div>
                                <h4 class="ms-check-title">{{ $controle['label'] }}</h4>
                                <p class="ms-check-detail">{{ $controle['detail'] }}</p>
                            </div>
                            <span class="ms-badge is-{{ $controle['statut'] }}">{{ $labelsStatut[$controle['statut']] }}</span>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="ms-extra-grid">
            <section class="ms-panel">
                <div class="ms-panel-head">
                    <div>
                        <h3 class="ms-panel-title">Migrations en attente</h3>
                        <p class="ms-panel-subtitle">L’application est précédée d’une sauvegarde automatique.</p>
                    </div>
                    <span class="ms-count"><x-filament::icon icon="heroicon-o-arrow-up-tray" /></span>
                </div>
                @if ($migrationsEnAttente === [])
                    <div class="ms-empty">Aucune migration en attente.</div>
                @else
                    <div class="ms-migration-list">
                        @foreach ($migrationsEnAttente as $migration)
                            <div class="ms-migration">{{ $migration }}</div>
                        @endforeach
                    </div>
                    <p class="ms-item-meta">{{ count($migrationsEnAttente) }} migration(s) détectée(s). Utilisez « Sauvegarder et appliquer » après revue.</p>
                @endif
            </section>

            <section class="ms-panel">
                <div class="ms-panel-head">
                    <div>
                        <h3 class="ms-panel-title">Sauvegardes privées</h3>
                        <p class="ms-panel-subtitle">Disque local privé · conservation 30 jours</p>
                    </div>
                    <span class="ms-count"><x-filament::icon icon="heroicon-o-circle-stack" /></span>
                </div>
                @if ($sauvegardes === [])
                    <div class="ms-empty">Aucune sauvegarde disponible. La création nécessite le client mysqldump sur le serveur.</div>
                @else
                    <div class="ms-items">
                        @foreach ($sauvegardes as $sauvegarde)
                            <article class="ms-item">
                                <div class="ms-item-head">
                                    <h4 class="ms-item-title">{{ $sauvegarde['filename'] }}</h4>
                                    <span class="ms-badge">{{ number_format($sauvegarde['size'] / 1024 / 1024, 2, ',', ' ') }} Mo</span>
                                </div>
                                <div class="ms-item-meta">{{ $sauvegarde['date'] }} · privée, non téléchargeable depuis cette page</div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="ms-panel">
                <div class="ms-panel-head">
                    <div>
                        <h3 class="ms-panel-title">Travaux échoués</h3>
                        <p class="ms-panel-subtitle">Aucun contenu de payload n’est exposé. Chaque relance est ciblée.</p>
                    </div>
                    <span class="ms-count"><x-filament::icon icon="heroicon-o-queue-list" /></span>
                </div>
                @if ($travauxEchoues === [])
                    <div class="ms-empty">Aucun travail échoué n’est enregistré.</div>
                @else
                    <div class="ms-items">
                        @foreach ($travauxEchoues as $travail)
                            <article class="ms-item">
                                <div class="ms-item-head">
                                    <h4 class="ms-item-title">File {{ $travail['queue'] }}</h4>
                                    <button
                                        type="button"
                                        class="ms-item-action"
                                        wire:click="relancerTravail('{{ $travail['uuid'] }}')"
                                        wire:confirm="Relancer ce travail échoué ? Il pourra répéter les effets de son exécution précédente."
                                        wire:loading.attr="disabled"
                                    >Relancer</button>
                                </div>
                                <div class="ms-item-meta">{{ $travail['failed_at'] }} · {{ $travail['uuid'] }}</div>
                                <p class="ms-item-copy">{{ $travail['exception'] }}</p>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="ms-panel">
                <div class="ms-panel-head">
                    <div>
                        <h3 class="ms-panel-title">Mode maintenance</h3>
                        <p class="ms-panel-subtitle">{{ $modeMaintenance ? 'Le site est en maintenance.' : 'Le site est accessible normalement.' }}</p>
                    </div>
                    <span class="ms-count"><x-filament::icon icon="heroicon-o-wrench-screwdriver" /></span>
                </div>
                @if ($modeMaintenance && filled($this->maintenanceBypassUrl))
                    <p class="ms-item-copy">Ouvrez ce lien privé une fois dans ce navigateur pour rétablir l’accès du super administrateur au panneau :</p>
                    <a class="ms-bypass" href="{{ $this->maintenanceBypassUrl }}">Rétablir mon accès administrateur</a>
                @elseif ($modeMaintenance)
                    <div class="ms-empty">Pour revenir ici, ouvrez le lien de contournement créé au moment de l’activation ou désactivez le mode maintenance depuis la console serveur.</div>
                @else
                    <p class="ms-item-copy">Les visiteurs seront temporairement redirigés vers la page de maintenance. L’activation génère un lien de contournement privé.</p>
                @endif
            </section>

            <section class="ms-panel ms-wide">
                <div class="ms-panel-head">
                    <div>
                        <h3 class="ms-panel-title">Journaux récents</h3>
                        <p class="ms-panel-subtitle">100 lignes au maximum · secrets et jetons usuels masqués</p>
                    </div>
                    <span class="ms-count"><x-filament::icon icon="heroicon-o-document-text" /></span>
                </div>
                @if ($journaux === [])
                    <div class="ms-empty">Aucune ligne de journal n’est disponible.</div>
                @else
                    <div class="ms-items">
                        @foreach ($journaux as $ligne)
                            <article class="ms-item">
                                <div class="ms-item-meta">{{ $ligne['filename'] }}</div>
                                <p class="ms-item-copy">{{ $ligne['message'] }}</p>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <aside class="ms-safety">
            <x-filament::icon icon="heroicon-o-shield-check" />
            <span>
                Cet espace est strictement réservé au super administrateur. Les actions disponibles sont limitées à la purge des caches Laravel et au signal de redémarrage des workers. Les migrations, modifications de fichiers et commandes libres restent hors de l’interface web.
            </span>
        </aside>
    </div>
</x-filament-panels::page>
