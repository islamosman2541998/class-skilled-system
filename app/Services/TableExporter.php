<?php

namespace App\Services;

use App\Tables\Column;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Writes a query to an XLSX file row by row (constant memory, no queue) and sends it as a download.
 */
class TableExporter
{
    /**
     * @param  Builder<Model>  $query
     * @param  list<Column>  $columns
     */
    public function download(string $fileName, Builder $query, array $columns): BinaryFileResponse
    {
        $directory = storage_path('app/private/exports');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.Str::uuid().'.xlsx';

        $this->write($path, $query, $columns);

        return response()->download($path, $fileName)->deleteFileAfterSend();
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<Column>  $columns
     * @return int number of data rows written
     */
    public function write(string $path, Builder $query, array $columns): int
    {
        $headers = array_map(fn (Column $column) => $column->label(), $columns);

        $writer = SimpleExcelWriter::create($path)->addHeader($headers);
        $count = 0;

        foreach ($query->reorder()->lazyById(500) as $row) {
            $writer->addRow(array_map(fn (Column $column) => $column->exportValue($row), $columns));
            $count++;
        }

        $writer->close();

        return $count;
    }
}
