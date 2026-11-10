<?php

namespace App\Console\Commands;

use App\Services\Marketing\WhatsAppCloud;
use Illuminate\Console\Command;

class WhatsappModeleOtp extends Command
{
    protected $signature = 'whatsapp:modele-otp';

    protected $description = 'Crée (ou contrôle) le modèle WhatsApp qui envoie les codes de vérification';

    public function handle(): int
    {
        $r = WhatsAppCloud::modeleOtp();

        if (! $r['ok']) {
            $this->error($r['erreur']);

            return self::FAILURE;
        }

        $this->info(($r['cree'] ? 'Modèle créé et envoyé à Meta.' : 'Le modèle existe déjà.').' Statut : '.$r['statut']);
        $this->line('Les codes ne peuvent partir que lorsque le statut est APPROVED.');

        return self::SUCCESS;
    }
}
