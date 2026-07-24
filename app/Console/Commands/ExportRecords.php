<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Records\RecordExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Export a single client team's herd records to disk (spec §229). Scoped to the
 * one team — never a cross-tenant dump. Complements the in-app export endpoint.
 */
class ExportRecords extends Command
{
    protected $signature = 'records:export {team : Team id} {--format=json : json|csv} {--disk=local}';

    protected $description = "Export a client team's herd records (cattle, health records, media) as JSON or CSV";

    public function handle(RecordExporter $exporter): int
    {
        $team = Team::find((int) $this->argument('team'));

        if ($team === null) {
            $this->error('Team not found.');

            return self::FAILURE;
        }

        $format = mb_strtolower((string) $this->option('format'));
        $disk = Storage::disk((string) $this->option('disk'));
        $stamp = now()->format('Ymd-His');

        if ($format === 'csv') {
            foreach ($exporter->toCsvTables($team) as $name => $rows) {
                $csv = collect($rows)->map(function (array $row) {
                    $out = fopen('php://temp', 'r+');
                    fputcsv($out, $row);
                    rewind($out);

                    return rtrim((string) stream_get_contents($out), "\n");
                })->implode("\n");

                $disk->put("exports/team-{$team->id}/{$stamp}/{$name}.csv", $csv);
            }

            $this->info("Exported CSV records for team {$team->id} to exports/team-{$team->id}/{$stamp}/.");

            return self::SUCCESS;
        }

        $path = "exports/team-{$team->id}/{$stamp}/records.json";
        $disk->put($path, json_encode($exporter->forTeam($team), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Exported JSON records for team {$team->id} to {$path}.");

        return self::SUCCESS;
    }
}
