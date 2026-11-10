<?php

namespace App\Services\Finance;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/** Export d'un rapport en PDF, Excel ou CSV à partir des mêmes rubriques. */
class ExportFinancier
{
    private const MAX_LIGNES_PDF = 1000;

    /**
     * @param  array<string,string>  $meta  libellé => valeur (période, périmètre…)
     * @param  array<int,array{0:string,1:string}>  $kpis  [libellé, valeur]
     * @param  array<int,array{titre:string, entetes:array, lignes:iterable}>  $sections
     */
    public static function exporter(string $format, string $fichier, string $titre, array $meta, array $kpis, array $sections): Response
    {
        return match ($format) {
            'pdf' => self::pdf($fichier, $titre, $meta, $kpis, $sections),
            'xlsx' => self::xlsx($fichier, $titre, $meta, $kpis, $sections),
            default => self::csv($fichier, $titre, $meta, $kpis, $sections),
        };
    }

    private static function csv(string $fichier, string $titre, array $meta, array $kpis, array $sections): Response
    {
        return response()->streamDownload(function () use ($titre, $meta, $kpis, $sections) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF"); // pour qu'Excel lise bien les accents

            $ligne = fn (array $valeurs) => fputcsv($h, $valeurs, ';', '"', '\\');

            $ligne([$titre]);
            foreach ($meta as $cle => $valeur) {
                $ligne([$cle, $valeur]);
            }
            $ligne([]);
            foreach ($kpis as [$cle, $valeur]) {
                $ligne([$cle, $valeur]);
            }

            foreach ($sections as $section) {
                $ligne([]);
                $ligne([$section['titre']]);
                $ligne($section['entetes']);
                foreach ($section['lignes'] as $l) {
                    $ligne($l);
                }
            }

            fclose($h);
        }, $fichier.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function xlsx(string $fichier, string $titre, array $meta, array $kpis, array $sections): Response
    {
        $chemin = tempnam(sys_get_temp_dir(), 'fin');

        $options = new Options;
        $options->DEFAULT_COLUMN_WIDTH = 24;

        $writer = new Writer($options);
        $writer->openToFile($chemin);

        $gras = (new Style)->setFontBold();
        $entete = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('0F766E');

        $writer->getCurrentSheet()->setName('Résumé');
        $writer->addRow(Row::fromValues([$titre], $gras));
        foreach ($meta as $cle => $valeur) {
            $writer->addRow(Row::fromValues([$cle, $valeur]));
        }
        $writer->addRow(Row::fromValues(['']));
        foreach ($kpis as [$cle, $valeur]) {
            $writer->addRow(Row::fromValues([$cle, $valeur], $gras));
        }

        foreach ($sections as $section) {
            $writer->addNewSheetAndMakeItCurrent();
            $writer->getCurrentSheet()->setName(
                mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $section['titre']), 0, 28)
            );
            $writer->addRow(Row::fromValues($section['entetes'], $entete));

            foreach ($section['lignes'] as $l) {
                $writer->addRow(Row::fromValues($l));
            }
        }

        $writer->close();

        return response()->download($chemin, $fichier.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private static function pdf(string $fichier, string $titre, array $meta, array $kpis, array $sections): Response
    {
        $sections = array_map(function (array $section) {
            $lignes = [];
            $tronque = false;

            foreach ($section['lignes'] as $l) {
                if (count($lignes) >= self::MAX_LIGNES_PDF) {
                    $tronque = true;
                    break;
                }
                $lignes[] = $l;
            }

            return [...$section, 'lignes' => $lignes, 'tronque' => $tronque];
        }, $sections);

        $pdf = Pdf::loadView('pdf.rapport-financier', compact('titre', 'meta', 'kpis', 'sections'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print ($pdf->output()), $fichier.'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
