<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_journal_parametres', function (Blueprint $table) {
            $table->id();
            $table->string('evenement', 20);
            $table->string('entite', 80);
            $table->unsignedBigInteger('entite_id')->nullable();
            $table->unsignedBigInteger('compagnie_id')->nullable();
            $table->string('compagnie_nom')->nullable();
            $table->unsignedBigInteger('acteur_id')->nullable();
            $table->string('acteur_nom');
            $table->unsignedBigInteger('cible_utilisateur_id')->nullable();
            $table->string('cible_utilisateur_nom')->nullable();
            $table->json('valeurs');
            $table->timestamp('enregistre_le')->useCurrent();

            $table->index(['compagnie_id', 'enregistre_le', 'id'], 'ci_journal_comp_date_idx');
            $table->index(['entite', 'entite_id'], 'ci_journal_entity_idx');
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER ci_journal_no_update BEFORE UPDATE ON ci_journal_parametres FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit journal is append-only'");
            DB::unprepared("CREATE TRIGGER ci_journal_no_delete BEFORE DELETE ON ci_journal_parametres FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit journal is append-only'");

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER ci_journal_no_update BEFORE UPDATE ON ci_journal_parametres BEGIN SELECT RAISE(ABORT, 'Audit journal is append-only'); END");
            DB::unprepared("CREATE TRIGGER ci_journal_no_delete BEFORE DELETE ON ci_journal_parametres BEGIN SELECT RAISE(ABORT, 'Audit journal is append-only'); END");

            return;
        }

        throw new RuntimeException("Append-only audit triggers are not configured for the [{$driver}] database driver.");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS ci_journal_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS ci_journal_no_delete');

        Schema::dropIfExists('ci_journal_parametres');
    }
};
