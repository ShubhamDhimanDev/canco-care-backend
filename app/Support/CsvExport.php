<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a UTF-8 CSV that opens correctly in Excel, one chunk of rows at a time, so a long list never
 * has to fit in memory.
 */
final class CsvExport
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  list<string>  $header
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): list<mixed>  $row
     */
    public static function download(string $filename, array $header, Builder $query, callable $row): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $query, $row): void {
            $out = new SplFileObject('php://output', 'w');
            // The byte-order mark makes Excel read the file as UTF-8.
            $out->fwrite("\xEF\xBB\xBF");
            $out->fputcsv($header, escape: '');

            $query->chunk(200, function ($chunk) use ($out, $row): void {
                foreach ($chunk as $model) {
                    $out->fputcsv(array_map(self::safeCell(...), $row($model)), escape: '');
                }
            });
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A spreadsheet runs a cell that starts with = + - @ as a formula; visitors typed these cells, so a
     * leading quote makes them plain text.
     */
    public static function safeCell(mixed $value): string
    {
        $text = (string) $value;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$text : $text;
    }
}
