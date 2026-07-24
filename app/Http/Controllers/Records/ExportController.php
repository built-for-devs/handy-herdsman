<?php

declare(strict_types=1);

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Records\RecordExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Data export (spec §229, §10b). A client exports their herd records anytime;
 * the export is scoped strictly to their own team, so it can never contain
 * another client's data. Supports JSON and a CSV zip.
 */
class ExportController extends Controller
{
    use ResolvesCurrentTeam;

    public function __construct(private readonly RecordExporter $exporter) {}

    public function show(Request $request): Response
    {
        $this->currentTeam($request); // ensure the user has a tenant

        return Inertia::render('records/Export');
    }

    public function download(Request $request): StreamedResponse
    {
        $team = $this->currentTeam($request);
        $format = $request->string('format')->lower()->value();

        return $format === 'csv'
            ? $this->csvZip($team)
            : $this->json($team);
    }

    private function json(Team $team): StreamedResponse
    {
        $payload = $this->exporter->forTeam($team);
        $filename = $this->filename($team, 'json');

        return ResponseFactory::streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    private function csvZip(Team $team): StreamedResponse
    {
        $tables = $this->exporter->toCsvTables($team);
        $tmp = tempnam(sys_get_temp_dir(), 'records-export-').'.zip';

        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($tables as $name => $rows) {
            $zip->addFromString("{$name}.csv", $this->toCsvString($rows));
        }

        $zip->close();

        return ResponseFactory::streamDownload(function () use ($tmp) {
            readfile($tmp);
            @unlink($tmp);
        }, $this->filename($team, 'zip'), ['Content-Type' => 'application/zip']);
    }

    /**
     * @param  list<array<int, mixed>>  $rows
     */
    private function toCsvString(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    private function filename(Team $team, string $ext): string
    {
        return 'herd-records-team-'.$team->id.'-'.now()->format('Ymd-His').'.'.$ext;
    }
}
