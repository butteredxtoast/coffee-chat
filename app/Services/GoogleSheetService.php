<?php

namespace App\Services;

use Google\Client;
use Google\Collection;
use Google\Exception;
use Google\Service\Sheets;
use Illuminate\Support\Facades\Log;

class GoogleSheetService
{
    private Sheets $service;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $client = new Client();
        $client->setAuthConfig(config('services.google.credentials_path'));
        $client->addScope(Sheets::SPREADSHEETS);

        $this->service = new Sheets($client);
    }

    public function appendMatches(\Illuminate\Support\Collection $matches): bool
    {
//        if ($matches->isEmpty()) {
//            Log::info('No matches to append to sheet');
//            return true;  // Return success since there's nothing to do
//        }

        try {

            $values = $matches->map(fn($match) => [
                $match->matched_at->format('Y-m-d'),
                $match->member1->name,
                $match->member2->name,
                $match->member3?->name ?? 'N/A'
            ])->toArray();

            $data = [
                new Sheets\ValueRange([
                    'range' => 'Matches!A:D',
                    'values' => $values
                ])
            ];

            $body = new Sheets\BatchUpdateValuesRequest([
                'valueInputOption' => 'RAW',
                'data' => $data
            ]);

            $this->service->spreadsheets_values->append(
                config('services.google.sheets_id'),
                'Sheet1!A:D',
                new Sheets\ValueRange(['values' => $values]),
                ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS']
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to append matches to sheet', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
